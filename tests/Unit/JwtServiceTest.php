<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\JwtService;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

class JwtServiceTest extends TestCase
{
    private const SECRET = 'unit-test-secret-with-enough-length';

    private function service(int $ttl = 60, string $secret = self::SECRET): JwtService
    {
        return new JwtService($secret, 'HS256', $ttl, 'http://localhost');
    }

    private function user(int $id = 7): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    public function test_issued_token_resolves_back_to_the_user_id(): void
    {
        $service = $this->service();

        $this->assertSame(7, $service->subject($service->issue($this->user(7))));
    }

    public function test_garbage_token_is_rejected(): void
    {
        $this->assertNull($this->service()->subject('not-a-jwt'));
    }

    public function test_token_signed_with_another_secret_is_rejected(): void
    {
        $token = $this->service(secret: 'another-secret-with-enough-length!!')->issue($this->user());

        $this->assertNull($this->service()->subject($token));
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = JWT::encode(['sub' => '7', 'exp' => time() - 60], self::SECRET, 'HS256');

        $this->assertNull($this->service()->subject($token));
    }
}
