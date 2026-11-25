<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Collection;

/**
 * Sumber tunggal harga promo produk.
 *
 * Promo aktif yang cocok (berdasarkan product_ids atau brands) menimpa diskon
 * manual. Produk yang sengaja dilewati admin disimpan di excluded_product_ids.
 */
class PromotionService
{
    private ?Collection $activePromotions = null;

    /**
     * @return Collection<int, Promotion>
     */
    public function activePromotions(): Collection
    {
        if ($this->activePromotions === null) {
            $this->activePromotions = Promotion::active()
                ->orderBy('discount_percentage', 'desc')
                ->get();
        }

        return $this->activePromotions;
    }

    public function flush(): void
    {
        $this->activePromotions = null;
    }

    public function discountPercentageFor(Product $product): float
    {
        $best = 0.0;

        foreach ($this->activePromotions() as $promotion) {
            if (! $this->matches($promotion, $product)) {
                continue;
            }

            $best = max($best, (float) $promotion->discount_percentage);
        }

        return $best;
    }

    public function promoPrice(Product $product): ?float
    {
        $percentage = $this->discountPercentageFor($product);

        if ($percentage <= 0) {
            return null;
        }

        return round((float) $product->price * (1 - ($percentage / 100)), 2);
    }

    public function effectivePrice(Product $product): float
    {
        $promoPrice = $this->promoPrice($product);

        if ($promoPrice !== null && $promoPrice < (float) $product->price) {
            return $promoPrice;
        }

        return (float) $product->discount_price > 0
            ? (float) $product->discount_price
            : (float) $product->price;
    }

    public function matches(Promotion $promotion, Product $product): bool
    {
        $productId = (string) $product->getKey();

        if (in_array($productId, $this->stringList($promotion->excluded_product_ids), true)) {
            return false;
        }

        $productIds = $this->stringList($promotion->product_ids);
        $brands = $this->stringList($promotion->brands);

        if ($productIds === [] && $brands === []) {
            return false;
        }

        if ($productIds !== [] && ! in_array($productId, $productIds, true)) {
            return false;
        }

        if ($productIds === [] && $brands !== [] && ! $this->brandMatches($brands, (string) $product->brand)) {
            return false;
        }

        if ($promotion->override_manual_discount === false && (float) $product->discount_price > 0) {
            return false;
        }

        return true;
    }

    public function decorate(Product $product): Product
    {
        $percentage = $this->discountPercentageFor($product);
        $promoPrice = $percentage > 0
            ? round((float) $product->price * (1 - ($percentage / 100)), 2)
            : null;
        $effectivePrice = $promoPrice !== null && $promoPrice < (float) $product->price
            ? $promoPrice
            : ((float) $product->discount_price > 0 ? (float) $product->discount_price : (float) $product->price);

        $product->setAttribute('promo_discount_percentage', $percentage);
        $product->setAttribute('promo_price', $promoPrice);
        $product->setAttribute('effective_price', $effectivePrice);
        $product->setAttribute(
            'discount_percentage',
            (float) $product->price > 0 && $effectivePrice < (float) $product->price
                ? round((((float) $product->price - $effectivePrice) / (float) $product->price) * 100, 2)
                : 0.0,
        );

        return $product;
    }

    /**
     * @param  array<int, mixed>|null  $values
     * @return array<int, string>
     */
    private function stringList(?array $values): array
    {
        return array_values(array_filter(array_map(
            static fn ($value): string => (string) $value,
            $values ?? [],
        )));
    }

    /**
     * @param  array<int, string>  $brands
     */
    private function brandMatches(array $brands, string $productBrand): bool
    {
        foreach ($brands as $brand) {
            if (strcasecmp($brand, $productBrand) === 0) {
                return true;
            }
        }

        return false;
    }
}
