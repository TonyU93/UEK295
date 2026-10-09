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
use OpenApi\Attributes as OAT;
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

final class CategoryApi
{
    #[OAT\Get(
        path: '/api/v1/categories',
        operationId: 'listCategories',
        summary: 'List all categories',
        tags: ['Categories'],
        responses: [
            new OAT\Response(response: 200, description: 'All categories', content: new OAT\JsonContent(
                type: 'array',
                items: new OAT\Items(ref: '#/components/schemas/Category')
            )),
            new OAT\Response(response: 401, description: 'Not authenticated', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function listCategories(Request $request, Response $response): Response
    {
        $repository = new CategoryRepository(Database::getConnection());
        $categories = array_map('mapCategoryRow', $repository->findAll());

        return JsonResponder::send($response, 200, $categories);
    }

    #[OAT\Post(
        path: '/api/v1/category',
        operationId: 'createCategory',
        summary: 'Create a category',
        tags: ['Categories'],
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                required: ['active', 'name'],
                properties: [
                    new OAT\Property(property: 'active', type: 'integer', enum: [0, 1], example: 1),
                    new OAT\Property(property: 'name', type: 'string', maxLength: 100, example: 'Clothing'),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 201, description: 'Category created', content: new OAT\JsonContent(ref: '#/components/schemas/Category')),
            new OAT\Response(response: 400, description: 'Validation failed', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Not authenticated', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function createCategory(Request $request, Response $response): Response
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

    #[OAT\Get(
        path: '/api/v1/category/{category_id}',
        operationId: 'getCategory',
        summary: 'Read a single category',
        tags: ['Categories'],
        parameters: [
            new OAT\PathParameter(name: 'category_id', description: 'Category ID', schema: new OAT\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'The category', content: new OAT\JsonContent(ref: '#/components/schemas/Category')),
            new OAT\Response(response: 400, description: 'Invalid category ID', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Not authenticated', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 404, description: 'Category not found', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function getCategory(Request $request, Response $response): Response
    {
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

    #[OAT\Patch(
        path: '/api/v1/category/{category_id}',
        operationId: 'updateCategory',
        summary: 'Update a category (partial)',
        description: 'Accepts a subset of name/active. Missing fields keep their current value.',
        tags: ['Categories'],
        parameters: [
            new OAT\PathParameter(name: 'category_id', description: 'Category ID', schema: new OAT\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                properties: [
                    new OAT\Property(property: 'active', type: 'integer', enum: [0, 1], example: 1),
                    new OAT\Property(property: 'name', type: 'string', maxLength: 100, example: 'Clothing'),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 200, description: 'Category updated', content: new OAT\JsonContent(ref: '#/components/schemas/Category')),
            new OAT\Response(response: 400, description: 'Validation failed', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Not authenticated', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 404, description: 'Category not found', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function updateCategory(Request $request, Response $response): Response
    {
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

    #[OAT\Delete(
        path: '/api/v1/category/{category_id}',
        operationId: 'deleteCategory',
        summary: 'Delete a category',
        description: 'Products referencing this category are kept; their id_category becomes NULL (ON DELETE SET NULL).',
        tags: ['Categories'],
        parameters: [
            new OAT\PathParameter(name: 'category_id', description: 'Category ID', schema: new OAT\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OAT\Response(response: 204, description: 'Category deleted (no content)'),
            new OAT\Response(response: 400, description: 'Invalid category ID', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Not authenticated', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 404, description: 'Category not found', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function deleteCategory(Request $request, Response $response): Response
    {
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
}
