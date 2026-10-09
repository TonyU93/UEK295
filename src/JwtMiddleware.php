<?php

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
    private const PUBLIC_PATHS = [
        '/api/v1/authenticate',
        '/docs',
        '/swagger.php',
    ];

    public function __construct(
        private JwtService $jwtService,
        private ResponseFactoryInterface $responseFactory = new ResponseFactory()
    ) {
    }

    /**
     * Authenticate request via JWT token.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = '/' . ltrim($request->getUri()->getPath(), '/');

        // Allow public routes
        foreach (self::PUBLIC_PATHS as $publicPath) {
            if ($path === $publicPath || str_starts_with($path, $publicPath . '/')) {
                return $handler->handle($request);
            }
        }

        $authHeader = $request->getHeaderLine('Authorization');

        if ($authHeader === '' || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized($request, 'missing or invalid authorization header');
        }

        $token = substr($authHeader, 7);
        $payload = $this->jwtService->verifyToken($token);

        if ($payload === null) {
            return $this->unauthorized($request, 'invalid or expired token');
        }

        $request = $request->withAttribute('jwt_payload', $payload);

        return $handler->handle($request);
    }

    /**
     * Build 401 unauthorized response.
     */
    private function unauthorized(ServerRequestInterface $request, string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(401);
        $response = $response->withHeader('WWW-Authenticate', 'Bearer');

        return JsonResponder::send($response, 401, ['error' => $message]);
    }
}