<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CheckoutBuildRevalidationTest extends TestCase
{
    /** @var array<int, string> */
    private array $productIds = [];

    private User $user;

    private string $token;

    private string $cpuId;

    private string $buildId;

    protected function setUp(): void
    {
        parent::setUp();

        $cpu = $this->makeProduct('CPU', ['socket' => 'LGA1700', 'tdp' => '125W']);
        $motherboard = $this->makeProduct('Motherboard', [
            'socket' => 'LGA1700',
            'form_factor' => 'ATX',
            'memory_type' => 'DDR5',
        ]);
        $ram = $this->makeProduct('RAM', ['type' => 'DDR5']);
        $ssd = $this->makeProduct('SSD', ['capacity' => '2TB']);
        $gpu = $this->makeProduct('GPU', ['tdp' => '450W', 'length' => 336]);
        $case = $this->makeProduct('Case', ['form_factor' => 'ATX', 'max_gpu' => '365mm']);
        $psu = $this->makeProduct('PSU', ['wattage_w' => 1000]);

        $this->cpuId = (string) $cpu->_id;

        $this->user = User::create([
            'name' => 'Checkout Test',
            'email' => 'checkout-'.uniqid().'@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $this->token = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'secret123',
        ])->json('data.token');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/builder/add-to-cart', [
                'user_id' => (string) $this->user->_id,
                'cpu_id' => (string) $cpu->_id,
                'motherboard_id' => (string) $motherboard->_id,
                'ram_id' => (string) $ram->_id,
                'storage_id' => (string) $ssd->_id,
                'gpu_id' => (string) $gpu->_id,
                'case_id' => (string) $case->_id,
                'psu_id' => (string) $psu->_id,
                'build_name' => 'Rakitan Uji',
            ]);

        $response->assertCreated();

        $this->buildId = $response->json('data.build_id');
    }

    protected function tearDown(): void
    {
        Product::whereIn('_id', $this->productIds)->delete();

        $cartIds = Cart::where('user_id', (string) $this->user->_id)->pluck('_id')->all();
        CartItem::whereIn('cart_id', $cartIds)->delete();
        Cart::whereIn('_id', $cartIds)->delete();

        Order::where('user_id', (string) $this->user->_id)->delete();
        $this->user->delete();

        parent::tearDown();
    }

    public function test_build_name_is_stored_and_cart_groups_by_build(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/cart?user_id='.(string) $this->user->_id);

        $response->assertOk()
            ->assertJsonPath('data.builder_builds.0.build_id', $this->buildId)
            ->assertJsonPath('data.builder_builds.0.build_name', 'Rakitan Uji');
    }

    public function test_checkout_rejects_build_that_became_incompatible(): void
    {
        $ordersBefore = Order::count();

        Product::where('_id', $this->cpuId)->update([
            'specs' => ['socket' => 'AM5', 'tdp' => '125W'],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/orders/checkout', [
                'user_id' => (string) $this->user->_id,
                'shipping_address' => 'Jl. Uji No. 1, Jakarta',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('builds.0.build_id', $this->buildId);

        $this->assertSame($ordersBefore, Order::count());
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
            'hpp' => 800_000,
            'stock' => 10,
            'specs' => $specs,
        ]);

        $this->productIds[] = (string) $product->_id;

        return $product;
    }
}
