<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CategoryBrandProductTest extends TestCase
{
    /** @var array<int, string> */
    private array $userIds = [];

    /** @var array<int, string> */
    private array $categoryIds = [];

    /** @var array<int, string> */
    private array $brandIds = [];

    /** @var array<int, string> */
    private array $productIds = [];

    /** @var array<int, string> */
    private array $cartIds = [];

    private User $admin;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Category::query()->delete();
        Brand::query()->delete();

        $this->admin = User::create([
            'name' => 'Admin Kategori',
            'email' => 'admin-kategori-'.uniqid().'@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->userIds[] = (string) $this->admin->_id;

        $this->adminToken = $this->postJson('/api/login', [
            'email' => $this->admin->email,
            'password' => 'secret123',
        ])->json('data.token');
    }

    protected function tearDown(): void
    {
        CartItem::whereIn('cart_id', $this->cartIds)->delete();
        Cart::whereIn('_id', $this->cartIds)->delete();
        Product::whereIn('_id', $this->productIds)->delete();
        Brand::whereIn('_id', $this->brandIds)->delete();
        Category::whereIn('_id', $this->categoryIds)->delete();
        User::whereIn('_id', $this->userIds)->delete();

        parent::tearDown();
    }

    public function test_category_admin_crud_and_public_list_only_active(): void
    {
        $cpu = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/categories', [
                'name' => 'Processor',
                'slug' => 'cpu',
                'type' => 'component',
                'icon' => 'cpu',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'cpu');

        $this->categoryIds[] = $cpu->json('data.id');

        $gpu = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/categories', [
                'name' => 'Graphics Card',
                'slug' => 'gpu',
                'type' => 'component',
                'is_active' => false,
            ])
            ->assertCreated();

        $this->categoryIds[] = $gpu->json('data.id');

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'cpu');

        $this->getJson('/api/categories/cpu')
            ->assertOk()
            ->assertJsonPath('data.name', 'Processor');

        $this->getJson('/api/categories/gpu')->assertNotFound();

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/categories', [
                'name' => 'Processor Duplikat',
                'slug' => 'cpu',
                'type' => 'component',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson('/api/categories/'.$cpu->json('data.id'), [
                'name' => 'Processor & CPU',
                'slug' => 'motherboard',
            ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'motherboard');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/categories/'.$gpu->json('data.id'))
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_brand_crud_and_delete_guard(): void
    {
        $brand = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/brands', ['name' => 'TestBrand'])
            ->assertCreated();

        $this->brandIds[] = $brand->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/brands', ['name' => 'testbrand'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $product = Product::create([
            'category' => 'cpu',
            'brand' => 'TestBrand',
            'name' => 'Produk Brand Guard '.uniqid(),
            'price' => 1_000_000,
            'discount_price' => 0,
            'hpp' => 700_000,
            'stock' => 5,
            'specs' => [],
        ]);

        $this->productIds[] = (string) $product->_id;

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/brands')
            ->assertOk()
            ->assertJsonPath('data.0.product_count', 1);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/brands/'.$brand->json('data.id'))
            ->assertStatus(409);

        $product->delete();

        $promoCode = PromoCode::create([
            'code' => 'BRANDGUARD'.strtoupper(substr(uniqid(), -5)),
            'discount_percentage' => 10,
            'brands' => ['TestBrand'],
            'min_subtotal' => null,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/brands/'.$brand->json('data.id'))
            ->assertStatus(409);

        $promoCode->delete();

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/brands/'.$brand->json('data.id'))
            ->assertOk();
    }

    public function test_product_api_normalizes_category_and_hides_hpp_from_buyer(): void
    {
        $category = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/categories', [
                'name' => 'Processor',
                'slug' => 'cpu',
                'type' => 'component',
            ])
            ->assertCreated();

        $this->categoryIds[] = $category->json('data.id');

        $brand = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/brands', ['name' => 'TestBrand'])
            ->assertCreated();

        $this->brandIds[] = $brand->json('data.id');

        $product = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/products', [
                'category' => 'CPU',
                'brand' => 'TestBrand',
                'name' => 'CPU Uji HPP '.uniqid(),
                'price' => 1_000_000,
                'hpp' => 800_000,
                'stock' => 5,
                'specs' => ['socket' => 'LGA1700'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.category', 'cpu')
            ->assertJsonPath('data.hpp', 800_000)
            ->assertJsonPath('data.effective_price', 1_000_000);

        $productId = $product->json('data.id');
        $this->productIds[] = $productId;

        $this->flushHeaders();

        $this->getJson('/api/products/'.$productId)
            ->assertOk()
            ->assertJsonMissingPath('data.hpp')
            ->assertJsonPath('data.effective_price', 1_000_000);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/products/'.$productId)
            ->assertOk()
            ->assertJsonPath('data.hpp', 800_000);

        $this->flushHeaders();

        $this->getJson('/api/products?q='.urlencode($product->json('data.name')))
            ->assertOk()
            ->assertJsonMissingPath('data.0.hpp');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/products?q='.urlencode($product->json('data.name')))
            ->assertOk()
            ->assertJsonPath('data.0.hpp', 800_000);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/products', [
                'category' => 'tidak_ada',
                'brand' => 'TestBrand',
                'name' => 'Kategori Salah',
                'price' => 1_000,
                'stock' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');
    }

    public function test_product_delete_guard_when_still_in_cart(): void
    {
        $category = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/categories', [
                'name' => 'Processor',
                'slug' => 'cpu',
                'type' => 'component',
            ])
            ->assertCreated();

        $this->categoryIds[] = $category->json('data.id');

        $brand = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/brands', ['name' => 'TestBrand'])
            ->assertCreated();

        $this->brandIds[] = $brand->json('data.id');

        $product = Product::create([
            'category' => 'cpu',
            'brand' => 'TestBrand',
            'name' => 'Produk Cart Guard '.uniqid(),
            'price' => 1_000_000,
            'discount_price' => 0,
            'hpp' => 700_000,
            'stock' => 5,
            'specs' => [],
        ]);

        $this->productIds[] = (string) $product->_id;

        $cart = Cart::create([
            'user_id' => (string) $this->admin->_id,
            'total_price' => 1_000_000,
        ]);

        $this->cartIds[] = (string) $cart->_id;

        CartItem::create([
            'cart_id' => $cart->_id,
            'product_id' => $product->_id,
            'source_type' => 'catalog',
            'qty' => 1,
            'price_snapshot' => 1_000_000,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/products/'.(string) $product->_id)
            ->assertStatus(409);

        CartItem::where('cart_id', $cart->_id)->delete();

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/products/'.(string) $product->_id)
            ->assertOk();
    }
}
