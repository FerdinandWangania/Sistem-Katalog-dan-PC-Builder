<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan keuangan (PRD FR-023).
 *
 * Dasar perhitungan:
 * - Hanya order dengan payment berstatus `paid` pada periode.
 * - Diskon dihitung dari `price_list_snapshot - price_snapshot`.
 * - HPP memakai `hpp_snapshot` agar margin historis tidak berubah.
 * - PPN inklusif: DPP = total / (1 + ppn_rate/100).
 */
class FinanceService
{
    public function profitLoss(Carbon $from, Carbon $to): array
    {
        $orderIds = $this->paidOrderIds($from, $to);

        $base = [
            'period' => $this->period($from, $to),
            'orders_count' => 0,
            'items_sold' => 0,
            'revenue_gross' => 0.0,
            'discount_total' => 0.0,
            'revenue_net' => 0.0,
            'cogs' => 0.0,
            'profit' => 0.0,
            'profit_margin_percent' => 0.0,
            'missing_hpp_lines' => 0,
        ];

        if ($orderIds->isEmpty()) {
            return $base;
        }

        $items = OrderItem::whereIn('order_id', $orderIds)->get();

        $gross = 0.0;
        $net = 0.0;
        $discount = 0.0;
        $cogs = 0.0;
        $itemsSold = 0;
        $missingHpp = 0;

        foreach ($items as $item) {
            $qty = (int) $item->qty;
            $price = (float) $item->price_snapshot;
            $list = $item->price_list_snapshot !== null
                ? (float) $item->price_list_snapshot
                : $price;

            $itemsSold += $qty;
            $gross += $list * $qty;
            $net += $price * $qty;
            $discount += max(0.0, $list - $price) * $qty;

            if ($item->hpp_snapshot !== null) {
                $cogs += (float) $item->hpp_snapshot * $qty;
            } else {
                $missingHpp++;
            }
        }

        $profit = $net - $cogs;

        return array_merge($base, [
            'orders_count' => Order::whereIn('_id', $orderIds)->count(),
            'items_sold' => $itemsSold,
            'revenue_gross' => round($gross, 2),
            'discount_total' => round($discount, 2),
            'revenue_net' => round($net, 2),
            'cogs' => round($cogs, 2),
            'profit' => round($profit, 2),
            'profit_margin_percent' => $net > 0 ? round($profit / $net * 100, 2) : 0.0,
            'missing_hpp_lines' => $missingHpp,
        ]);
    }

    public function tax(Carbon $from, Carbon $to): array
    {
        $orderIds = $this->paidOrderIds($from, $to);
        $rate = (float) config('finance.ppn_rate', 11);

        $orders = $orderIds->isEmpty()
            ? collect()
            : Order::whereIn('_id', $orderIds)->orderBy('created_at')->get();

        $gross = 0.0;
        $lines = [];

        foreach ($orders as $order) {
            $amount = (float) $order->total_amount;
            $dpp = $amount / (1 + $rate / 100);
            $ppn = $amount - $dpp;

            $gross += $amount;

            $lines[] = [
                'order_id' => (string) $order->_id,
                'order_number' => $order->order_number,
                'date' => optional($order->created_at)->toDateString(),
                'gross_amount' => round($amount, 2),
                'dpp' => round($dpp, 2),
                'ppn' => round($ppn, 2),
            ];
        }

        $dppTotal = $gross / (1 + $rate / 100);

        return [
            'period' => $this->period($from, $to),
            'tax_rate_percent' => $rate,
            'orders_count' => $orders->count(),
            'gross_amount' => round($gross, 2),
            'dpp' => round($dppTotal, 2),
            'ppn' => round($gross - $dppTotal, 2),
            'orders' => $lines,
        ];
    }

