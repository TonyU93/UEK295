<?php

/**
 * categories.php – Route handlers for the category resource.
 *
 * Endpoints (all protected by JwtMiddleware):
 *   GET    /api/v1/categories              – list all categories
 *   POST   /api/v1/category                – create a category
 *   GET    /api/v1/category/{category_id}  – read a single category
 *   PATCH  /api/v1/category/{category_id}  – update (partial, PATCH semantics)
 *   DELETE /api/v1/category/{category_id}  – delete a category
 */

declare(strict_types=1);

use App\CategoryRepository;
use App\Database;
use App\JsonResponder;
use App\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Converts a raw DB row into the API response structure
 * Numbers get their proper types active as int
 *
 * @param array<string, mixed> $row raw row from mysqli
 * @return array<string, mixed> API-ready category object
 */
function mapCategoryRow(array $row): array
{
    return [
        'id_category' => (int) $row['id_category'],
        'name'        => (string) $row['name'],
        'active'      => (int) $row['active'],
    ];
}

/**
 * GET /api/v1/categories – list all categories.
 *
 * @param Request  $request  incoming request
 * @param Response $response empty response to fill
 */
function listCategories(Request $request, Response $response): Response
{
    $repository = new CategoryRepository(Database::getConnection());
    $categories = array_map('mapCategoryRow', $repository->findAll());

    return JsonResponder::send($response, 200, $categories);
}

/**
 * POST /api/v1/category – create a category.
 *
 * Expects the full payload: {"active": 1, "name": "..."}
 * Returns 201 with the created object including its new id_category.
 *
 * @param Request  $request  incoming request JSON body
 * @param Response $response empty response to fill
 */
function createCategory(Request $request, Response $response): Response
{
    // body must be a JSON object
    $body = $request->getParsedBody();
    if (!is_array($body)) {
        return JsonResponder::send($response, 400, ['error' => 'request body must be a JSON object']);
    }

    // Full validation: both fields are required on POST
    $errors = Validator::validateCategory($body, true);
    if ($errors !== []) {
        return JsonResponder::send($response, 400, ['error' => 'validation failed', 'details' => $errors]);
    }

    $repository = new CategoryRepository(Database::getConnection());

    $newId = $repository->create($body);

    // Re-read the row so the response contains the stored values
    $category = $repository->findById($newId);

    return JsonResponder::send($response, 201, mapCategoryRow($category ?? []));
}

/**
 * GET /api/v1/category/{category_id} – read a single category.
 *
 * @param Request  $request  incoming request (category_id route argument)
 * @param Response $response empty response to fill
 */
function getCategory(Request $request, Response $response): Response
{
    // The route placeholder is {category_id:[0-9]+}, so the attribute has this exact name
    $id = $request->getAttribute('category_id');

    // Route parameter must be a positive integer
    if (!Validator::validateId((string) $id)) {
        return JsonResponder::send($response, 400, ['error' => 'category_id must be a positive integer']);
    }

    $repository = new CategoryRepository(Database::getConnection());
    $category = $repository->findById((int) $id);

    // 404 for unknown categories
    if ($category === null) {
        return JsonResponder::send($response, 404, ['error' => 'category not found']);
    }

    return JsonResponder::send($response, 200, mapCategoryRow($category));
}

/**
 * PATCH /api/v1/category/{category_id} – update a category partial
 *
 * Accepts a subset of {"name": ..., "active": ...}; at least one field is
 * required. Missing fields keep their current value
 *
 * @param Request  $request  incoming request JSON body + category_id route argument
 * @param Response $response empty response to fill
 */
function updateCategory(Request $request, Response $response): Response
{
    // The route placeholder is {category_id:[0-9]+}, so the attribute has this exact name
    $id = $request->getAttribute('category_id');

    // Route parameter must be a positive integer
    if (!Validator::validateId((string) $id)) {
        return JsonResponder::send($response, 400, ['error' => 'category_id must be a positive integer']);
    }
    $id = (int) $id;

    // body must be a JSON object
    $body = $request->getParsedBody();
    if (!is_array($body)) {
        return JsonResponder::send($response, 400, ['error' => 'request body must be a JSON object']);
    }

    // Partial validation: at least one of name/active must be present
    $errors = Validator::validateCategory($body, false);
    if ($errors !== []) {
        return JsonResponder::send($response, 400, ['error' => 'validation failed', 'details' => $errors]);
    }

    $repository = new CategoryRepository(Database::getConnection());

    // 404 when the category does not exist
    $existing = $repository->findById($id);
    if ($existing === null) {
        return JsonResponder::send($response, 404, ['error' => 'category not found']);
    }

    $repository->update($id, $body, $existing);

    // Re-read the row so the response contains the merged values
    $category = $repository->findById($id);

    return JsonResponder::send($response, 200, mapCategoryRow($category ?? []));
}

/**
 * DELETE /api/v1/category/category_id 
 *
 * Products that referenced this category are kept: their id_category
 * becomes NULL through the foreign key ON DELETE SET NULL
 *
 * @param Request  $request  incoming request
 * @param Response $response empty response to fill
 */
function deleteCategory(Request $request, Response $response): Response
{
    // The route placeholder is {category_id:[0-9]+}, so the attribute has this exact name
    $id = $request->getAttribute('category_id');

    // Route parameter must be a positive integer
    if (!Validator::validateId((string) $id)) {
        return JsonResponder::send($response, 400, ['error' => 'category_id must be a positive integer']);
    }

    $repository = new CategoryRepository(Database::getConnection());

    // 404 when the category does not exist
    if ($repository->findById((int) $id) === null) {
        return JsonResponder::send($response, 404, ['error' => 'category not found']);
    }

    $repository->delete((int) $id);

    return $response->withStatus(204);
}
