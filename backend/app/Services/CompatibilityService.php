<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Support\SpecNormalizer;

/**
 * Validation engine PC Builder (PRD §6, RULE-01 s.d. RULE-06).
 *
 * Satu sumber aturan untuk:
 * - POST /api/builder/validate
 * - POST /api/builder/add-to-cart
 * - PUT  /api/builder/builds/{build_id}
 * - revalidasi checkout per build_id
 */
class CompatibilityService
{
    /** Field input builder => slug kategori. */
    public const REQUIRED_SLOTS = [
        'cpu_id' => 'cpu',
        'motherboard_id' => 'motherboard',
        'ram_id' => 'ram',
        'storage_id' => 'ssd',
        'gpu_id' => 'gpu',
        'case_id' => 'case',
        'psu_id' => 'psu',
    ];

    public const SLOT_LABELS = [
        'cpu' => 'CPU',
        'motherboard' => 'Motherboard',
        'ram' => 'RAM',
        'ssd' => 'SSD',
        'gpu' => 'GPU',
        'case' => 'Case',
        'psu' => 'PSU',
    ];

    /** Slug kategori => field input builder. */
    public const CATEGORY_TO_SLOT = [
        'cpu' => 'cpu_id',
        'motherboard' => 'motherboard_id',
        'ram' => 'ram_id',
        'ssd' => 'storage_id',
        'gpu' => 'gpu_id',
        'case' => 'case_id',
        'psu' => 'psu_id',
    ];

    private const PSU_SAFETY_MARGIN = 1.2;

    private const BASE_SYSTEM_LOAD_W = 60;

    private const RAM_WATT = 15;

    private const STORAGE_WATT = 10;

