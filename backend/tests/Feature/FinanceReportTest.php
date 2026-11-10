<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinanceReportTest extends TestCase
{
    private User $financeUser;

    private Product $product;

    private Order $order;

    private string $token;

    private string $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financeUser = User::create([
            'name' => 'Finance Test',
            'email' => 'finance-'.uniqid().'@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'keuangan',
            'email_verified_at' => now(),
        ]);

        $this->token = $this->postJson('/api/login', [
            'email' => $this->financeUser->email,
            'password' => 'secret123',
        ])->json('data.token');

        $this->product = Product::create([
            'category' => 'CPU',
            'brand' => 'TestBrand',
            'name' => 'Produk Finance '.uniqid(),
            'price' => 1_000_000,
            'discount_price' => 900_000,
            'hpp' => 600_000,
            'stock' => 10,
            'specs' => [],
        ]);

        $this->order = Order::create([
            'user_id' => (string) $this->financeUser->_id,
            'order_number' => 'ORD-TEST-'.uniqid(),
            'subtotal' => 900_000,
            'shipping_fee' => 25_000,
            'total_amount' => 1_025_000,
            'status' => 'processing',
            'shipping_address' => 'Jl. Finance No. 1',
        ]);

        OrderItem::create([
            'order_id' => (string) $this->order->_id,
            'product_id' => (string) $this->product->_id,
            'source_type' => 'catalog',
            'qty' => 1,
            'price_snapshot' => 900_000,
            'price_list_snapshot' => 1_000_000,
            'hpp_snapshot' => 600_000,
        ]);

        Payment::create([
            'order_id' => (string) $this->order->_id,
            'gateway_provider' => 'midtrans',
            'gateway_transaction_id' => 'TRX-TEST-'.uniqid(),
            'amount' => 1_025_000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->period = now()->toDateString();
    }

    protected function tearDown(): void
    {
        OrderItem::where('order_id', (string) $this->order->_id)->delete();
        Payment::where('order_id', (string) $this->order->_id)->delete();
        Order::where('_id', (string) $this->order->_id)->delete();
        $this->product->delete();
        $this->financeUser->delete();

        parent::tearDown();
    }

    public function test_profit_loss_report(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/admin/finance/profit-loss?from={$this->period}&to={$this->period}");

        $response->assertOk()
            ->assertJsonPath('data.revenue_gross', 1_000_000)
            ->assertJsonPath('data.discount_total', 100_000)
            ->assertJsonPath('data.revenue_net', 900_000)
            ->assertJsonPath('data.cogs', 600_000)
            ->assertJsonPath('data.profit', 300_000)
            ->assertJsonPath('data.profit_margin_percent', 33.33);
    }

    public function test_tax_report_uses_inclusive_ppn(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/admin/finance/tax?from={$this->period}&to={$this->period}");

        $response->assertOk()
            ->assertJsonPath('data.tax_rate_percent', 11)
            ->assertJsonPath('data.gross_amount', 1_025_000)
            ->assertJsonPath('data.dpp', 923_423.42)
            ->assertJsonPath('data.ppn', 101_576.58);
    }

    public function test_margin_report_per_category(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/admin/finance/margin?group_by=category&from={$this->period}&to={$this->period}");

        $response->assertOk()
            ->assertJsonPath('data.group_by', 'category')
            ->assertJsonPath('data.rows.0.label', 'CPU')
            ->assertJsonPath('data.rows.0.revenue', 900_000)
            ->assertJsonPath('data.rows.0.cogs', 600_000)
            ->assertJsonPath('data.rows.0.profit', 300_000)
            ->assertJsonPath('data.rows.0.margin_percent', 33.33);
    }

    public function test_report_can_be_exported_as_csv(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->get("/api/admin/finance/profit-loss?from={$this->period}&to={$this->period}&format=csv");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Metrik', $response->streamedContent());
    }
}
