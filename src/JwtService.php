<?php

/**
 * JwtService.php – Issues and verifies JSON Web Tokens (HS256).
 *
 * Uses firebase/php-jwt v7. The signing key comes from the .env file
 * (JWT_SECRET, must be at least 32 characters long for HS256) and the
 * token lifetime from JWT_TTL (seconds).
 */

declare(strict_types=1);

namespace App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class JwtService
{
    /** Signing algorithm used for all tokens */
    private const ALGORITHM = 'HS256';

    /**
     * Creates a new valid JWT for the given user.
     *
     * @param int    $userId   id of the user (goes into the "sub" claim)
     * @param string $username name of the user (custom claim)
     */
    public function issueToken(int $userId, string $username): string
    {
        $issuedAt = time();
        $ttl = (int) (Env::get('JWT_TTL', '3600') ?? '3600');

        $payload = [
            'iss'      => 'uek295-shop-api',       // issuer: who created the token
            'sub'      => (string) $userId,        // subject: the user id
            'username' => $username,               // custom claim for display purposes
            'iat'      => $issuedAt,               // issued at
            'exp'      => $issuedAt + $ttl,        // expiration time
        ];

        return JWT::encode($payload, (string) Env::get('JWT_SECRET', ''), self::ALGORITHM);
    }

    /**
     * Verifies the signature and expiration of a token.
     *
     * @param string $token the JWT from the Authorization header
     * @return array<string, mixed>|null the token payload if valid, otherwise null
     */
    public function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key((string) Env::get('JWT_SECRET', ''), self::ALGORITHM));

            // Convert the stdClass payload into an array for easier handling
            $payload = json_decode((string) json_encode($decoded), true);

            return is_array($payload) ? $payload : null;
        } catch (Throwable $e) {
            // Covers: ExpiredException, SignatureInvalidException, BeforeValidException,
            // UnexpectedValueException (malformed token), DomainException (key problems)
            return null;
        }
    }
}
