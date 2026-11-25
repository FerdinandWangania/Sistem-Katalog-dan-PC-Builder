<?php

namespace App\Console\Commands;

use App\Services\OrderExpiryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire')]
#[Description('Batalkan pesanan pending yang kedaluwarsa dan kembalikan stok')]
class ExpireOrders extends Command
{
    public function handle(OrderExpiryService $orderExpiryService): int
    {
        $count = $orderExpiryService->expirePending();

        $this->info("{$count} pesanan kedaluwarsa diproses.");

        return self::SUCCESS;
    }
}
