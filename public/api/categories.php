<?php

declare(strict_types=1);

use App\CategoryRepository;
use App\Database;
use App\JsonResponder;
use App\Validator;
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Maps raw database row to API response structure.
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
    /**
     * Get all categories.
     */
    #[OAT\Get(
        path: '/api/v1/categories',
        operationId: 'listCategories',
        summary: 'List categories',
        tags: ['Categories'],
        responses: [
            new OAT\Response(
                response: 200, 
                description: 'Category list', 
                content: new OAT\JsonContent(
                    type: 'array',
                    items: new OAT\Items(ref: '#/components/schemas/Category')
                )
            ),
            new OAT\Response(
                response: 401, 
                description: 'Unauthorized', 
                content: new OAT\JsonContent(ref: '#/components/schemas/Error')
            ),
        ]
    )]
    public static function listCategories(Request $request, Response $response): Response
    {
        $repository = new CategoryRepository(Database::getConnection());
        $categories = array_map('mapCategoryRow', $repository->findAll());

        return JsonResponder::send($response, 200, $categories);
    }

    /**
     * Create a new category.
     */
    #[OAT\Post(
        path: '/api/v1/category',
        operationId: 'createCategory',
        summary: 'Create category',
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
            new OAT\Response(response: 401, description: 'Unauthorized', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function createCategory(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::send($response, 400, ['error' => 'Invalid request body']);
        }

        $errors = Validator::validateCategory($body, true);
        if ($errors !== []) {
            return JsonResponder::send($response, 400, ['error' => 'Validation failed', 'details' => $errors]);
        }

        $body = Validator::getTrimmedData();

        $repository = new CategoryRepository(Database::getConnection());
        $newId = $repository->create($body);
        $category = $repository->findById($newId);

        return JsonResponder::send($response, 201, mapCategoryRow($category ?? []));
    }

    /**
     * Get category by ID.
     */
    #[OAT\Get(
        path: '/api/v1/category/{category_id}',
        operationId: 'getCategory',
        summary: 'Get category',
        tags: ['Categories'],
        parameters: [
            new OAT\PathParameter(name: 'category_id', description: 'Category ID', schema: new OAT\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'Category details', content: new OAT\JsonContent(ref: '#/components/schemas/Category')),
            new OAT\Response(response: 400, description: 'Invalid category ID', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Unauthorized', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 404, description: 'Category not found', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function getCategory(Request $request, Response $response): Response
    {
        $id = $request->getAttribute('category_id');

        if (!Validator::isValidId((string) $id)) {
            return JsonResponder::send($response, 400, ['error' => 'Invalid category ID']);
        }

        $repository = new CategoryRepository(Database::getConnection());
        $category = $repository->findById((int) $id);

        if ($category === null) {
            return JsonResponder::send($response, 404, ['error' => 'Category not found']);
        }

        return JsonResponder::send($response, 200, mapCategoryRow($category));
    }

    /**
     * Update category.
     */
    #[OAT\Patch(
        path: '/api/v1/category/{category_id}',
        operationId: 'updateCategory',
        summary: 'Update category',
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
            new OAT\Response(response: 401, description: 'Unauthorized', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 404, description: 'Category not found', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function updateCategory(Request $request, Response $response): Response
    {
        $id = $request->getAttribute('category_id');

        if (!Validator::isValidId((string) $id)) {
            return JsonResponder::send($response, 400, ['error' => 'Invalid category ID']);
        }
        $id = (int) $id;

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::send($response, 400, ['error' => 'Invalid request body']);
        }

        $errors = Validator::validateCategory($body, false);
        if ($errors !== []) {
            return JsonResponder::send($response, 400, ['error' => 'Validation failed', 'details' => $errors]);
        }

        $body = Validator::getTrimmedData();

        $repository = new CategoryRepository(Database::getConnection());

        $existing = $repository->findById($id);
        if ($existing === null) {
            return JsonResponder::send($response, 404, ['error' => 'Category not found']);
        }

        $repository->update($id, $body, $existing);

        $category = $repository->findById($id);

        return JsonResponder::send($response, 200, mapCategoryRow($category ?? []));
    }

    /**
     * Delete category.
     */
    #[OAT\Delete(
        path: '/api/v1/category/{category_id}',
        operationId: 'deleteCategory',
        summary: 'Delete category',
        tags: ['Categories'],
        parameters: [
            new OAT\PathParameter(name: 'category_id', description: 'Category ID', schema: new OAT\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OAT\Response(response: 204, description: 'Category deleted'),
            new OAT\Response(response: 400, description: 'Invalid category ID', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 401, description: 'Unauthorized', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
            new OAT\Response(response: 404, description: 'Category not found', content: new OAT\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public static function deleteCategory(Request $request, Response $response): Response
    {
        $id = $request->getAttribute('category_id');

        if (!Validator::isValidId((string) $id)) {
            return JsonResponder::send($response, 400, ['error' => 'Invalid category ID']);
        }

        $repository = new CategoryRepository(Database::getConnection());

        if ($repository->findById((int) $id) === null) {
            return JsonResponder::send($response, 404, ['error' => 'Category not found']);
        }

        $repository->delete((int) $id);

        return $response->withStatus(204);
    }
}