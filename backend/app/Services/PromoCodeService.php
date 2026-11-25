<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\PromoCode;

/**
 * Validasi dan alokasi diskon kode promo terhadap isi keranjang.
 */
class PromoCodeService
{
    /**
     * @return array{
     *     valid: bool,
     *     message: string,
     *     subtotal: float,
     *     eligible_subtotal: float,
     *     discount_amount: float,
     *     total_after_discount: float,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function validateForCart(PromoCode $promoCode, Cart $cart): array
    {
        $cart->loadMissing('items.product');

        $items = $cart->items
            ->filter(static fn ($item): bool => $item->product !== null)
            ->values();

        if ($items->isEmpty()) {
            return $this->invalid('Keranjang masih kosong');
        }

        $brands = array_values(array_filter(array_map(
            static fn ($brand): string => strtolower(trim((string) $brand)),
            $promoCode->brands ?? [],
        )));

        $subtotal = 0.0;
        $eligibleSubtotal = 0.0;
        $lineItems = [];

        foreach ($items as $item) {
            $unitPrice = (float) $item->product->effective_price;
            $qty = (int) $item->qty;
            $lineTotal = round($unitPrice * $qty, 2);
            $eligible = $brands === [] || in_array(strtolower((string) $item->product->brand), $brands, true);

            $subtotal += $lineTotal;

            if ($eligible) {
                $eligibleSubtotal += $lineTotal;
            }

            $lineItems[] = [
                'cart_item_id' => (string) $item->_id,
                'product_id' => (string) $item->product_id,
                'product_name' => $item->product->name,
                'brand' => $item->product->brand,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'eligible' => $eligible,
                'discount_amount' => 0.0,
            ];
        }

        $subtotal = round($subtotal, 2);
        $eligibleSubtotal = round($eligibleSubtotal, 2);

        if ($promoCode->min_subtotal !== null && $subtotal < (float) $promoCode->min_subtotal) {
            return $this->invalid(
                'Minimum belanja Rp '.number_format((float) $promoCode->min_subtotal, 0, ',', '.').' belum terpenuhi',
                $subtotal,
                $eligibleSubtotal,
            );
        }

        if ($eligibleSubtotal <= 0) {
            return $this->invalid(
                'Tidak ada produk yang memenuhi syarat brand kode promo ini',
                $subtotal,
                $eligibleSubtotal,
            );
        }

        $discountAmount = round($eligibleSubtotal * ((float) $promoCode->discount_percentage / 100), 2);
        $discountAmount = min($discountAmount, $eligibleSubtotal);

        $allocated = 0.0;
        $eligibleIndexes = [];

        foreach ($lineItems as $index => $lineItem) {
            if (! $lineItem['eligible']) {
                continue;
            }

            $eligibleIndexes[] = $index;
        }

        foreach ($eligibleIndexes as $position => $index) {
            $isLast = $position === count($eligibleIndexes) - 1;
            $share = $isLast
                ? round($discountAmount - $allocated, 2)
                : round($discountAmount * ($lineItems[$index]['line_total'] / $eligibleSubtotal), 2);

            $lineItems[$index]['discount_amount'] = max(0.0, min($share, $lineItems[$index]['line_total']));
            $allocated += $lineItems[$index]['discount_amount'];
        }

        return [
            'valid' => true,
            'message' => 'Kode promo dapat digunakan',
            'subtotal' => $subtotal,
            'eligible_subtotal' => $eligibleSubtotal,
            'discount_amount' => round($allocated, 2),
            'total_after_discount' => round(max(0, $subtotal - $allocated), 2),
            'items' => $lineItems,
        ];
    }

    /**
     * @return array{
     *     valid: false,
     *     message: string,
     *     subtotal: float,
     *     eligible_subtotal: float,
     *     discount_amount: float,
     *     total_after_discount: float,
     *     items: array<int, array<string, mixed>>
     * }
     */
    private function invalid(
        string $message,
        float $subtotal = 0.0,
        float $eligibleSubtotal = 0.0,
    ): array {
        return [
            'valid' => false,
            'message' => $message,
            'subtotal' => round($subtotal, 2),
            'eligible_subtotal' => round($eligibleSubtotal, 2),
            'discount_amount' => 0.0,
            'total_after_discount' => round($subtotal, 2),
            'items' => [],
        ];
    }
}
