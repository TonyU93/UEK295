<?php

/**
 * swagger.php – Delivers the OpenAPI documentation as YAML.
 *
 * Scans the API classes for OpenAPI attributes and outputs the merged
 * OpenAPI specification. Point Swagger-UI at this URL.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// analyser reflects over the classes, so they must be loaded first
require_once __DIR__ . '/api/api-main.php';
require_once __DIR__ . '/api/products.php';
require_once __DIR__ . '/api/categories.php';

$result = \OpenApi\Generator::scan([__DIR__ . '/api']);

header('Content-Type: application/x-yaml');

if ($result === null) {
    http_response_code(500);
    echo '# No OpenAPI specification found';
    exit;
}

echo $result->toYaml();
