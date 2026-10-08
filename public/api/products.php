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

/**
 * GET /api/v1/products – list all products.
 *
 * @param Request  $request  incoming request
 * @param Response $response empty response to fill
 */
function listProducts(Request $request, Response $response): Response
{
    $repository = new ProductRepository(Database::getConnection());
    $products = array_map('mapProductRow', $repository->findAll());

    return JsonResponder::send($response, 200, $products);
}

/**
 * GET /api/v1/product/{product_id} – read a single product.
 *
 * @param Request  $request  incoming request (id_product route argument)
 * @param Response $response empty response to fill
 */
function getProduct(Request $request, Response $response): Response
{
    // The route placeholder is {product_id:[0-9]+}, so the attribute has this exact name
    $id = $request->getAttribute('product_id');

    // Route parameter must be a positive integer
    if (!Validator::validateId((string) $id)) {
        return JsonResponder::send($response, 400, ['error' => 'product_id must be a positive integer']);
    }

    $repository = new ProductRepository(Database::getConnection());
    $product = $repository->findById((int) $id);

    // 404 for unknown products
    if ($product === null) {
        return JsonResponder::send($response, 404, ['error' => 'product not found']);
    }

    return JsonResponder::send($response, 200, mapProductRow($product));
}

/**
 * PUT /api/v1/product/{product_id} – create or update a product (upsert).
 *
 * @param Request  $request  incoming request (JSON body + id_product route argument)
 * @param Response $response empty response to fill
 */
function upsertProduct(Request $request, Response $response): Response
{
    // The route placeholder is {product_id:[0-9]+}, so the attribute has this exact name
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

/**
 * DELETE /api/v1/product/{product_id} – delete a product.
 *
 * @param Request  $request  incoming request (id_product route argument)
 * @param Response $response empty response to fill
 */
function deleteProduct(Request $request, Response $response): Response
{
    // The route placeholder is {product_id:[0-9]+}, so the attribute has this exact name
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
