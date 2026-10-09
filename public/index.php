<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Autoloader fallback for App namespace
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $relative = substr($class, 4);
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

App\Env::load(dirname(__DIR__) . '/.env');

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Custom error responses
$errorMiddleware = $app->addErrorMiddleware(false, true, true);

$errorHandler = $errorMiddleware->getDefaultErrorHandler();
if ($errorHandler instanceof SlimErrorHandler) {
    $errorHandler->registerErrorRenderer('application/json', App\JsonErrorRenderer::class);
    $errorHandler->forceContentType('application/json');
    $errorHandler->setDefaultErrorRenderer('application/json', App\JsonErrorRenderer::class);
}

$errorMiddleware->setErrorHandler(HttpNotFoundException::class, static function (
    Request $request,
    \Throwable $exception
): Response {
    $response = AppFactory::determineResponseFactory()->createResponse(404);
    $response->getBody()->write((string) json_encode(['error' => 'Resource not found']));
    return $response->withHeader('Content-Type', 'application/json');
}, true);

$errorMiddleware->setErrorHandler(HttpMethodNotAllowedException::class, static function (
    Request $request,
    \Throwable $exception
): Response {
    $response = AppFactory::determineResponseFactory()->createResponse(405);
    $response->getBody()->write((string) json_encode(['error' => 'Method not allowed']));
    return $response->withHeader('Content-Type', 'application/json');
}, true);

// Auth endpoint
require_once __DIR__ . '/api/api-main.php';
$app->post('/api/v1/authenticate', [AuthApi::class, 'authenticate']);

// Product endpoints
require_once __DIR__ . '/api/products.php';
$app->get('/api/v1/products', [ProductApi::class, 'listProducts']);
$app->get('/api/v1/product/{product_id:[0-9]+}', [ProductApi::class, 'getProduct']);
$app->put('/api/v1/product/{product_id:[0-9]+}', [ProductApi::class, 'upsertProduct']);
$app->delete('/api/v1/product/{product_id:[0-9]+}', [ProductApi::class, 'deleteProduct']);

// Category endpoints
require_once __DIR__ . '/api/categories.php';
$app->get('/api/v1/categories', [CategoryApi::class, 'listCategories']);
$app->post('/api/v1/category', [CategoryApi::class, 'createCategory']);
$app->get('/api/v1/category/{category_id:[0-9]+}', [CategoryApi::class, 'getCategory']);
$app->patch('/api/v1/category/{category_id:[0-9]+}', [CategoryApi::class, 'updateCategory']);
$app->delete('/api/v1/category/{category_id:[0-9]+}', [CategoryApi::class, 'deleteCategory']);

// Authentication middleware
$jwtMiddleware = new App\JwtMiddleware(new App\JwtService());
$app->add($jwtMiddleware);

$app->run();