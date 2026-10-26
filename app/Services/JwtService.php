<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algorithm,
        private readonly int $ttl,
        private readonly string $issuer,
    ) {}

    public function issue(User $user): string
    {
        $now = time();

        return JWT::encode([
            'iss' => $this->issuer,
            'sub' => (string) $user->getKey(),
            'iat' => $now,
            'exp' => $now + $this->ttl * 60,
        ], $this->secret, $this->algorithm);
    }

    /**
     * Returns the user id the token was issued for, or null when the token
     * is malformed, tampered with or expired.
     */
    public function subject(string $token): ?int
    {
        try {
            $claims = JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (Throwable) {
            return null;
        }

        return ctype_digit((string) ($claims->sub ?? '')) ? (int) $claims->sub : null;
    }
}
