<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

/**
 * PCBuilderController — Mesin Auto-Matching & Validasi Kompatibilitas PC Builder.
 * Menerapkan rule sekuensial dari dokumen alur_dan_logika_pc_builder_ai_agent.md:
 * - CPU & Motherboard Socket match
 * - Motherboard & RAM Type match (DDR4 / DDR5)
 * - Motherboard & Case Form Factor match
 * - GPU Length vs Case Max GPU Length
 * - Wattage Calculation + Headroom Safety Margin (1.2x)
 */
class PCBuilderController extends Controller
{
    /**
     * Validasi Kompatibilitas Rakitan PC & Kalkulator Daya.
     * POST /api/builder/validate
     * Body: {
     *   "cpu_id": "...",
     *   "motherboard_id": "...",
     *   "ram_id": "...",
     *   "storage_id": "...",
     *   "gpu_id": "...",
     *   "case_id": "...",
     *   "psu_id": "..."
     * }
     */
    public function validateBuild(Request $request): JsonResponse
    {
        $analysis = $this->analyzeComponents($request->all());

        return response()->json([
            'status' => 'success',
            'data'   => $analysis,
        ]);
    }

    /**
     * Masukkan Seluruh Komponen Rakitan PC ke Keranjang Sekaligus.
     * POST /api/builder/add-to-cart
     * Body: {
     *   "user_id": "...",
     *   "session_id": "...",
     *   "cpu_id": "...",
     *   "motherboard_id": "...",
     *   "ram_id": "...",
     *   "storage_id": "...",
     *   "gpu_id": "...",
     *   "case_id": "...",
     *   "psu_id": "..."
     * }
     */
    public function addBuildToCart(Request $request): JsonResponse
    {
        $analysis = $this->analyzeComponents($request->all());

        if (!$analysis['is_compatible']) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Rakitan PC memiliki masalah kompatibilitas fisik/daya!',
                'errors'  => $analysis['errors'],
            ], 422);
        }

        // Cek stok semua komponen sebelum masuk keranjang
        foreach ($analysis['resolved_products'] as $product) {
            if ($product->stock < 1) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Stok {$product->name} habis.",
                    'errors'  => ["Stok {$product->name} habis."],
                ], 422);
            }
        }

        // Tentukan cart
        $userId = $request->input('user_id');
        $sessionId = $request->input('session_id', 'guest-demo-session');

        $cart = $userId
            ? Cart::firstOrCreate(['user_id' => $userId], ['total_price' => 0])
            : Cart::firstOrCreate(['session_id' => $sessionId], ['total_price' => 0]);

        $buildId = (string) new ObjectId();
        $addedItems = [];

        foreach ($analysis['resolved_products'] as $product) {
            $item = CartItem::create([
                'cart_id'        => $cart->_id,
                'product_id'     => $product->_id,
                'source_type'    => 'builder',
                'build_id'       => $buildId,
                'qty'            => 1,
                'price_snapshot' => $product->effective_price,
            ]);
            $addedItems[] = $item;
        }

        // Recalculate cart
        $cart->total_price = (float) CartItem::where('cart_id', $cart->_id)
            ->get()
            ->sum(fn ($i) => $i->price_snapshot * $i->qty);
        $cart->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Seluruh komponen rakitan PC berhasil dimasukkan ke keranjang belanja!',
            'data'    => [
                'build_id'       => $buildId,
                'cart_id'        => $cart->_id,
                'total_build'    => $analysis['total_price'],
                'items_count'    => count($addedItems),
                'power_analysis' => $analysis['power_analysis'],
            ],
        ], 201);
    }

    /**
     * Logika Inti Auto-Matching Engine
     */
    private function analyzeComponents(array $input): array
    {
        $errors = [];
        $warnings = [];
        $resolvedProducts = [];
        $totalPrice = 0.0;

        // 1. Ambil & validasi produk yang dipilih (wajib lengkap 7 komponen)
        $requiredMap = [
            'cpu_id'         => 'CPU',
            'motherboard_id' => 'Motherboard',
            'ram_id'         => 'RAM',
            'storage_id'     => 'SSD',
            'gpu_id'         => 'GPU',
            'case_id'        => 'Case',
            'psu_id'         => 'PSU',
        ];

        $found = [];
        foreach ($requiredMap as $field => $expectedCategory) {
            $id = $input[$field] ?? null;
            if (empty($id)) {
                $errors[] = "Komponen {$expectedCategory} belum dipilih ({$field}).";
                continue;
            }
            $product = Product::find($id);
            if (!$product) {
                $errors[] = "Produk untuk {$expectedCategory} tidak ditemukan (ID: {$id}).";
                continue;
            }
            if (strcasecmp((string) $product->category, $expectedCategory) !== 0) {
                $errors[] = "Produk {$product->name} adalah kategori {$product->category}, bukan {$expectedCategory}.";
                continue;
            }
            $found[$field] = $product;
        }

        $cpu   = $found['cpu_id'] ?? null;
        $mobo  = $found['motherboard_id'] ?? null;
        $ram   = $found['ram_id'] ?? null;
        $ssd   = $found['storage_id'] ?? null;
        $gpu   = $found['gpu_id'] ?? null;
        $case  = $found['case_id'] ?? null;
        $psu   = $found['psu_id'] ?? null;

        $componentList = array_filter([$cpu, $mobo, $ram, $ssd, $gpu, $case, $psu]);
        foreach ($componentList as $p) {
            $resolvedProducts[$p->category] = $p;
            $totalPrice += $p->effective_price;
        }

        // 2. Rule: CPU & Motherboard Socket Matching
        if ($cpu && $mobo) {
            $cpuSocket = $cpu->specs['socket'] ?? null;
            $moboSocket = $mobo->specs['socket'] ?? null;

            if ($cpuSocket && $moboSocket && strcasecmp($cpuSocket, $moboSocket) !== 0) {
                $errors[] = "Socket tidak cocok! CPU {$cpu->name} menggunakan socket {$cpuSocket}, sedangkan Motherboard {$mobo->name} menggunakan socket {$moboSocket}.";
            }
        }

        // 3. Rule: Motherboard & RAM Type Matching
        if ($mobo && $ram) {
            $moboRamType = $mobo->specs['memory_type'] ?? ($mobo->specs['max_memory'] ?? '');
            $ramType = $ram->specs['type'] ?? '';

            if ($ramType && $moboRamType && stripos($moboRamType, $ramType) === false) {
                $errors[] = "Tipe RAM tidak kompatibel! Motherboard {$mobo->name} membutuhkan memori {$moboRamType}, tetapi RAM yang dipilih adalah {$ramType}.";
            }
        }

        // 4. Rule: Motherboard & Case Form Factor Compatibility
        if ($mobo && $case) {
            $moboForm = strtoupper($mobo->specs['form_factor'] ?? 'ATX');
            $caseForm = strtoupper($case->specs['form_factor'] ?? 'ATX');

            // ATX case supports ATX, Micro-ATX, Mini-ITX
            // Micro-ATX case supports Micro-ATX, Mini-ITX
            if (str_contains($caseForm, 'MINI-ITX') && $moboForm !== 'MINI-ITX') {
                $errors[] = "Casing Mini-ITX terlalu kecil untuk Motherboard {$moboForm} ({$mobo->name}).";
            } elseif (str_contains($caseForm, 'MICRO-ATX') && $moboForm === 'ATX') {
                $errors[] = "Casing Micro-ATX tidak muat untuk Motherboard berukuran standar ATX ({$mobo->name}).";
            }
        }

        // 5. Rule: GPU Length vs Case Max GPU
        if ($gpu && $case) {
            $maxGpuLength = (int) filter_var($case->specs['max_gpu'] ?? '400', FILTER_SANITIZE_NUMBER_INT);
            $gpuLength = (int) filter_var($gpu->specs['length'] ?? '320', FILTER_SANITIZE_NUMBER_INT);

            if ($maxGpuLength > 0 && $gpuLength > 0 && $gpuLength > $maxGpuLength) {
                $errors[] = "Panjang VGA ({$gpuLength}mm) melebihi batas maksimum casing {$case->name} ({$maxGpuLength}mm).";
            }
        }

        // 6. Rule: Wattage Calculation & 20% Safety Margin (Headroom 1.2x)
        $cpuSpecs = $cpu?->specs ?? [];
        $gpuSpecs = $gpu?->specs ?? [];
        $psuSpecs = $psu?->specs ?? [];
        $cpuTdp = (int) filter_var($cpuSpecs['tdp'] ?? '65', FILTER_SANITIZE_NUMBER_INT);
        $gpuTdp = (int) filter_var($gpuSpecs['tdp'] ?? '0', FILTER_SANITIZE_NUMBER_INT);
        $ramWatt = $ram ? 15 : 0;
        $storageWatt = $ssd ? 10 : 0;
        $baseLoad = 60; // Motherboard, fans, USB, and controllers

        $totalEstimatedWatt = $cpuTdp + $gpuTdp + $ramWatt + $storageWatt + $baseLoad;
        $recommendedMinPsuWatt = (int) ceil($totalEstimatedWatt * 1.2);

        $selectedPsuWatt = 0;
        $isPsuSufficient = true;

        if ($psu) {
            $selectedPsuWatt = (int) filter_var($psuSpecs['wattage'] ?? '500', FILTER_SANITIZE_NUMBER_INT);
            if ($selectedPsuWatt < $recommendedMinPsuWatt) {
                $isPsuSufficient = false;
                $warnings[] = "Peringatan Daya! Total konsumsi daya sistem adalah {$totalEstimatedWatt}W. Dengan margin keamanan 20%, disarankan PSU minimal {$recommendedMinPsuWatt}W, sedangkan PSU yang dipilih hanya {$selectedPsuWatt}W.";
            }
        }

        return [
            'is_compatible' => empty($errors),
            'errors'        => $errors,
            'warnings'      => $warnings,
            'total_price'   => $totalPrice,
            'power_analysis' => [
                'cpu_tdp_watt'              => $cpuTdp,
                'gpu_tdp_watt'              => $gpuTdp,
                'ram_watt'                  => $ramWatt,
                'storage_watt'              => $storageWatt,
                'system_base_load'          => $baseLoad,
                'total_estimated_watt'      => $totalEstimatedWatt,
                'safety_margin_factor'      => 1.2,
                'recommended_min_psu_watt'  => $recommendedMinPsuWatt,
                'selected_psu_watt'         => $selectedPsuWatt,
                'is_psu_sufficient'         => $isPsuSufficient,
            ],
            'resolved_products' => $resolvedProducts,
        ];
    }
}
