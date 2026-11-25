<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AddressReviewTest extends TestCase
{
    /** @var array<int, string> */
    private array $userIds = [];

    /** @var array<int, string> */
    private array $addressIds = [];

    /** @var array<int, string> */
    private array $reviewIds = [];

    /** @var array<int, string> */
    private array $productIds = [];

    /** @var array<int, string> */
    private array $sessionIds = [];

    private User $customerA;

    private User $customerB;

    private User $admin;

    private string $tokenA;

    private string $tokenB;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerA = $this->makeUser('customer', 'Alamat A');
        $this->customerB = $this->makeUser('customer', 'Alamat B');
        $this->admin = $this->makeUser('admin', 'Admin Ulasan');

        $this->tokenA = $this->login($this->customerA);
        $this->tokenB = $this->login($this->customerB);
        $this->adminToken = $this->login($this->admin);
    }

    protected function tearDown(): void
    {
        Review::whereIn('_id', $this->reviewIds)->delete();
        Address::whereIn('_id', $this->addressIds)->delete();
        Product::whereIn('_id', $this->productIds)->delete();
        User::whereIn('_id', $this->userIds)->delete();

        parent::tearDown();
    }

    public function test_first_address_is_default_and_limit_is_ten(): void
    {
        $first = $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
            ->postJson('/api/addresses', $this->addressPayload('Rumah'))
            ->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $this->addressIds[] = $first->json('data.id');

        $second = $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
            ->postJson('/api/addresses', [
                ...$this->addressPayload('Kantor'),
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $this->addressIds[] = $second->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
            ->getJson('/api/addresses')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', 'Kantor')
            ->assertJsonPath('data.0.is_default', true)
            ->assertJsonPath('data.1.is_default', false);

        for ($i = 3; $i <= 10; $i++) {
            $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
                ->postJson('/api/addresses', $this->addressPayload('Alamat '.$i))
                ->assertCreated();

            $this->addressIds[] = $response->json('data.id');
        }

        $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
            ->postJson('/api/addresses', $this->addressPayload('Alamat 11'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('address');
    }

    public function test_address_ownership_blocks_other_user_and_supports_guest_session(): void
    {
        $address = $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
            ->postJson('/api/addresses', $this->addressPayload('Rumah'))
            ->assertCreated();

        $addressId = $address->json('data.id');
        $this->addressIds[] = $addressId;

        $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->putJson('/api/addresses/'.$addressId, ['label' => 'Dibajak'])
            ->assertNotFound();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->deleteJson('/api/addresses/'.$addressId)
            ->assertNotFound();

        $this->flushHeaders();

        $sessionId = 'guest-'.uniqid();
        $this->sessionIds[] = $sessionId;

        $guestAddress = $this->postJson('/api/addresses', [
            ...$this->addressPayload('Guest'),
            'session_id' => $sessionId,
        ])->assertCreated();

        $guestAddressId = $guestAddress->json('data.id');
        $this->addressIds[] = $guestAddressId;

        $this->getJson('/api/addresses?session_id='.$sessionId)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/addresses?session_id=session-lain')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->putJson('/api/addresses/'.$guestAddressId.'?session_id=session-lain', ['label' => 'Bukan'])
            ->assertNotFound();
    }

    public function test_review_is_unique_per_user_and_updates_product_rating(): void
    {
        $product = $this->makeProduct();
        $productId = (string) $product->_id;

        $reviewA = $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
            ->postJson('/api/reviews', [
                'product_id' => $productId,
                'rating' => 5,
                'comment' => 'Sangat cepat dan dingin.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5);

        $this->reviewIds[] = $reviewA->json('data.id');

        $product->refresh();
        $this->assertSame(5.0, (float) $product->rating);
        $this->assertSame(1, (int) $product->review_count);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
            ->postJson('/api/reviews', [
                'product_id' => $productId,
                'rating' => 4,
                'comment' => 'Ulasan kedua.',
            ])
            ->assertStatus(422);

        $reviewB = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->postJson('/api/reviews', [
                'product_id' => $productId,
                'rating' => 3,
                'comment' => 'Cukup.',
            ])
            ->assertCreated();

        $this->reviewIds[] = $reviewB->json('data.id');

        $product->refresh();
        $this->assertSame(4.0, (float) $product->rating);
        $this->assertSame(2, (int) $product->review_count);

        $this->getJson('/api/reviews?product_id='.$productId)
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.product_name', $product->name);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/reviews/'.$reviewA->json('data.id'))
            ->assertOk();

        $product->refresh();
        $this->assertSame(3.0, (float) $product->rating);
        $this->assertSame(1, (int) $product->review_count);

        $this->flushHeaders();

        $this->postJson('/api/reviews', [
            'product_id' => $productId,
            'rating' => 5,
            'comment' => 'Tamu tidak boleh.',
        ])->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->postJson('/api/reviews', [
                'product_id' => $productId,
                'rating' => 6,
                'comment' => 'Rating di luar batas.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
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

    private function makeProduct(): Product
    {
        $product = Product::create([
            'category' => 'cpu',
            'brand' => 'TestBrand',
            'name' => 'CPU Ulasan '.uniqid(),
            'price' => 2_000_000,
            'discount_price' => 0,
            'hpp' => 1_500_000,
            'stock' => 5,
            'rating' => 0,
            'review_count' => 0,
            'specs' => [],
        ]);

        $this->productIds[] = (string) $product->_id;

        return $product;
    }

    /**
     * @return array<string, string>
     */
    private function addressPayload(string $label): array
    {
        return [
            'label' => $label,
            'recipient' => 'Penerima '.$label,
            'phone' => '08123456789',
            'address' => 'Jl. Uji No. 1',
            'city' => 'Jakarta',
        ];
    }
}
