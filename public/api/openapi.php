<?php

declare(strict_types=1);

use OpenApi\Attributes as OAT;

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
        new OAT\Property(property: 'description', type: 'string', nullable: true, example: 'Product description'),
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
        new OAT\Property(property: 'name', type: 'string', maxLength: 100, example: 'Clothing'),
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
        new OAT\Property(property: 'error', type: 'string', example: 'Invalid credentials'),
        new OAT\Property(property: 'details', type: 'array', items: new OAT\Items(type: 'string'), nullable: true),
    ]
)]
final class ApiSchemas
{
}
