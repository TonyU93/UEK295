<?php

declare(strict_types=1);

use App\Config\Database;
use App\Http\JsonResponder;
use App\Repository\ProductRepository;
use App\Validation\Validator;
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Maps database row to API response array.
 */
function mapProductRow(array $row): array
{
    return [
        'id_product'  => (int) $row['id_product'],
        'id_category' => $row['id_category'] !== null ? (int) $row['id_category'] : null,
        'name'        => (string) $row['name'],
        'description' => $row['description'] !== null ? (string) $row['description'] : null,
        'image'       => $row['image'] !== null ? (string) $row['image'] : null,
        'price'       => (float) $row['price'],
        'stock'       => (int) $row['stock'],
        'active'      => (int) $row['active'],
    ];
}

final class ProductApi
{
    /**
     * Get all products.
     */
    #[OAT\Get(
        path: '/api/v1/products',
        operationId: 'listProducts',
        summary: 'List products',
        tags: ['Products'],
        responses: [
            new OAT\Response(
                response: 200, 
                description: 'Product list', 
                content: new OAT\JsonContent(
                    type: 'array',
                    items: new OAT\Items(ref: '#/components/schemas/Product')
                )
            ),
            new OAT\Response(
                response: 401, 
                description: 'Unauthorized', 
                content: new OAT\JsonContent(ref: '#/components/schemas/Error')
            ),
        ]
    )]
    public static function listProducts(Request $request, Response $response): Response
    {
        $repository = new ProductRepository(Database::getConnection());
        $products = array_map('mapProductRow', $repository->findAll());

        return JsonResponder::send($response, 200, $products);
    }

    /**
     * Get product by ID.
     */
    #[OAT\Get(
        path: '/api/v1/product/{product_id}',
        operationId: 'getProduct',
        summary: 'Get product',
        tags: ['Products'],
        parameters: [
            new OAT\PathParameter(name: 'product_id', description: 'Product ID', schema: new OAT\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'Product details', content: new OAT\JsonContent(ref: '#/components/schemas/Product')),
            new OAT\Response(response: 400, description: 'Invalid product ID', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Unauthorized', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 404, description: 'Product not found', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function getProduct(Request $request, Response $response): Response
    {
        $id = $request->getAttribute('product_id');

        if (!Validator::isValidId((string) $id)) {
            return JsonResponder::send($response, 400, ['error' => 'Invalid product ID']);
        }

        $repository = new ProductRepository(Database::getConnection());
        $product = $repository->findById((int) $id);

        if ($product === null) {
            return JsonResponder::send($response, 404, ['error' => 'Product not found']);
        }

        return JsonResponder::send($response, 200, mapProductRow($product));
    }

    /**
     * Create or update product (upsert).
     */
    #[OAT\Put(
        path: '/api/v1/product/{product_id}',
        operationId: 'upsertProduct',
        summary: 'Create or update product',
        tags: ['Products'],
        parameters: [
            new OAT\PathParameter(name: 'product_id', description: 'Product ID', schema: new OAT\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                required: ['active', 'name', 'price', 'stock'],
                properties: [
                    new OAT\Property(property: 'active', type: 'integer', enum: [0, 1], example: 1),
                    new OAT\Property(property: 'id_category', type: 'integer', nullable: true, example: 1),
                    new OAT\Property(property: 'name', type: 'string', maxLength: 150, example: 'T-Shirt'),
                    new OAT\Property(property: 'image', type: 'string', maxLength: 500, nullable: true, example: 'https://example.com/images/tshirt.png'),
                    new OAT\Property(property: 'description', type: 'string', nullable: true, example: 'Product description'),
                    new OAT\Property(property: 'price', type: 'number', format: 'float', example: 19.99),
                    new OAT\Property(property: 'stock', type: 'integer', example: 10),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 200, description: 'Product updated', content: new OAT\JsonContent(ref: '#/components/schemas/Product')),
            new OAT\Response(response: 201, description: 'Product created', content: new OAT\JsonContent(ref: '#/components/schemas/Product')),
            new OAT\Response(response: 400, description: 'Validation failed', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Unauthorized', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function upsertProduct(Request $request, Response $response): Response
    {
        $id = $request->getAttribute('product_id');

        if (!Validator::isValidId((string) $id)) {
            return JsonResponder::send($response, 400, ['error' => 'Invalid product ID']);
        }
        $id = (int) $id;

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::send($response, 400, ['error' => 'Invalid request body']);
        }

        $errors = Validator::validateProduct($body);
        if ($errors !== []) {
            return JsonResponder::send($response, 400, ['error' => 'Validation failed', 'details' => $errors]);
        }

        $body = Validator::getTrimmedData();

        if (isset($body['id_category']) && is_int($body['id_category'])) {
            $repository = new ProductRepository(Database::getConnection());
            if (!$repository->hasCategory($body['id_category'])) {
                return JsonResponder::send($response, 400, ['error' => 'Category does not exist']);
            }
        }

        $repository = new ProductRepository(Database::getConnection());

        $existing = $repository->findById($id);
        if ($existing === null) {
            $repository->create($body, $id);
            $product = $repository->findById($id);
            return JsonResponder::send($response, 201, mapProductRow($product ?? []));
        }

        $repository->update($id, $body);

        $product = $repository->findById($id);

        return JsonResponder::send($response, 200, mapProductRow($product ?? []));
    }

    /**
     * Delete product.
     */
    #[OAT\Delete(
        path: '/api/v1/product/{product_id}',
        operationId: 'deleteProduct',
        summary: 'Delete product',
        tags: ['Products'],
        parameters: [
            new OAT\PathParameter(name: 'product_id', description: 'Product ID', schema: new OAT\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OAT\Response(response: 204, description: 'Product deleted'),
            new OAT\Response(response: 400, description: 'Invalid product ID', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Unauthorized', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 404, description: 'Product not found', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function deleteProduct(Request $request, Response $response): Response
    {
        $id = $request->getAttribute('product_id');

        if (!Validator::isValidId((string) $id)) {
            return JsonResponder::send($response, 400, ['error' => 'Invalid product ID']);
        }

        $repository = new ProductRepository(Database::getConnection());

        if ($repository->findById((int) $id) === null) {
            return JsonResponder::send($response, 404, ['error' => 'Product not found']);
        }

        $repository->delete((int) $id);

        return $response->withStatus(204);
    }
}