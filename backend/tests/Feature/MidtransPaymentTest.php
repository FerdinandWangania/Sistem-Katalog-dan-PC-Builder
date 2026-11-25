<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransPaymentTest extends TestCase
{
    /** @var array<int, string> */
    private array $userIds = [];

    /** @var array<int, string> */
    private array $productIds = [];

    /** @var array<int, string> */
    private array $orderIds = [];

    private User $admin;

    private User $finance;

    private User $customer;

    private User $otherCustomer;

    private string $adminToken;

    private string $financeToken;

    private string $customerToken;

    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'midtrans.server_key' => 'server-key',
            'midtrans.client_key' => 'client-key',
            'midtrans.is_production' => false,
            'midtrans.snap_url' => 'https://midtrans.test/snap/v1/transactions',
            'midtrans.finish_url' => 'https://frontend.test/orders/finish',
        ]);

        Http::preventStrayRequests();

        $this->admin = $this->makeUser('admin', 'Admin Midtrans');
        $this->finance = $this->makeUser('keuangan', 'Finance Midtrans');
        $this->customer = $this->makeUser('customer', 'Customer Midtrans');
        $this->otherCustomer = $this->makeUser('customer', 'Other Midtrans');

        $this->adminToken = $this->login($this->admin);
        $this->financeToken = $this->login($this->finance);
        $this->customerToken = $this->login($this->customer);
        $this->otherToken = $this->login($this->otherCustomer);
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

    public function test_snap_token_is_owner_scoped_and_posts_valid_midtrans_payload(): void
    {
        [$order, $payment] = $this->makeOrder();

        Http::fake([
            'https://midtrans.test/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-123',
                'redirect_url' => 'https://midtrans.test/pay/snap-token-123',
            ], 201),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->postJson('/api/payments/'.$order->_id.'/snap-token')
            ->assertOk()
            ->assertJsonPath('data.snap_token', 'snap-token-123')
            ->assertJsonPath('data.snap_redirect_url', 'https://midtrans.test/pay/snap-token-123')
            ->assertJsonPath('data.client_key', 'client-key');

        $payment->refresh();
        $this->assertSame('snap-token-123', $payment->snap_token);

        Http::assertSent(function (Request $request) use ($order): bool {
            $payload = $request->data();

            return $request->url() === 'https://midtrans.test/snap/v1/transactions'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('server-key:'))
                && $payload['transaction_details']['order_id'] === $order->order_number
                && $payload['transaction_details']['gross_amount'] === 1_025_000
                && $payload['enabled_payments'] === ['bank_transfer']
                && $payload['callbacks']['finish'] === 'https://frontend.test/orders/finish';
        });

        Http::assertSentCount(1);

        $this->withHeader('Authorization', 'Bearer '.$this->otherToken)
            ->postJson('/api/payments/'.$order->_id.'/snap-token')
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->postJson('/api/payments/'.$order->_id.'/snap-token')
            ->assertOk()
            ->assertJsonPath('data.snap_token', 'snap-token-123');

        Http::assertSentCount(1);
    }

    public function test_callback_requires_valid_signature_and_is_idempotent_on_paid(): void
    {
        [$order, $payment] = $this->makeOrder();

        $payload = $this->callbackPayload($order, 'settlement', '1025000.00', [
            'transaction_id' => 'trx-paid-1',
            'payment_type' => 'bank_transfer',
            'va_numbers' => [['bank' => 'bca', 'va_number' => '1234567890']],
        ]);

        $this->postJson('/api/payments/callback', [
            ...$payload,
            'signature_key' => 'signature-palsu',
        ])->assertForbidden();

        $payment->refresh();
        $this->assertSame('pending', $payment->status);

        $this->postJson('/api/payments/callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.order.status', 'processing')
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.payment.va_number', '1234567890');

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame(1, PaymentLog::where('order_id', $order->_id)->count());

        $this->postJson('/api/payments/callback', $payload)->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame(2, PaymentLog::where('order_id', $order->_id)->count());
        $this->assertSame(1_025_000.0, (float) Order::find($order->_id)->total_amount);
    }

    public function test_expire_callback_cancels_order_and_restores_stock_once(): void
    {
        [$order, $payment, $product] = $this->makeOrder([
            'price' => 1_000_000,
            'qty' => 2,
            'stock' => 8,
        ]);

        $payload = $this->callbackPayload($order, 'expire', '2025000.00', [
            'transaction_id' => 'trx-expired-1',
            'payment_type' => 'bank_transfer',
        ]);

        $this->postJson('/api/payments/callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.order.status', 'cancelled')
            ->assertJsonPath('data.payment.status', 'expired');

        $order->refresh();
        $payment->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertTrue((bool) $order->stock_restored);
        $this->assertSame('expired', $payment->status);
        $this->assertSame(10, (int) $product->fresh()->stock);

        $log = PaymentLog::where('order_id', $order->_id)->first();
        $this->assertNotNull($log);
        $this->assertSame('expired', $log->reconciled_status);

        $this->postJson('/api/payments/callback', $payload)->assertOk();
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_callback_with_amount_mismatch_is_ignored(): void
    {
        [$order, $payment] = $this->makeOrder();

        $payload = $this->callbackPayload($order, 'settlement', '999999.00', [
            'transaction_id' => 'trx-mismatch-1',
            'payment_type' => 'bank_transfer',
        ]);

        $this->postJson('/api/payments/callback', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertSame(1, PaymentLog::where('order_id', $order->_id)->count());
    }

    public function test_payment_logs_and_top_products_are_operational_read_only(): void
    {
        [$order, $payment, $product] = $this->makeOrder([
            'price' => 5_000_000,
            'qty' => 2,
            'stock' => 7,
        ]);

        $order->update(['status' => 'processing']);
        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        $log = PaymentLog::create([
            'order_id' => $order->_id,
            'payment_id' => $payment->_id,
            'gateway_transaction_id' => 'TRX-LOG-OK',
            'gateway_method' => 'bank_transfer',
            'amount' => (float) $order->total_amount,
            'gateway_status' => 'settlement',
            'reconciled_status' => 'paid',
            'received_at' => now(),
            'raw_payload' => ['source' => 'test'],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/admin/payment-logs')
            ->assertOk()
            ->assertJsonFragment(['gateway_transaction_id' => 'TRX-LOG-OK']);

        $this->withHeader('Authorization', 'Bearer '.$this->financeToken)
            ->getJson('/api/admin/payment-logs')
            ->assertOk();

        $period = now()->toDateString();

        $this->withHeader('Authorization', 'Bearer '.$this->financeToken)
            ->getJson('/api/admin/payment-logs?from='.$period.'&to='.$period.'&status=paid&method=bank&order_id='.$order->_id)
            ->assertOk()
            ->assertJsonFragment(['gateway_transaction_id' => 'TRX-LOG-OK']);

        $this->withHeader('Authorization', 'Bearer '.$this->financeToken)
            ->getJson('/api/admin/payment-logs?from='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertJsonMissing(['gateway_transaction_id' => 'TRX-LOG-OK']);

        $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->getJson('/api/admin/payment-logs')
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/admin/payment-logs/'.$log->_id)
            ->assertOk()
            ->assertJsonPath('data.order.order_number', $order->order_number)
            ->assertJsonCount(1, 'data.payment_attempts');

        $this->withHeader('Authorization', 'Bearer '.$this->financeToken)
            ->getJson('/api/admin/top-products?from='.$period.'&to='.$period.'&limit=10')
            ->assertOk()
            ->assertJsonFragment([
                'product_id' => (string) $product->_id,
                'quantity' => 2,
                'revenue' => 10_000_000,
            ]);
    }

    public function test_payment_instructions_are_owner_only_and_include_va(): void
    {
        [$order, $payment] = $this->makeOrder();

        $payment->update(['va_number' => '8808123456789']);

        $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->getJson('/api/payments/'.$order->_id.'/instructions')
            ->assertOk()
            ->assertJsonPath('data.va_number', '8808123456789')
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.instruction', 'Transfer ke nomor Virtual Account 8808123456789 sesuai nominal.');

        $this->withHeader('Authorization', 'Bearer '.$this->otherToken)
            ->getJson('/api/payments/'.$order->_id.'/instructions')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{0: Order, 1: Payment, 2: Product}
     */
    private function makeOrder(array $options = []): array
    {
        $price = (float) ($options['price'] ?? 1_000_000);
        $qty = (int) ($options['qty'] ?? 1);
        $stock = (int) ($options['stock'] ?? 8);
        $shippingFee = (float) ($options['shipping_fee'] ?? 25_000);
        $total = $price * $qty + $shippingFee;

        $product = Product::create([
            'category' => 'gpu',
            'brand' => 'TestBrand',
            'name' => 'Produk Midtrans '.uniqid(),
            'price' => $price,
            'discount_price' => 0,
            'hpp' => $price * 0.7,
            'stock' => $stock,
            'specs' => [],
        ]);

        $this->productIds[] = (string) $product->_id;

        $order = Order::create([
            'user_id' => (string) $this->customer->_id,
            'order_number' => 'ORD-MID-'.uniqid(),
            'subtotal' => $price * $qty,
            'shipping_fee' => $shippingFee,
            'service_fee' => 0,
            'discount_amount' => 0,
            'promo_code' => null,
            'total_amount' => $total,
            'status' => 'pending',
            'shipping_address' => 'Jl. Midtrans No. 1, Jakarta',
            'shipping_method' => 'courier',
            'payment_method' => 'bank_transfer',
            'expired_at' => now()->addDay(),
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
            'amount' => $total,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        return [$order, $payment, $product];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function callbackPayload(Order $order, string $transactionStatus, string $grossAmount, array $extra = []): array
    {
        $statusCode = '200';
        $signature = hash(
            'sha512',
            $order->order_number.$statusCode.$grossAmount.'server-key',
        );

        return [
            'order_id' => $order->order_number,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => $transactionStatus,
            'fraud_status' => 'accept',
            ...$extra,
        ];
    }

    private function makeUser(string $role, string $name): User
    {
        $user = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid().'@example.com',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);

        $this->userIds[] = (string) $user->_id;

        return $user;
    }

    private function login(User $user): string
    {
        return (string) $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->json('data.token');
    }
}
