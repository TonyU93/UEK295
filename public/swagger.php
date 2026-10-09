<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Load API endpoints for OpenAPI generator
require_once __DIR__ . '/api/api-main.php';
require_once __DIR__ . '/api/products.php';
require_once __DIR__ . '/api/categories.php';

$openapi = \OpenApi\Generator::scan([__DIR__ . '/api']);

header('Content-Type: application/x-yaml');

if ($openapi === null) {
    http_response_code(500);
    echo '# Failed to generate OpenAPI spec';
    exit;
}

echo $openapi->toYaml();