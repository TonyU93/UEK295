<?php

/**
 * api-main.php OpenAPI global metadata, reusable schemas and the
 * authentication handler
 */

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
        description: 'REST API for managing products and categories of an online shop. All endpoints except authentication require a Bearer token.'
    ),
    servers: [
        new OAT\Server(url: 'http://localhost/api/v1', description: 'Local development server'),
    ],
    security: [['BearerAuth' => []]],
    tags: [
        new OAT\Tag(name: 'Authentication', description: 'User authentication'),
        new OAT\Tag(name: 'Products', description: 'Product management'),
        new OAT\Tag(name: 'Categories', description: 'Category management'),
    ]
)]
#[OAT\SecurityScheme(
    securityScheme: 'BearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Send the token as "Authorization: Bearer <token>". Obtain it via POST /api/v1/authenticate.'
)]
final class OpenApiSpec
{
}

#[OAT\Schema(
    schema: 'Product',
    title: 'Product',
    required: ['id_product', 'name', 'price', 'stock', 'active'],
    properties: [
        new OAT\Property(property: 'id_product', type: 'integer', example: 1),
        new OAT\Property(property: 'id_category', type: 'integer', nullable: true, example: 1, description: 'null = product is not listed'),
        new OAT\Property(property: 'name', type: 'string', maxLength: 150, example: 'T-Shirt'),
        new OAT\Property(property: 'description', type: 'string', nullable: true, example: 'A product description'),
        new OAT\Property(property: 'image', type: 'string', maxLength: 500, nullable: true, example: 'https://example.com/images/tshirt.png'),
        new OAT\Property(property: 'price', type: 'number', format: 'float', example: 19.99),
        new OAT\Property(property: 'stock', type: 'integer', example: 10),
        new OAT\Property(property: 'active', type: 'integer', enum: [0, 1], example: 1),
    ]
)]
#[OAT\Schema(
    schema: 'Category',
    title: 'Category',
    required: ['id_category', 'name', 'active'],
    properties: [
        new OAT\Property(property: 'id_category', type: 'integer', example: 1),
        new OAT\Property(property: 'name', type: 'string', maxLength: 100, example: 'Clothing'),
        new OAT\Property(property: 'active', type: 'integer', enum: [0, 1], example: 1),
    ]
)]
#[OAT\Schema(
    schema: 'TokenResponse',
    title: 'TokenResponse',
    required: ['token'],
    properties: [
        new OAT\Property(property: 'token', type: 'string', description: 'Signed JWT (valid for JWT_TTL seconds)'),
    ]
)]
#[OAT\Schema(
    schema: 'Error',
    title: 'Error',
    properties: [
        new OAT\Property(property: 'error', type: 'string', example: 'resource not found'),
        new OAT\Property(property: 'details', type: 'array', items: new OAT\Items(type: 'string'), nullable: true, description: 'Validation details, only on validation errors'),
    ]
)]
final class ApiSchemas
{
}

final class AuthApi
{
    #[OAT\Post(
        path: '/api/v1/authenticate',
        operationId: 'authenticate',
        summary: 'Authenticate and obtain a JWT',
        description: 'The only public endpoint. Expects username and password, returns a signed JWT.',
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
            new OAT\Response(response: 200, description: 'Valid token', content: new OAT\JsonContent(ref: '#/components/schemas/TokenResponse')),
            new OAT\Response(response: 400, description: 'Body missing or fields empty', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Invalid credentials', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function authenticate(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        //body must be a JSON object (BodyParsingMiddleware delivers an array)
        if (!is_array($body)) {
            return JsonResponder::send($response, 400, ['error' => 'request body must be a JSON object']);
        }

        $username = isset($body['username']) && is_string($body['username']) ? trim($body['username']) : '';
        $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';

        // both field are required and must not be empty
        if ($username === '' || $password === '') {
            return JsonResponder::send($response, 400, ['error' => 'username and password are required']);
        }

        // look up the user in the database (prepared statement inside the repository)
        $repository = new UserRepository(Database::getConnection());
        $user = $repository->findByUsername($username);

        // Same generic message for unknown user and wrong password (no user enumeration)
        if ($user === null || !password_verify($password, (string) $user['password'])) {
            return JsonResponder::send($response, 401, ['error' => 'invalid credentials']);
        }

        // credentials are valid: issue a signed JWT for all protected endpoints
        $jwtService = new JwtService();
        $token = $jwtService->issueToken((int) $user['id_user'], (string) $user['username']);

        return JsonResponder::send($response, 200, ['token' => $token]);
    }
}
