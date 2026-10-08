<?php

/**
 * JsonResponder.php – Central helper for JSON responses.
 *
 * All endpoints use this helper so that every response has the same
 * JSON structure and the correct Content-Type header.
 */

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    /**
     * Writes an array as JSON to the response with the given HTTP status code.
     *
     * @param ResponseInterface $response the response object to write into
     * @param int               $status   HTTP status code (e.g. 200, 201, 400, 401, 404)
     * @param array<string|int, mixed> $data payload that is encoded as JSON
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
