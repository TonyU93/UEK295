<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    /**
     * Send JSON response.
     */
    public static function send(ResponseInterface $response, int $status, array $data): ResponseInterface
    {
        $response->getBody()->write(
            (string) json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES)
        );

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}