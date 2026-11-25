<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;

/**
 * Pengembalian stok pesanan, idempoten lewat flag stock_restored.
 */
class OrderStockService
{
    public function restore(Order $order): void
    {
        if ($order->stock_restored) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            Product::where('_id', $item->product_id)->increment('stock', (int) $item->qty);
        }

        $order->update(['stock_restored' => true]);
    }
}
