<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderExpiryTest extends TestCase
{
    /** @var array<int, string> */
    private array $userIds = [];

    /** @var array<int, string> */
    private array $productIds = [];

    /** @var array<int, string> */
    private array $orderIds = [];

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Customer Expiry',
            'email' => 'expiry-'.uniqid().'@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $this->userIds[] = (string) $this->customer->_id;
    }

    protected function tearDown(): void
    {
        PaymentLog::whereIn('order_id', $this->orderIds)->delete();
        Payment::whereIn('order_id', $this->orderIds)->delete();
        OrderItem::whereIn('order_id', $this->orderIds)->delete();
        Order::whereIn('_id', $this->orderIds)->delete();
        Product::whereIn('_id', $this->productIds)->delete();
        User::whereIn('_id', $this->userIds)->delete();

        parent::tearDown();
    }

    public function test_expired_pending_order_is_cancelled_and_stock_restored_once(): void
    {
        [$order, $payment, $product] = $this->makeOrder([
            'expired_at' => now()->subMinute(),
            'payment_status' => 'pending',
            'stock' => 8,
            'qty' => 2,
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $order->refresh();
        $payment->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertTrue((bool) $order->stock_restored);
        $this->assertSame('expired', $payment->status);
        $this->assertSame(10, (int) $product->fresh()->stock);

        $log = PaymentLog::where('order_id', $order->_id)->first();
        $this->assertNotNull($log);
        $this->assertSame('expired', $log->reconciled_status);
        $this->assertSame('system_expired', $log->gateway_status);

        $this->artisan('orders:expire')->assertSuccessful();
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_future_order_and_paid_order_are_not_cancelled(): void
    {
        [$futureOrder, $futurePayment, $futureProduct] = $this->makeOrder([
            'expired_at' => now()->addHour(),
            'payment_status' => 'pending',
            'stock' => 5,
            'qty' => 1,
        ]);

        [$paidOrder, $paidPayment, $paidProduct] = $this->makeOrder([
            'expired_at' => now()->subMinute(),
            'payment_status' => 'paid',
            'stock' => 5,
            'qty' => 1,
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame('pending', $futureOrder->fresh()->status);
        $this->assertSame('pending', $futurePayment->fresh()->status);
        $this->assertSame(5, (int) $futureProduct->fresh()->stock);

        $this->assertSame('processing', $paidOrder->fresh()->status);
        $this->assertSame('paid', $paidPayment->fresh()->status);
        $this->assertSame(5, (int) $paidProduct->fresh()->stock);
        $this->assertFalse((bool) $paidOrder->fresh()->stock_restored);

        $this->assertSame(0, PaymentLog::where('order_id', $futureOrder->_id)->count());
        $this->assertSame(0, PaymentLog::where('order_id', $paidOrder->_id)->count());
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{0: Order, 1: Payment, 2: Product}
     */
    private function makeOrder(array $options): array
    {
        $stock = (int) ($options['stock'] ?? 5);
        $qty = (int) ($options['qty'] ?? 1);
        $price = (float) ($options['price'] ?? 1_000_000);
        $shippingFee = 25_000.0;
        $paymentStatus = (string) ($options['payment_status'] ?? 'pending');

        $product = Product::create([
            'category' => 'gpu',
            'brand' => 'TestBrand',
            'name' => 'Produk Expiry '.uniqid(),
            'price' => $price,
            'discount_price' => 0,
            'hpp' => $price * 0.7,
            'stock' => $stock,
            'specs' => [],
        ]);

        $this->productIds[] = (string) $product->_id;

        $order = Order::create([
            'user_id' => (string) $this->customer->_id,
            'order_number' => 'ORD-EXP-'.uniqid(),
            'subtotal' => $price * $qty,
            'shipping_fee' => $shippingFee,
            'service_fee' => 0,
            'discount_amount' => 0,
            'promo_code' => null,
            'total_amount' => $price * $qty + $shippingFee,
            'status' => 'pending',
            'shipping_address' => 'Jl. Expiry No. 1, Jakarta',
            'shipping_method' => 'courier',
            'payment_method' => 'bank_transfer',
            'expired_at' => Carbon::parse($options['expired_at']),
            'stock_restored' => false,
        ]);

        $this->orderIds[] = (string) $order->_id;

        OrderItem::create([
            'order_id' => $order->_id,
            'product_id' => $product->_id,
            'source_type' => 'catalog',
            'build_id' => null,
            'build_name' => null,
            'qty' => $qty,
            'price_snapshot' => $price,
            'hpp_snapshot' => $price * 0.7,
            'price_list_snapshot' => $price,
        ]);

        $payment = Payment::create([
            'order_id' => $order->_id,
            'payment_method' => 'bank_transfer',
            'gateway_provider' => 'midtrans',
            'gateway_transaction_id' => 'TRX-'.strtoupper(uniqid()),
            'amount' => $price * $qty + $shippingFee,
            'status' => $paymentStatus,
            'paid_at' => $paymentStatus === 'paid' ? now() : null,
        ]);

        return [$order, $payment, $product];
    }
}
