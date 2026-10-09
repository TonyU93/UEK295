<?php

declare(strict_types=1);

use App\Database;
use App\JsonResponder;
use App\JwtService;
use App\UserRepository;
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

#[OAT\OpenApi(
    info: new OAT\Info(
        title: 'Shop API',
        version: '1.0.0',
        description: 'REST API fuer Produkte und Kategorien'
    ),
    servers: [
        new OAT\Server(url: 'http://localhost/api/v1', description: 'Lokaler Server'),
    ],
    security: [['BearerAuth' => []]],
    tags: [
        new OAT\Tag(name: 'Authentication'),
        new OAT\Tag(name: 'Products'),
        new OAT\Tag(name: 'Categories'),
    ]
)]
#[OAT\SecurityScheme(
    securityScheme: 'BearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
final class OpenApiSpec
{
}

#[OAT\Schema(
    schema: 'Product',
    required: ['id_product', 'name', 'price', 'stock', 'active'],
    properties: [
        new OAT\Property(property: 'id_product', type: 'integer', example: 1),
        new OAT\Property(property: 'id_category', type: 'integer', nullable: true, example: 1),
        new OAT\Property(property: 'name', type: 'string', maxLength: 150, example: 'T-Shirt'),
        new OAT\Property(property: 'description', type: 'string', nullable: true, example: 'Produktbeschreibung'),
        new OAT\Property(property: 'image', type: 'string', maxLength: 500, nullable: true, example: 'https://example.com/image.png'),
        new OAT\Property(property: 'price', type: 'number', format: 'float', example: 19.99),
        new OAT\Property(property: 'stock', type: 'integer', example: 10),
        new OAT\Property(property: 'active', type: 'integer', enum: [0, 1], example: 1),
    ]
)]
#[OAT\Schema(
    schema: 'Category',
    required: ['id_category', 'name', 'active'],
    properties: [
        new OAT\Property(property: 'id_category', type: 'integer', example: 1),
        new OAT\Property(property: 'name', type: 'string', maxLength: 100, example: 'Bekleidung'),
        new OAT\Property(property: 'active', type: 'integer', enum: [0, 1], example: 1),
    ]
)]
#[OAT\Schema(
    schema: 'TokenResponse',
    required: ['token'],
    properties: [
        new OAT\Property(property: 'token', type: 'string'),
    ]
)]
#[OAT\Schema(
    schema: 'Error',
    properties: [
        new OAT\Property(property: 'error', type: 'string', example: 'Ungueltige Anmeldedaten'),
        new OAT\Property(property: 'details', type: 'array', items: new OAT\Items(type: 'string'), nullable: true),
    ]
)]
final class ApiSchemas
{
}

final class AuthApi
{
    /**
     * Authentifiziert den Benutzer und stellt ein JWT aus.
     */
    #[OAT\Post(
        path: '/api/v1/authenticate',
        summary: 'Benutzer-Login',
        tags: ['Authentication'],
        security: [],
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OAT\Property(property: 'username', type: 'string', example: 'admin'),
                    new OAT\Property(property: 'password', type: 'string', format: 'password', example: '123456'),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 200, description: 'Login erfolgreich', content: new OAT\JsonContent(ref: '#/components/schemas/TokenResponse')),
            new OAT\Response(response: 400, description: 'Ungueltige Eingabe', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Nicht autorisiert', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function authenticate(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return JsonResponder::send($response, 400, ['error' => 'Ungueltiger Request Body']);
        }

        $username = isset($body['username']) && is_string($body['username']) ? trim($body['username']) : '';
        $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';

        if ($username === '' || $password === '') {
            return JsonResponder::send($response, 400, ['error' => 'Benutzername und Passwort sind erforderlich']);
        }

        $repository = new UserRepository(Database::getConnection());
        $user = $repository->findByUsername($username);

        if ($user === null || !password_verify($password, (string) $user['password'])) {
            return JsonResponder::send($response, 401, ['error' => 'Ungueltige Anmeldedaten']);
        }

        $jwtService = new JwtService();
        $token = $jwtService->issueToken((int) $user['id_user'], (string) $user['username']);

        return JsonResponder::send($response, 200, ['token' => $token]);
    }
}