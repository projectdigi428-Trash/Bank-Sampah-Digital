<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Reward;
use App\Models\User;
use App\Models\WasteType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankSampahApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_always_creates_a_nasabah(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ayu',
            'email' => 'ayu@example.test',
            'password' => 'password-rahasia',
            'password_confirmation' => 'password-rahasia',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', 'nasabah')
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('users', ['email' => 'ayu@example.test', 'role' => 'nasabah']);
    }

    public function test_invalid_input_uses_the_standard_validation_error_format(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['name', 'email']]]);
    }

    public function test_private_endpoints_require_a_valid_bearer_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'ayu@example.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ayu@example.test',
            'password' => 'kata-sandi-salah',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $member = User::factory()->create(['role' => 'nasabah']);
        $token = $this->tokenFor($member);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_deposit_and_redemption_write_auditable_ledger_entries(): void
    {
        $officer = User::factory()->create(['role' => 'petugas']);
        $member = User::factory()->create(['role' => 'nasabah']);
        $wasteType = WasteType::create([
            'name' => 'Botol PET',
            'price_per_kg' => 1000,
            'is_active' => true,
        ]);
        $reward = Reward::create([
            'name' => 'Bibit',
            'points_required' => 500,
            'stock' => 2,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->tokenFor($officer))
            ->postJson('/api/v1/setoran', [
                'nasabah_id' => $member->id,
                'jenis_sampah_id' => $wasteType->id,
                'berat_kg' => 1.250,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.total_points', 1250)
            ->assertJsonPath('data.transaction.balance_after', 1250);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $member->id,
            'type' => 'setoran',
            'amount' => 1250,
            'balance_after' => 1250,
        ]);

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/penukaran', ['hadiah_id' => $reward->id, 'jumlah' => 1])
            ->assertCreated()
            ->assertJsonPath('data.total_points', 500)
            ->assertJsonPath('data.transaction.balance_after', 750);

        $this->assertSame(750, (int) $member->transactions()->sum('amount'));
        $this->assertSame(1, $reward->fresh()->stock);
    }

    public function test_failed_redemption_does_not_change_balance_or_stock(): void
    {
        $member = User::factory()->create(['role' => 'nasabah']);
        $reward = Reward::create([
            'name' => 'Hadiah mahal',
            'points_required' => 500,
            'stock' => 1,
            'is_active' => true,
        ]);

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/penukaran', ['hadiah_id' => $reward->id, 'jumlah' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'insufficient_points');

        $this->assertSame(0, (int) $member->transactions()->sum('amount'));
        $this->assertSame(1, $reward->fresh()->stock);
        $this->assertDatabaseCount('redemptions', 0);
    }

    public function test_nasabah_cannot_create_deposits_or_read_another_members_balance(): void
    {
        $member = User::factory()->create(['role' => 'nasabah']);
        $otherMember = User::factory()->create(['role' => 'nasabah']);
        $token = $this->tokenFor($member);

        $this->withToken($token)
            ->postJson('/api/v1/setoran', [])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');

        $this->withToken($token)
            ->getJson('/api/v1/nasabah/'.$otherMember->id.'/saldo')
            ->assertForbidden();
    }

    public function test_collection_points_and_waste_types_have_a_many_to_many_relationship(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plastic = WasteType::create(['name' => 'Plastik', 'price_per_kg' => 1000, 'is_active' => true]);
        $paper = WasteType::create(['name' => 'Kertas', 'price_per_kg' => 500, 'is_active' => true]);

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/titik-setor', [
                'name' => 'Titik Utara',
                'address' => 'Jalan Merdeka',
                'jenis_sampah_ids' => [$plastic->id, $paper->id],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.waste_types');

        $this->assertDatabaseCount('collection_point_waste_type', 2);
    }

    private function tokenFor(User $user): string
    {
        $token = bin2hex(random_bytes(32));

        ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
        ]);

        return $token;
    }
}
