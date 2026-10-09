<?php

declare(strict_types=1);

namespace App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class JwtService
{
    private const ALGORITHM = 'HS256';

    /**
     * Issue JWT token for a user.
     */
    public function issueToken(int $userId, string $username): string
    {
        $issuedAt = time();
        $ttl = (int) (Env::get('JWT_TTL', '3600') ?? '3600');

        $payload = [
            'iss'      => 'uek295-shop-api',
            'sub'      => (string) $userId,
            'username' => $username,
            'iat'      => $issuedAt,
            'exp'      => $issuedAt + $ttl,
        ];

        return JWT::encode($payload, (string) Env::get('JWT_SECRET', ''), self::ALGORITHM);
    }

    /**
     * Verify token signature and expiration.
     */
    public function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key((string) Env::get('JWT_SECRET', ''), self::ALGORITHM));
            $payload = json_decode((string) json_encode($decoded), true);

            return is_array($payload) ? $payload : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}