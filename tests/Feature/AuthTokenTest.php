<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_bearer_jwt(): void
    {
        $user = User::factory()->create(['username' => 'lohgarra', 'password' => 'rwaaaaawr']);

        $response = $this->postJson('/auth/token', ['username' => 'lohgarra', 'password' => 'rwaaaaawr'])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type'])
            ->assertJsonPath('token_type', 'bearer');

        $this->assertSame($user->id, app(JwtService::class)->subject($response->json('access_token')));
    }

    public function test_wrong_password_returns_401(): void
    {
        User::factory()->create(['username' => 'lohgarra', 'password' => 'rwaaaaawr']);

        $this->postJson('/auth/token', ['username' => 'lohgarra', 'password' => 'nope'])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'unauthorized');
    }

    public function test_unknown_user_returns_401(): void
    {
        $this->postJson('/auth/token', ['username' => 'ghost', 'password' => 'whatever'])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'unauthorized');
    }

    public function test_missing_fields_return_400(): void
    {
        $this->postJson('/auth/token', ['username' => 'lohgarra'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonPath('details.field', 'password')
            ->assertJsonPath('details.code', 'required');
    }
}
