<?php

declare(strict_types=1);

use App\Auth\JwtService;
use App\Config\Database;
use App\Http\JsonResponder;
use App\Repository\UserRepository;
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthApi
{
    #[OAT\Post(
        path: '/api/v1/authenticate',
        operationId: 'authenticate',
        summary: 'User login',
        tags: ['Authentication'],
        security: [],
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OAT\Property(property: 'username', type: 'string', example: 'admin'),
                    new OAT\Property(property: 'password', type: 'string', format: 'password', example: 'secret'),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 200, description: 'Login successful, token issued', content: new OAT\JsonContent(ref: '#/components/schemas/TokenResponse')),
            new OAT\Response(response: 400, description: 'Invalid request body or missing fields', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Invalid credentials', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function authenticate(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return JsonResponder::send($response, 400, ['error' => 'invalid request body']);
        }

        $username = isset($body['username']) && is_string($body['username']) ? trim($body['username']) : '';
        $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';

        if ($username === '' || $password === '') {
            return JsonResponder::send($response, 400, ['error' => 'username and password are required']);
        }

        $repository = new UserRepository(Database::getConnection());
        $user = $repository->findByUsername($username);

        if ($user === null || !password_verify($password, (string) $user['password'])) {
            return JsonResponder::send($response, 401, ['error' => 'invalid credentials']);
        }

        $jwtService = new JwtService();
        $token = $jwtService->issueToken((int) $user['id_user'], (string) $user['username']);

        return JsonResponder::send($response, 200, ['token' => $token]);
    }
}