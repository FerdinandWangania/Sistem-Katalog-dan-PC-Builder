<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Promotion;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PromotionAndPromoCodeTest extends TestCase
{
    /** @var array<int, string> */
    private array $userIds = [];

    /** @var array<int, string> */
    private array $productIds = [];

    /** @var array<int, string> */
    private array $promotionIds = [];

    /** @var array<int, string> */
    private array $promoCodeIds = [];

    /** @var array<int, string> */
    private array $orderIds = [];

    /** @var array<int, string> */
    private array $cartIds = [];

    private User $admin;

    private User $customer;

    private string $adminToken;

    private string $customerToken;

    private Product $gpu;

    private Product $cpu;

    private Product $gpuManual;

    protected function setUp(): void
    {
        parent::setUp();

        Promotion::query()->delete();
        PromoCode::query()->delete();

        $this->admin = $this->makeUser('admin', 'Admin Promo');
        $this->customer = $this->makeUser('customer', 'Pembeli Promo');

        $this->adminToken = $this->login($this->admin);
        $this->customerToken = $this->login($this->customer);

        $this->gpu = $this->makeProduct('gpu', 'NVIDIA', 1_000_000, 0);
        $this->cpu = $this->makeProduct('cpu', 'Intel', 2_000_000, 0);
        $this->gpuManual = $this->makeProduct('gpu', 'NVIDIA', 1_500_000, 1_200_000);

        app(PromotionService::class)->flush();
    }

    protected function tearDown(): void
    {
        OrderItem::whereIn('order_id', $this->orderIds)->delete();
        Payment::whereIn('order_id', $this->orderIds)->delete();
        Order::whereIn('_id', $this->orderIds)->delete();

        CartItem::whereIn('cart_id', $this->cartIds)->delete();
        Cart::whereIn('_id', $this->cartIds)->delete();
        Cart::whereIn('user_id', $this->userIds)->delete();

        PromoCode::whereIn('_id', $this->promoCodeIds)->delete();
        Promotion::whereIn('_id', $this->promotionIds)->delete();
        Product::whereIn('_id', $this->productIds)->delete();
        User::whereIn('_id', $this->userIds)->delete();

        app(PromotionService::class)->flush();

        parent::tearDown();
    }

    public function test_promo_code_validation_and_checkout_allocate_only_eligible_brand(): void
    {
        $promoCode = PromoCode::create([
            'code' => 'GAMING15',
            'discount_percentage' => 15.0,
            'brands' => ['NVIDIA'],
            'min_subtotal' => 0,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->promoCodeIds[] = (string) $promoCode->_id;

        $this->addToCart($this->gpu, 1);
        $this->addToCart($this->cpu, 1);

        $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->postJson('/api/promo-codes/validate', [
                'user_id' => (string) $this->customer->_id,
                'code' => 'gaming15',
            ])
            ->assertOk()
            ->assertJsonPath('data.discount_amount', 150_000)
            ->assertJsonPath('data.total_after_discount', 2_850_000)
            ->assertJsonPath('data.items.0.eligible', true)
            ->assertJsonPath('data.items.0.discount_amount', 150_000)
            ->assertJsonPath('data.items.1.eligible', false)
            ->assertJsonPath('data.items.1.discount_amount', 0);

        $checkout = $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->postJson('/api/orders/checkout', [
                'user_id' => (string) $this->customer->_id,
                'shipping_address' => 'Jl. Promo No. 1, Jakarta',
                'promo_code' => 'GAMING15',
            ])
            ->assertCreated()
            ->assertJsonPath('data.order.subtotal', 2_850_000)
            ->assertJsonPath('data.order.discount_amount', 150_000)
            ->assertJsonPath('data.order.shipping_fee', 25_000)
            ->assertJsonPath('data.order.total_amount', 2_875_000)
            ->assertJsonPath('data.order.promo_code', 'GAMING15')
            ->assertJsonPath('data.payment.amount', 2_875_000);

        $orderId = $checkout->json('data.order.id');
        $this->orderIds[] = $orderId;

        $prices = OrderItem::where('order_id', $orderId)
            ->get()
            ->mapWithKeys(static fn (OrderItem $item): array => [(string) $item->product_id => (float) $item->price_snapshot]);

        $this->assertSame(850_000.0, $prices[(string) $this->gpu->_id]);
        $this->assertSame(2_000_000.0, $prices[(string) $this->cpu->_id]);

        $this->assertSame(9, (int) $this->gpu->fresh()->stock);
        $this->assertSame(9, (int) $this->cpu->fresh()->stock);
    }

    public function test_promo_code_min_subtotal_and_inactive_are_rejected(): void
    {
        $minimumCode = PromoCode::create([
            'code' => 'MINIM3JT',
            'discount_percentage' => 10.0,
            'brands' => null,
            'min_subtotal' => 3_000_000,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->promoCodeIds[] = (string) $minimumCode->_id;

        $this->addToCart($this->gpu, 1);

        $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->postJson('/api/promo-codes/validate', [
                'code' => 'MINIM3JT',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $inactive = PromoCode::create([
            'code' => 'NONAKTIF',
            'discount_percentage' => 10.0,
            'brands' => null,
            'min_subtotal' => null,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'is_active' => false,
        ]);

        $this->promoCodeIds[] = (string) $inactive->_id;

        $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->postJson('/api/promo-codes/validate', [
                'code' => 'NONAKTIF',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_manual_discount_conflict_requires_choice_and_controls_product_pricing(): void
    {
        $basePayload = [
            'title' => 'Promo GPU NVIDIA',
            'banner_image' => 'https://example.com/promo-nvidia.png',
            'click_target_type' => 'brand',
            'click_target_value' => 'NVIDIA',
            'brands' => ['NVIDIA'],
            'discount_percentage' => 25,
            'start_date' => now()->subDay()->toIso8601String(),
            'end_date' => now()->addDay()->toIso8601String(),
        ];

        $conflict = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/promotions', $basePayload)
            ->assertStatus(422)
            ->assertJsonPath('data.manual_discount_conflicts.0.product_id', (string) $this->gpuManual->_id)
            ->assertJsonPath('data.manual_discount_conflicts.0.nominal_before', 1_200_000)
            ->assertJsonPath('data.manual_discount_conflicts.0.nominal_after', 1_125_000);

        $this->assertCount(1, $conflict->json('data.manual_discount_conflicts'));

        $created = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/promotions', [
                ...$basePayload,
                'manual_discount_action' => 'skip',
            ])
            ->assertCreated()
            ->assertJsonPath('data.override_manual_discount', false)
            ->assertJsonFragment([
                'excluded_product_ids' => [(string) $this->gpuManual->_id],
            ]);

        $promotionId = $created->json('data.id');
        $this->promotionIds[] = $promotionId;

        $this->getJson('/api/promotions/'.$promotionId.'/products')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.products.0.id', (string) $this->gpu->_id)
            ->assertJsonPath('data.products.0.promo_price', 750_000);

        $this->getJson('/api/products/'.(string) $this->gpu->_id)
            ->assertOk()
            ->assertJsonPath('data.effective_price', 750_000)
            ->assertJsonPath('data.promo_discount_percentage', 25);

        $this->getJson('/api/products/'.(string) $this->gpuManual->_id)
            ->assertOk()
            ->assertJsonPath('data.effective_price', 1_200_000)
            ->assertJsonPath('data.promo_discount_percentage', 0);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson('/api/promotions/'.$promotionId, [
                ...$basePayload,
                'discount_percentage' => 30,
                'manual_discount_action' => 'override',
            ])
            ->assertOk()
            ->assertJsonPath('data.override_manual_discount', true)
            ->assertJsonFragment(['excluded_product_ids' => []]);

        $this->getJson('/api/promotions/'.$promotionId.'/products')
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->getJson('/api/products/'.(string) $this->gpuManual->_id)
            ->assertOk()
            ->assertJsonPath('data.effective_price', 1_050_000)
            ->assertJsonPath('data.promo_discount_percentage', 30);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson('/api/promotions/'.$promotionId, ['is_active' => false])
            ->assertOk();

        $this->getJson('/api/promotions')
            ->assertOk()
            ->assertJsonMissing(['id' => $promotionId]);

        $this->getJson('/api/products/'.(string) $this->gpu->_id)
            ->assertOk()
            ->assertJsonPath('data.effective_price', 1_000_000);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/promotions', [
                ...$basePayload,
                'banner_image' => null,
                'manual_discount_action' => 'skip',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('banner_image');
    }

    public function test_promo_code_admin_crud_and_case_insensitive_duplicate(): void
    {
        $created = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/promo-codes', [
                'code' => ' harbolnas10 ',
                'discount_percentage' => 10,
                'start_date' => now()->subDay()->toIso8601String(),
                'end_date' => now()->addDay()->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'HARBOLNAS10');

        $codeId = $created->json('data.id');
        $this->promoCodeIds[] = $codeId;

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/promo-codes', [
                'code' => 'harbolnas10',
                'discount_percentage' => 5,
                'start_date' => now()->subDay()->toIso8601String(),
                'end_date' => now()->addDay()->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson('/api/promo-codes/'.$codeId, [
                'discount_percentage' => 12.5,
                'brands' => ['NVIDIA'],
            ])
            ->assertOk()
            ->assertJsonPath('data.discount_percentage', 12.5)
            ->assertJsonPath('data.brands.0', 'NVIDIA');

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/admin/promo-codes')
            ->assertOk()
            ->assertJsonFragment(['code' => 'HARBOLNAS10']);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/promo-codes/'.$codeId)
            ->assertOk();
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

    private function makeProduct(string $category, string $brand, float $price, float $discountPrice): Product
    {
        $product = Product::create([
            'category' => $category,
            'brand' => $brand,
            'name' => strtoupper($category).' '.$brand.' '.uniqid(),
            'price' => $price,
            'discount_price' => $discountPrice,
            'hpp' => $price * 0.7,
            'stock' => 10,
            'specs' => [],
        ]);

        $this->productIds[] = (string) $product->_id;

        return $product;
    }

    private function addToCart(Product $product, int $qty): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->postJson('/api/cart/items', [
                'user_id' => (string) $this->customer->_id,
                'product_id' => (string) $product->_id,
                'qty' => $qty,
            ])
            ->assertCreated();

        $cart = Cart::where('user_id', (string) $this->customer->_id)->first();

        if ($cart) {
            $this->cartIds[] = (string) $cart->_id;
        }
    }
}
