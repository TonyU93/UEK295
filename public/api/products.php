<?php

/**
 * products.php – Route handlers for the product resource.
 *
 * Endpoints (all protected by JwtMiddleware):
 *   GET    /api/v1/products            – list all products
 *   GET    /api/v1/product/{id}        – read a single product
 *   PUT    /api/v1/product/{id}        – create or update (upsert)
 *   DELETE /api/v1/product/{id}        – delete a product
 *
 */

declare(strict_types=1);

use App\Database;
use App\JsonResponder;
use App\ProductRepository;
use App\Validator;
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Converts a raw DB row into the API response structure.
 * Numbers get their proper types.
 *
 * @param array<string, mixed> $row raw row from mysqli
 * @return array<string, mixed> API-ready product object
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
    #[OAT\Get(
        path: '/api/v1/products',
        operationId: 'listProducts',
        summary: 'List all products',
        tags: ['Products'],
        responses: [
            new OAT\Response(response: 200, description: 'All products', content: new OAT\JsonContent(
                type: 'array',
                items: new OAT\Items(ref: '#/components/schemas/Product')
            )),
            new OAT\Response(response: 401, description: 'Not authenticated', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function listProducts(Request $request, Response $response): Response
    {
        $repository = new ProductRepository(Database::getConnection());
        $products = array_map('mapProductRow', $repository->findAll());

        return JsonResponder::send($response, 200, $products);
    }

    #[OAT\Get(
        path: '/api/v1/product/{product_id}',
        operationId: 'getProduct',
        summary: 'Read a single product',
        tags: ['Products'],
        parameters: [
            new OAT\PathParameter(name: 'product_id', description: 'Product ID', schema: new OAT\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'The product', content: new OAT\JsonContent(ref: '#/components/schemas/Product')),
            new OAT\Response(response: 400, description: 'Invalid product ID', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Not authenticated', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 404, description: 'Product not found', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function getProduct(Request $request, Response $response): Response
    {
        $id = $request->getAttribute('product_id');

        if (!Validator::validateId((string) $id)) {
            return JsonResponder::send($response, 400, ['error' => 'product_id must be a positive integer']);
        }

        $repository = new ProductRepository(Database::getConnection());
        $product = $repository->findById((int) $id);

        if ($product === null) {
            return JsonResponder::send($response, 404, ['error' => 'product not found']);
        }

        return JsonResponder::send($response, 200, mapProductRow($product));
    }

    #[OAT\Put(
        path: '/api/v1/product/{product_id}',
        operationId: 'upsertProduct',
        summary: 'Create or update a product (upsert)',
        description: 'Creates the product with exactly the given ID if it does not exist (201), otherwise updates it (200).',
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
                    new OAT\Property(property: 'description', type: 'string', nullable: true, example: 'A product description'),
                    new OAT\Property(property: 'price', type: 'number', format: 'float', example: 19.99),
                    new OAT\Property(property: 'stock', type: 'integer', example: 10),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 200, description: 'Product updated', content: new OAT\JsonContent(ref: '#/components/schemas/Product')),
            new OAT\Response(response: 201, description: 'Product created', content: new OAT\JsonContent(ref: '#/components/schemas/Product')),
            new OAT\Response(response: 400, description: 'Validation failed or category does not exist', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Not authenticated', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function upsertProduct(Request $request, Response $response): Response
    {
        $id = $request->getAttribute('product_id');

        // Route parameter must be a positive integer
        if (!Validator::validateId((string) $id)) {
            return JsonResponder::send($response, 400, ['error' => 'product_id must be a positive integer']);
        }
        $id = (int) $id;

        // Body must be a JSON object
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::send($response, 400, ['error' => 'request body must be a JSON object']);
        }

        // Validate the payload (required fields, types, limits)
        $errors = Validator::validateProduct($body);
        if ($errors !== []) {
            return JsonResponder::send($response, 400, ['error' => 'validation failed', 'details' => $errors]);
        }

        // If a category is given, it must exist (FK integrity check -> 400)
        if (isset($body['id_category']) && is_int($body['id_category'])) {
            $repository = new ProductRepository(Database::getConnection());
            if (!$repository->categoryExists($body['id_category'])) {
                return JsonResponder::send($response, 400, ['error' => 'id_category does not exist']);
            }
        }

        $repository = new ProductRepository(Database::getConnection());

        // Upsert: create when the product does not exist yet, otherwise update.
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

    #[OAT\Delete(
        path: '/api/v1/product/{product_id}',
        operationId: 'deleteProduct',
        summary: 'Delete a product',
        tags: ['Products'],
        parameters: [
            new OAT\PathParameter(name: 'product_id', description: 'Product ID', schema: new OAT\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OAT\Response(response: 204, description: 'Product deleted (no content)'),
            new OAT\Response(response: 400, description: 'Invalid product ID', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Not authenticated', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 404, description: 'Product not found', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function deleteProduct(Request $request, Response $response): Response
    {
        $id = $request->getAttribute('product_id');

        // Route parameter must be a positive integer
        if (!Validator::validateId((string) $id)) {
            return JsonResponder::send($response, 400, ['error' => 'product_id must be a positive integer']);
        }

        $repository = new ProductRepository(Database::getConnection());

        // 404 when the product does not exist
        if ($repository->findById((int) $id) === null) {
            return JsonResponder::send($response, 404, ['error' => 'product not found']);
        }

        $repository->delete((int) $id);

        return $response->withStatus(204);
    }
}
