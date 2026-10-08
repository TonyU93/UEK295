<?php

/**
 * index.php – API front controller. single central entry point.
 *
 * All requests are routed here via the .htaccess rewrite rule and are
 * processed by Slim. Responsible for:
 *  - Autoloading fallback SPL autoloader for App\ until Composer PSR-4 is active
 *  - Setting up the Slim app, including middleware body parsing, routing, error handling
 *  - Registering routes currently test routes. i have to change them later --dontForget--
 */

declare(strict_types=1);

// Autoloading

require_once dirname(__DIR__) . '/vendor/autoload.php';

// fallback autoloader for the App\ classes src/ until the composer PSR-4 mapping
// is set up in step 3. this supplements composer's autoload_classmap.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $relative = substr($class, 4); // "App\Env" -> "Env"
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

// .env-configuration load

App\Env::load(dirname(__DIR__) . '/.env');

// Set up the Slim app

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;

$app = AppFactory::create();

//automatically parse the JSON body in $request->getParsedBody() (application/json)
$app->addBodyParsingMiddleware();

// Routing middleware: throws an HttpNotFoundException (404) or
// an HttpMethodNotAllowedException (405) for unknown paths or methods
$app->addRoutingMiddleware();

// error  middleware: catches all unhandled exceptions
// keep `displayErrorDetails` set to `false` in production
$errorMiddleware = $app->addErrorMiddleware(false, true, true);

// Configure Slim's default ErrorHandler so that errors are always returned as JSON
// with the structure {“error”: “...”} never as HTML.
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
if ($errorHandler instanceof SlimErrorHandler) {
    $errorHandler->registerErrorRenderer('application/json', App\JsonErrorRenderer::class);
    $errorHandler->forceContentType('application/json');
    $errorHandler->setDefaultErrorRenderer('application/json', App\JsonErrorRenderer::class);
}

// Explicitly set a custom renderer for the most common errors (404 / 405),
// so that the messages match the rest of the API.
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, static function (
    Request $request,
    \Throwable $exception
): Response {
    $response = AppFactory::determineResponseFactory()->createResponse(404);
    $response->getBody()->write((string) json_encode(['error' => 'resource not found']));
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



$app->get('/', static function (Request $request, Response $response): Response {
    $payload = [
        'name'    => 'uek295 ShopAPI',
        'status'  => 'running',
        'version' => 'v1',
    ];
    $response->getBody()->write((string) json_encode($payload));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/db-check', static function (Request $request, Response $response): Response {
    try {
        $db      = App\Database::getConnection();
        $payload = ['status' => 'ok', 'server_info' => $db->server_info];
    } catch (\Throwable $e) {
        $payload = ['status' => 'error', 'error' => $e->getMessage()];
    }

    $response->getBody()->write((string) json_encode($payload));
    return $response->withHeader('Content-Type', 'application/json');
});

// API v1 – authentication endpoint (the only public route)

require_once __DIR__ . '/api/api-main.php';

$app->post('/api/v1/authenticate', 'authenticate');

// API v1 – product endpoints (protected by JwtMiddleware)

require_once __DIR__ . '/api/products.php';

$app->get('/api/v1/products', 'listProducts');
$app->get('/api/v1/product/{product_id:[0-9]+}', 'getProduct');
$app->put('/api/v1/product/{product_id:[0-9]+}', 'upsertProduct');
$app->delete('/api/v1/product/{product_id:[0-9]+}', 'deleteProduct');


// JWT protection for all other endpoints

$jwtMiddleware = new App\JwtMiddleware(new App\JwtService());
$app->add($jwtMiddleware);

$app->run();