    /**
     * Margin per produk / kategori / brand.
     *
     * @param  'product'|'category'|'brand'  $groupBy
     */
    public function margin(Carbon $from, Carbon $to, string $groupBy = 'product'): array
    {
        $orderIds = $this->paidOrderIds($from, $to);

        if ($orderIds->isEmpty()) {
            return [
                'period' => $this->period($from, $to),
                'group_by' => $groupBy,
                'rows' => [],
            ];
        }

        $items = OrderItem::whereIn('order_id', $orderIds)->get();

        $productIds = $items->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $products = Product::whereIn('_id', $productIds)
            ->get()
            ->keyBy(fn (Product $product) => (string) $product->_id);

        $groups = [];

        foreach ($items as $item) {
            $product = $products[(string) $item->product_id] ?? null;

            $key = match ($groupBy) {
                'category' => $product?->category ?? 'Tidak diketahui',
                'brand' => $product?->brand ?? 'Tidak diketahui',
                default => (string) $item->product_id,
            };

            $groups[$key] ??= [
                'key' => $key,
                'label' => $groupBy === 'product'
                    ? ($product?->name ?? 'Produk terhapus')
                    : $key,
                'quantity' => 0,
                'revenue' => 0.0,
                'cogs' => 0.0,
                'profit' => 0.0,
                'margin_percent' => 0.0,
                'missing_hpp_lines' => 0,
            ];

            $qty = (int) $item->qty;
            $price = (float) $item->price_snapshot;

            $groups[$key]['quantity'] += $qty;
            $groups[$key]['revenue'] += $price * $qty;

            if ($item->hpp_snapshot !== null) {
                $groups[$key]['cogs'] += (float) $item->hpp_snapshot * $qty;
            } else {
                $groups[$key]['missing_hpp_lines']++;
            }
        }

        $rows = array_map(function (array $row): array {
            $row['profit'] = $row['revenue'] - $row['cogs'];
            $row['margin_percent'] = $row['revenue'] > 0
                ? round($row['profit'] / $row['revenue'] * 100, 2)
                : 0.0;

            $row['revenue'] = round($row['revenue'], 2);
            $row['cogs'] = round($row['cogs'], 2);
            $row['profit'] = round($row['profit'], 2);

            return $row;
        }, array_values($groups));

        usort($rows, fn (array $a, array $b) => $b['profit'] <=> $a['profit']);

        return [
            'period' => $this->period($from, $to),
            'group_by' => $groupBy,
            'rows' => $rows,
        ];
    }

    /**
     * Produk terlaris berdasarkan order berbayar pada periode.
     *
     * @return array{period: array{from: string, to: string}, rows: array<int, array<string, mixed>>}
     */
    public function topProducts(Carbon $from, Carbon $to, int $limit = 10): array
    {
        $orderIds = $this->paidOrderIds($from, $to);

        if ($orderIds->isEmpty()) {
            return [
                'period' => $this->period($from, $to),
                'rows' => [],
            ];
        }

        $items = OrderItem::whereIn('order_id', $orderIds)->get();
        $productIds = $items->pluck('product_id')
            ->filter()
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values();

        $products = Product::whereIn('_id', $productIds)
            ->get()
            ->keyBy(static fn (Product $product): string => (string) $product->_id);

        $rows = [];

        foreach ($items as $item) {
            $productId = (string) $item->product_id;
            $product = $products[$productId] ?? null;

            $rows[$productId] ??= [
                'product_id' => $productId,
                'name' => $product?->name ?? 'Produk terhapus',
                'brand' => $product?->brand,
                'category' => $product?->category,
                'quantity' => 0,
                'revenue' => 0.0,
                'orders_count' => 0,
                'order_ids' => [],
            ];

            $quantity = (int) $item->qty;

            $rows[$productId]['quantity'] += $quantity;
            $rows[$productId]['revenue'] += (float) $item->price_snapshot * $quantity;
            $rows[$productId]['order_ids'][(string) $item->order_id] = true;
        }

        $result = array_map(static function (array $row): array {
            $row['orders_count'] = count($row['order_ids']);
            unset($row['order_ids']);
            $row['revenue'] = round($row['revenue'], 2);

            return $row;
        }, array_values($rows));

        usort($result, static fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        return [
            'period' => $this->period($from, $to),
            'rows' => array_slice($result, 0, max(1, $limit)),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function paidOrderIds(Carbon $from, Carbon $to): Collection
    {
        return Payment::where('status', 'paid')
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<=', $to)
            ->get()
            ->pluck('order_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();
    }

    private function period(Carbon $from, Carbon $to): array
    {
        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }
}
