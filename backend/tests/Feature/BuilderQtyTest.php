<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Tests\TestCase;

class BuilderQtyTest extends TestCase
{
    /** @var array<int, string> */
    private array $productIds = [];

    private string $sessionId = 'qty-test-session';

    private Product $cpu;

    private Product $motherboard;

    private Product $ram;

    private Product $ssd;

    private Product $gpu;

    private Product $case;

    private Product $psu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cpu = $this->makeProduct('CPU', ['socket' => 'AM5', 'tdp' => '105W']);
        $this->motherboard = $this->makeProduct('Motherboard', [
            'socket' => 'AM5',
            'form_factor' => 'ATX',
            'memory_type' => 'DDR5',
        ]);
        $this->ram = $this->makeProduct('RAM', ['type' => 'DDR5']);
        $this->ssd = $this->makeProduct('SSD', ['capacity' => '1TB']);
        $this->gpu = $this->makeProduct('GPU', ['tdp' => '115W', 'length' => 240]);
        $this->case = $this->makeProduct('Case', ['form_factor' => 'ATX', 'max_gpu_length' => 410]);
        $this->psu = $this->makeProduct('PSU', ['wattage_w' => 1000]);
    }

    protected function tearDown(): void
    {
        Product::whereIn('_id', $this->productIds)->delete();

        // HAPUS via model (bukan pluck('_id')) agar kompatibel driver Mongo.
        foreach (Cart::where('session_id', $this->sessionId)->get() as $cart) {
            CartItem::where('cart_id', $cart->_id)->delete();
            $cart->delete();
        }
        foreach (Order::where('session_id', $this->sessionId)->get() as $order) {
            OrderItem::where('order_id', $order->_id)->delete();
            $order->delete();
        }

        parent::tearDown();
    }

    public function test_quantities_scale_grand_total_and_watt(): void
    {
        $response = $this->postJson('/api/builder/validate', $this->payload([
            (string) $this->ram->_id => 3,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.is_compatible', true)
            // 6 slot x 1jt + RAM 3 x 1jt
            ->assertJsonPath('data.grand_total', 9_000_000)
            ->assertJsonPath('data.power_analysis.ram_watt', 45)
            ->assertJsonPath('data.quantities.'.$this->ram->_id, 3);
    }

    public function test_add_to_cart_stores_qty_per_item(): void
    {
        $response = $this->postJson('/api/builder/add-to-cart', $this->payload([
            (string) $this->ram->_id => 3,
        ]) + ['session_id' => $this->sessionId]);

        $response->assertCreated();

        $buildId = $response->json('data.build_id');
        $items = CartItem::where('build_id', $buildId)->get();

        $this->assertCount(7, $items);
        $this->assertSame(3, (int) $items->firstWhere('product_id', (string) $this->ram->_id)->qty);
        $this->assertSame(1, (int) $items->firstWhere('product_id', (string) $this->cpu->_id)->qty);
        $this->assertSame(9_000_000.0, (float) $response->json('data.total_build'));
    }

    public function test_insufficient_stock_is_rejected_before_cart_write(): void
    {
        $this->ram->update(['stock' => 2]);

        $response = $this->postJson('/api/builder/add-to-cart', $this->payload([
            (string) $this->ram->_id => 3,
        ]) + ['session_id' => $this->sessionId]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'tidak mencukupi',
            implode(' ', $response->json('errors') ?? []),
        );
        // Gagal sebelum cart tersentuh: tidak ada cart yang tercipta.
        $this->assertSame(0, Cart::where('session_id', $this->sessionId)->count());
    }

    public function test_checkout_decrements_stock_by_qty(): void
    {
        $this->postJson('/api/builder/add-to-cart', $this->payload([
            (string) $this->ram->_id => 3,
        ]) + ['session_id' => $this->sessionId])->assertCreated();

        $response = $this->postJson('/api/orders/checkout', [
            'session_id' => $this->sessionId,
            'shipping_address' => 'Jl. Qty No. 3, Jakarta',
        ]);

        $response->assertCreated();
        $this->assertSame(7, (int) $this->ram->fresh()->stock);
        $this->assertSame(3, (int) OrderItem::where('product_id', (string) $this->ram->_id)->first()->qty);
    }

    public function test_backward_compatible_without_quantities(): void
    {
        $response = $this->postJson('/api/builder/validate', $this->payload());

        $response->assertOk()
            ->assertJsonPath('data.is_compatible', true)
            ->assertJsonPath('data.grand_total', 7_000_000);
    }

    /**
     * @param  array<string, int>  $quantities
     * @return array<string, mixed>
     */
    private function payload(array $quantities = []): array
    {
        $body = [
            'cpu_id' => (string) $this->cpu->_id,
            'motherboard_id' => (string) $this->motherboard->_id,
            'ram_id' => (string) $this->ram->_id,
            'storage_id' => (string) $this->ssd->_id,
            'gpu_id' => (string) $this->gpu->_id,
            'case_id' => (string) $this->case->_id,
            'psu_id' => (string) $this->psu->_id,
        ];

        if ($quantities !== []) {
            $body['quantities'] = $quantities;
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $specs
     */
    private function makeProduct(string $category, array $specs): Product
    {
        $product = Product::create([
            'category' => $category,
            'brand' => 'Test',
            'name' => 'Test '.$category.' '.uniqid(),
            'price' => 1_000_000,
            'discount_price' => 0,
            'stock' => 10,
            'specs' => $specs,
        ]);

        $this->productIds[] = (string) $product->_id;

        return $product;
    }
}
