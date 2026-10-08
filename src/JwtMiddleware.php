<?php

/**
 * JwtMiddleware.php – Protects all endpoints with a JSON Web Token.
 *
 * Rule: every request needs a valid "Authorization: Bearer <token>" header,
 * EXCEPT the authentication endpoint itself (POST /api/v1/authenticate).
 * On failure the middleware answers with 401 and a JSON body.
 */

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class JwtMiddleware implements MiddlewareInterface
{
    /** The only public endpoint – accessible without a token */
    private const PUBLIC_PATH = '/api/v1/authenticate';

    public function __construct(
        private JwtService $jwtService,
        private ResponseFactoryInterface $responseFactory = new ResponseFactory()
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // The authentication endpoint stays open for everyone
        if ($request->getUri()->getPath() === self::PUBLIC_PATH) {
            return $handler->handle($request);
        }

        $authHeader = $request->getHeaderLine('Authorization');

        // Header must have the form "Bearer <token>"
        if ($authHeader === '' || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized($request, 'missing or invalid authorization header');
        }

        $token = substr($authHeader, 7);
        $payload = $this->jwtService->verifyToken($token);

        if ($payload === null) {
            return $this->unauthorized($request, 'invalid or expired token');
        }

        // Make the verified payload available to route handlers
        $request = $request->withAttribute('jwt_payload', $payload);

        return $handler->handle($request);
    }

    /**
     * Builds a 401 response in the unified JSON error structure.
     */
    private function unauthorized(ServerRequestInterface $request, string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(401);

        // Bearer challenge as required by the HTTP standard for 401 responses
        $response = $response->withHeader('WWW-Authenticate', 'Bearer');

        return JsonResponder::send($response, 401, ['error' => $message]);
    }
}
