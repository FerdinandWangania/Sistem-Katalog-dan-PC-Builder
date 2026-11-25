<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    /** @var array<int, string> */
    private array $userIds = [];

    /** @var array<int, string> */
    private array $productIds = [];

    /** @var array<int, string> */
    private array $orderIds = [];

    /** @var array<int, string> */
    private array $cartIds = [];

    /** @var array<int, string> */
    private array $addressIds = [];

    private User $customer;

    private User $other;

    private string $customerToken;

    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = $this->makeUser('Customer Checkout');
        $this->other = $this->makeUser('Other Checkout');

        $this->customerToken = $this->login($this->customer);
        $this->otherToken = $this->login($this->other);
    }

    protected function tearDown(): void
    {
        Payment::whereIn('order_id', $this->orderIds)->delete();
        OrderItem::whereIn('order_id', $this->orderIds)->delete();
        Order::whereIn('_id', $this->orderIds)->delete();

        CartItem::whereIn('cart_id', $this->cartIds)->delete();
        Cart::whereIn('_id', $this->cartIds)->delete();
        Cart::whereIn('user_id', $this->userIds)->delete();

        Address::whereIn('_id', $this->addressIds)->delete();
        Product::whereIn('_id', $this->productIds)->delete();
        User::whereIn('_id', $this->userIds)->delete();

        parent::tearDown();
    }

    public function test_qris_above_ten_million_is_rejected_without_touching_stock(): void
    {
        $product = $this->makeProduct(11_000_000, 3);
        $this->addToCart($this->customer, $this->customerToken, $product);

        $before = Order::where('user_id', (string) $this->customer->_id)->count();

        $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->postJson('/api/orders/checkout', [
                'user_id' => (string) $this->customer->_id,
                'shipping_address' => 'Jl. QRIS No. 1, Jakarta',
                'payment_method' => 'qris',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->assertSame($before, Order::where('user_id', (string) $this->customer->_id)->count());
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(1, CartItem::where('product_id', (string) $product->_id)->count());
    }

    public function test_credit_card_fee_and_pickup_shipping_are_calculated_server_side(): void
    {
        $product = $this->makeProduct(1_000_000, 5);
        $this->addToCart($this->customer, $this->customerToken, $product);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->postJson('/api/orders/checkout', [
                'user_id' => (string) $this->customer->_id,
                'shipping_address' => 'Ambil di toko',
                'shipping_method' => 'pickup',
                'payment_method' => 'credit_card',
            ])
            ->assertCreated()
            ->assertJsonPath('data.order.subtotal', 1_000_000)
            ->assertJsonPath('data.order.shipping_fee', 0)
            ->assertJsonPath('data.order.service_fee', 29_000)
            ->assertJsonPath('data.order.total_amount', 1_029_000)
            ->assertJsonPath('data.payment.amount', 1_029_000);

        $this->orderIds[] = $response->json('data.order.id');
        $this->assertSame(4, (int) $product->fresh()->stock);
    }

    public function test_address_id_must_belong_to_buyer_and_is_composed_into_shipping_address(): void
    {
        $product = $this->makeProduct(1_000_000, 5);

        $address = $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->postJson('/api/addresses', [
                'label' => 'Rumah',
                'recipient' => 'Penerima Rumah',
                'phone' => '08123456789',
                'address' => 'Jl. Alamat No. 9',
                'city' => 'Bandung',
            ])
            ->assertCreated();

        $addressId = $address->json('data.id');
        $this->addressIds[] = $addressId;

        $this->addToCart($this->other, $this->otherToken, $product);

        $this->withHeader('Authorization', 'Bearer '.$this->otherToken)
            ->postJson('/api/orders/checkout', [
                'user_id' => (string) $this->other->_id,
                'address_id' => $addressId,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('address_id');

        $this->assertSame(5, (int) $product->fresh()->stock);

        $this->addToCart($this->customer, $this->customerToken, $product);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->postJson('/api/orders/checkout', [
                'user_id' => (string) $this->customer->_id,
                'address_id' => $addressId,
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.order.shipping_address',
                'Penerima Rumah - 08123456789 - Jl. Alamat No. 9, Bandung',
            )
            ->assertJsonPath('data.order.address_id', $addressId);

        $this->orderIds[] = $response->json('data.order.id');
        $this->assertSame(4, (int) $product->fresh()->stock);
    }

    public function test_cart_item_cannot_be_removed_by_another_user(): void
    {
        $product = $this->makeProduct(1_000_000, 5);
        $this->addToCart($this->customer, $this->customerToken, $product);

        $item = CartItem::where('product_id', (string) $product->_id)->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$this->otherToken)
            ->deleteJson('/api/cart/items/'.$item->_id)
            ->assertForbidden();

        $this->assertNotNull($item->fresh());

        $this->withHeader('Authorization', 'Bearer '.$this->customerToken)
            ->deleteJson('/api/cart/items/'.$item->_id)
            ->assertOk();

        $this->assertNull($item->fresh());
    }

    private function makeUser(string $name): User
    {
        $user = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid().'@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'customer',
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

    private function makeProduct(float $price, int $stock): Product
    {
        $product = Product::create([
            'category' => 'gpu',
            'brand' => 'TestBrand',
            'name' => 'Produk Checkout '.uniqid(),
            'price' => $price,
            'discount_price' => 0,
            'hpp' => $price * 0.7,
            'stock' => $stock,
            'specs' => [],
        ]);

        $this->productIds[] = (string) $product->_id;

        return $product;
    }

    private function addToCart(User $user, string $token, Product $product, int $qty = 1): void
    {
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/cart/items', [
                'user_id' => (string) $user->_id,
                'product_id' => (string) $product->_id,
                'qty' => $qty,
            ])
            ->assertCreated();

        $cart = Cart::where('user_id', (string) $user->_id)->first();

        if ($cart) {
            $this->cartIds[] = (string) $cart->_id;
        }
    }
}
