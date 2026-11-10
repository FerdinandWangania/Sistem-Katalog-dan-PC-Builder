<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    /** @var array<int, string> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        User::whereIn('_id', $this->userIds)->delete();
        Product::where('brand', 'Test')->delete();
        parent::tearDown();
    }

    public function test_keuangan_can_read_finance_but_not_write_products(): void
    {
        $token = $this->tokenFor('keuangan');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/finance/profit-loss')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/stats')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/products', [
                'category' => 'CPU',
                'brand' => 'Test',
                'name' => 'Tidak boleh',
                'price' => 1000,
                'stock' => 1,
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_open_finance_or_manage_accounts(): void
    {
        $token = $this->tokenFor('admin');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/finance/profit-loss')
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users', [
                'name' => 'Baru',
                'email' => 'baru-'.uniqid().'@example.com',
                'role' => 'admin',
            ])
            ->assertForbidden();
    }

    public function test_superadmin_can_create_account_and_change_role(): void
    {
        $token = $this->tokenFor('superadmin');
        $email = 'akun-'.uniqid().'@example.com';

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users', [
                'name' => 'Akun Baru',
                'email' => $email,
                'role' => 'admin',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', 'admin');

        $this->assertNotEmpty($response->json('data.temporary_password'));
        $this->assertTrue($response->json('data.user.must_change_password'));

        $created = User::where('email', $email)->first();
        $this->userIds[] = (string) $created->_id;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/users/'.$created->_id.'/role', ['role' => 'keuangan'])
            ->assertOk()
            ->assertJsonPath('data.role', 'keuangan');
    }

    public function test_superadmin_cannot_demote_self(): void
    {
        $superadmin = $this->makeUser('superadmin');
        $token = $this->loginToken($superadmin);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/users/'.$superadmin->_id.'/role', ['role' => 'admin'])
            ->assertStatus(422);
    }

    private function tokenFor(string $role): string
    {
        return $this->loginToken($this->makeUser($role));
    }

    private function makeUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role).' Test',
            'email' => $role.'-'.uniqid().'@example.com',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);

        $this->userIds[] = (string) $user->_id;

        return $user;
    }

    private function loginToken(User $user): string
    {
        return $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->json('data.token');
    }
}