    /**
     * Validasi komposisi rakitan dari field input builder.
     *
     * @param  array{ignore_psu_warning?: bool, build_name?: string|null, extra_product_ids?: array}  $input
     * @param  array{ignore_psu_warning?: bool, build_name?: string|null}  $options
     */
    public function analyze(array $input, array $options = []): array
    {
        $errors = [];
        $errorDetails = [];
        $warnings = [];
        $warningDetails = [];
        $missingSlots = [];

        $pushError = function (string $message, ?string $rule = null, ?string $component = null) use (&$errors, &$errorDetails): void {
            $errors[] = $message;
            $errorDetails[] = array_filter([
                'rule' => $rule,
                'component' => $component,
                'message' => $message,
            ], fn ($value) => $value !== null);
        };

        $pushWarning = function (string $message, ?string $rule = null, ?string $component = null) use (&$warnings, &$warningDetails): void {
            $warnings[] = $message;
            $warningDetails[] = array_filter([
                'rule' => $rule,
                'component' => $component,
                'message' => $message,
            ], fn ($value) => $value !== null);
        };

        $ignorePsuWarning = filter_var(
            $options['ignore_psu_warning'] ?? $input['ignore_psu_warning'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        // ---------------------------------------------------------------
        // RULE-05: kelengkapan 7 slot utama + ketersediaan/stok
        // ---------------------------------------------------------------
        $components = [];
        $resolvedIds = [];

        foreach (self::REQUIRED_SLOTS as $field => $slot) {
            $label = self::SLOT_LABELS[$slot];
            $id = $input[$field] ?? null;

            if ($field === 'storage_id' && blank($id)) {
                $id = $input['ssd_id'] ?? null;
            }

            if (blank($id)) {
                $missingSlots[] = $label;
                $pushError("Komponen {$label} belum dipilih ({$field}).", 'RULE-05', $slot);

                continue;
            }

            $product = $this->findProduct($id);

            if (! $product) {
                $pushError("Produk untuk {$label} tidak ditemukan (ID: {$id}).", 'RULE-05', $slot);

                continue;
            }

            if (SpecNormalizer::category($product->category) !== $slot) {
                $pushError(
                    "Produk {$product->name} adalah kategori {$product->category}, bukan {$label}.",
                    'RULE-05',
                    $slot,
                );

                continue;
            }

            $components[$slot] = $product;
            $resolvedIds[(string) $product->_id] = true;

            if ((int) $product->stock < 1) {
                $pushError("Stok {$product->name} habis.", 'RULE-05', $slot);
            }
        }

        // Item ekstra (13 baris tambahan): hanya cek keberadaan + stok.
        $extras = [];
        foreach ((array) ($input['extra_product_ids'] ?? []) as $index => $id) {
            if (blank($id)) {
                continue;
            }

            $product = $this->findProduct($id);

            if (! $product) {
                $pushError('Produk tambahan #'.($index + 1)." tidak ditemukan (ID: {$id}).", 'RULE-05', 'extra');

                continue;
            }

            $productKey = (string) $product->_id;
            if (isset($resolvedIds[$productKey])) {
                continue;
            }
            $resolvedIds[$productKey] = true;

            if ((int) $product->stock < 1) {
                $pushError("Stok {$product->name} habis (item tambahan).", 'RULE-05', 'extra');

                continue;
            }

            $extras[] = $product;
        }

        $cpu = $components['cpu'] ?? null;
        $mobo = $components['motherboard'] ?? null;
        $ram = $components['ram'] ?? null;
        $ssd = $components['ssd'] ?? null;
        $gpu = $components['gpu'] ?? null;
        $case = $components['case'] ?? null;
        $psu = $components['psu'] ?? null;

        $cpuSpecs = (array) ($cpu?->specs ?? []);
        $moboSpecs = (array) ($mobo?->specs ?? []);
        $ramSpecs = (array) ($ram?->specs ?? []);
        $gpuSpecs = (array) ($gpu?->specs ?? []);
        $caseSpecs = (array) ($case?->specs ?? []);
        $psuSpecs = (array) ($psu?->specs ?? []);

        // ---------------------------------------------------------------
        // RULE-01: socket CPU <-> Motherboard (setelah normalisasi)
        // ---------------------------------------------------------------
        if ($cpu && $mobo) {
            $cpuSocket = SpecNormalizer::socket($cpuSpecs['socket'] ?? '');
            $moboSocket = SpecNormalizer::socket($moboSpecs['socket'] ?? '');

            if ($cpuSocket !== '' && $moboSocket !== '' && $cpuSocket !== $moboSocket) {
                $pushError(
                    "Socket tidak cocok! CPU {$cpu->name} memakai socket {$cpuSpecs['socket']}, "
                    ."sedangkan Motherboard {$mobo->name} memakai socket {$moboSpecs['socket']}.",
                    'RULE-01',
                    'socket',
                );
            }
        }

        // ---------------------------------------------------------------
        // RULE-02: form factor Motherboard <-> Case (hierarki ukuran)
        // ---------------------------------------------------------------
        if ($mobo && $case) {
            $moboRank = SpecNormalizer::formFactorRank($moboSpecs['form_factor'] ?? null);
            $caseRank = SpecNormalizer::formFactorRank($caseSpecs['form_factor'] ?? null);

            if ($moboRank !== null && $caseRank !== null && $moboRank > $caseRank) {
                $pushError(
                    "Form factor Motherboard {$mobo->name} ({$moboSpecs['form_factor']}) lebih besar "
                    ."dari Case {$case->name} ({$caseSpecs['form_factor']}).",
                    'RULE-02',
                    'form_factor',
                );
            }
        }

        // ---------------------------------------------------------------
        // RULE-04: tipe memori RAM didukung Motherboard
        // ---------------------------------------------------------------
        if ($mobo && $ram) {
            $ramType = SpecNormalizer::memoryType($ramSpecs['type'] ?? '');
            $moboType = SpecNormalizer::memoryType($moboSpecs['memory_type'] ?? $moboSpecs['max_memory'] ?? '');

            if ($ramType !== '' && $moboType !== ''
                && ! str_contains($moboType, $ramType)
                && ! str_contains($ramType, $moboType)) {
                $pushError(
                    "Tipe RAM tidak kompatibel! Motherboard {$mobo->name} mendukung {$moboSpecs['memory_type']}, "
                    ."sedangkan RAM {$ram->name} bertipe {$ramSpecs['type']}.",
                    'RULE-04',
                    'memory_type',
                );
            }
        }

        // ---------------------------------------------------------------
        // RULE-06: panjang VGA <-> batas Case (angka numerik)
        // ---------------------------------------------------------------
        if ($gpu && $case) {
            $gpuLength = SpecNormalizer::number($gpuSpecs['length'] ?? $gpuSpecs['length_mm'] ?? null);
            $caseMax = SpecNormalizer::number($caseSpecs['max_gpu_length'] ?? $caseSpecs['max_gpu'] ?? null);

            if ($gpuLength !== null && $caseMax !== null && $gpuLength > $caseMax) {
                $pushError(
                    "Panjang VGA {$gpu->name} ({$gpuLength}mm) melebihi batas maksimum "
                    ."Case {$case->name} ({$caseMax}mm).",
                    'RULE-06',
                    'length',
                );
            }
        }

        // ---------------------------------------------------------------
        // RULE-03: daya PSU (error, bisa di-bypass dengan ignore_psu_warning)
        // ---------------------------------------------------------------
        $cpuTdp = (int) round(SpecNormalizer::number($cpuSpecs['tdp'] ?? null) ?? 0);
        $gpuTdp = (int) round(SpecNormalizer::number($gpuSpecs['tdp'] ?? null) ?? 0);
        $ramWatt = $ram ? self::RAM_WATT : 0;
        $storageWatt = $ssd ? self::STORAGE_WATT : 0;
        $baseLoad = self::BASE_SYSTEM_LOAD_W;
        $totalWatt = $cpuTdp + $gpuTdp + $ramWatt + $storageWatt + $baseLoad;
        $recommendedW = (int) ceil($totalWatt * self::PSU_SAFETY_MARGIN);

        $selectedPsuWatt = 0;
        $psuStatus = 'unknown';

        if ($psu) {
            $selectedPsuWatt = (int) round(
                SpecNormalizer::number($psuSpecs['wattage_w'] ?? $psuSpecs['wattage'] ?? null) ?? 0,
            );

            if ($selectedPsuWatt > 0) {
                $psuStatus = $selectedPsuWatt >= $recommendedW ? 'sufficient' : 'insufficient';

                if ($psuStatus === 'insufficient') {
                    $message = "Daya PSU tidak cukup! Total estimasi sistem {$totalWatt}W; dengan margin 20% "
                        ."dibutuhkan minimal {$recommendedW}W, sedangkan PSU terpilih hanya {$selectedPsuWatt}W.";

                    if ($ignorePsuWarning) {
                        $pushWarning($message.' (dipaksa lanjut oleh pengguna)', 'RULE-03', 'psu');
                    } else {
                        $pushError($message, 'RULE-03', 'psu');
                    }
                }
            } else {
                $pushWarning(
                    "Wattage PSU {$psu->name} tidak terbaca; kecukupan daya tidak dapat diverifikasi.",
                    'RULE-03',
                    'psu',
                );
            }
        }

        $isPsuSufficient = match ($psuStatus) {
            'sufficient' => true,
            'insufficient' => false,
            default => null,
        };

        // ---------------------------------------------------------------
        // Total harga (7 komponen wajib + item ekstra)
        // ---------------------------------------------------------------
        $totalPrice = 0.0;
        foreach (array_merge(array_values($components), $extras) as $product) {
            $totalPrice += (float) $product->effective_price;
        }

        $buildName = $this->resolveBuildName($options['build_name'] ?? $input['build_name'] ?? null, $cpu);

        return [
            'is_compatible' => empty($errors),
            'errors' => $errors,
            'error_details' => $errorDetails,
            'warnings' => $warnings,
            'warning_details' => $warningDetails,
            'missing_slots' => $missingSlots,
            'build_name' => $buildName,
            'total_price' => $totalPrice,
            'grand_total' => $totalPrice,
            'power_analysis' => [
                'cpu_tdp_watt' => $cpuTdp,
                'gpu_tdp_watt' => $gpuTdp,
                'ram_watt' => $ramWatt,
                'storage_watt' => $storageWatt,
                'system_base_load' => $baseLoad,
                'total_estimated_watt' => $totalWatt,
                'safety_margin_factor' => self::PSU_SAFETY_MARGIN,
                'recommended_min_psu_watt' => $recommendedW,
                'selected_psu_watt' => $selectedPsuWatt,
                'is_psu_sufficient' => $isPsuSufficient,
                'psu_status' => $psuStatus,
            ],
            'components' => $components,
            'extras' => $extras,
            'resolved_products' => array_merge(array_values($components), $extras),
        ];
    }

    /**
     * Validasi rakitan langsung dari item keranjang (revalidasi checkout).
     *
     * @param  iterable<int, CartItem>  $items
     */
    public function analyzeCartItems(iterable $items, array $options = []): array
    {
        $items = collect($items);

        $slotIds = [];
        $extraIds = [];
        $preErrors = [];
        $duplicates = [];

        foreach ($items as $item) {
            $product = $item->product ?: $this->findProduct($item->product_id);

            if (! $product) {
                $preErrors[] = 'Terdapat produk pada rakitan yang sudah tidak tersedia.';

                continue;
            }

            $slotField = self::CATEGORY_TO_SLOT[SpecNormalizer::category($product->category)] ?? null;

            if ($slotField === null) {
                $extraIds[] = (string) $product->_id;

                continue;
            }

            if (isset($slotIds[$slotField])) {
                $duplicates[] = self::SLOT_LABELS[self::REQUIRED_SLOTS[$slotField]] ?? $slotField;

                continue;
            }

            $slotIds[$slotField] = (string) $product->_id;
        }

        $buildName = $options['build_name']
            ?? $items->first()?->build_name
            ?? null;

        $input = $slotIds;
        if ($extraIds !== []) {
            $input['extra_product_ids'] = $extraIds;
        }

        $result = $this->analyze($input, array_merge($options, ['build_name' => $buildName]));

        foreach ($duplicates as $label) {
            $message = "Komponen {$label} muncul lebih dari satu kali dalam satu rakitan.";
            $result['errors'][] = $message;
            $result['error_details'][] = ['rule' => 'RULE-05', 'component' => $label, 'message' => $message];
        }

        foreach ($preErrors as $message) {
            $result['errors'][] = $message;
            $result['error_details'][] = ['rule' => 'RULE-05', 'message' => $message];
        }

        $result['is_compatible'] = empty($result['errors']);

        return $result;
    }

    private function resolveBuildName(?string $name, ?Product $cpu): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            $name = $cpu?->name ?? 'Rakitan PC';
        }

        return mb_substr($name, 0, 60);
    }

    private function findProduct(mixed $id): ?Product
    {
        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        $id = trim((string) $id);

        if ($id === '') {
            return null;
        }

        try {
            return Product::find($id);
        } catch (\Throwable) {
            return null;
        }
    }
}
