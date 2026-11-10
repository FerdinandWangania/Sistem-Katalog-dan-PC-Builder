<?php

namespace App\Support;

/**
 * Normalisasi nilai spesifikasi produk.
 *
 * Dipakai validation engine agar data dari admin (mis. "LGA 1700",
 * "lga-1700", "LGA_1700") tetap dianggap sama, dan agar nilai seperti
 * "422mm" / "1000W" bisa dibandingkan sebagai angka.
 */
class SpecNormalizer
{
    /**
     * Normalisasi socket: huruf besar, tanpa spasi/hyphen/underscore.
     * Contoh: "LGA 1700" -> "LGA1700", "am5" -> "AM5".
     */
    public static function socket(mixed $value): string
    {
        return strtoupper(preg_replace('/[\s\-_]+/', '', (string) $value));
    }

    /**
     * Normalisasi nama kategori menjadi slug: "Case Fan" -> "case_fan".
     */
    public static function category(mixed $value): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string) $value), '_'));
    }

    /**
     * Normalisasi tipe memori: "DDR 5" -> "DDR5".
     */
    public static function memoryType(mixed $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]+/i', '', (string) $value));
    }

    /**
     * Peringkat form factor motherboard/case.
     * MINI-ITX (1) < MICRO-ATX (2) < ATX (3) < E-ATX (4).
     * Null bila tidak dikenali.
     */
    public static function formFactorRank(mixed $value): ?int
    {
        $normalized = strtoupper(preg_replace('/[^A-Z]/i', '', (string) $value));

        if ($normalized === '') {
            return null;
        }

        return match (true) {
            str_contains($normalized, 'MINIITX') => 1,
            str_contains($normalized, 'MICROATX'), str_contains($normalized, 'MATX') => 2,
            str_contains($normalized, 'EATX') => 4,
            str_contains($normalized, 'ATX') => 3,
            default => null,
        };
    }

    /**
     * Ambil angka pertama dari nilai apa pun.
     * Contoh: 422 -> 422.0, "422mm" -> 422.0, "1.000 W" -> 1000.0.
     * Null bila tidak ada angka.
     */
    public static function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        // Buang pemisah ribuan gaya Indonesia ("1.000") sebelum ambil angka.
        $candidate = preg_replace_callback(
            '/\d{1,3}(?:\.\d{3})+(?!\d)/',
            fn (array $m) => str_replace('.', '', $m[0]),
            $value,
        );

        if (! preg_match('/-?\d+(?:[.,]\d+)?/', $candidate, $matches)) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[0]);
    }
}
