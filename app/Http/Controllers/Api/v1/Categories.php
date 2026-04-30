<?php

namespace App\Http\Controllers\Api\v1;

use App\Actions\Category\CreateNewCategory;
use App\Actions\Category\EditCategory;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Category\EditCategoryRequest;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Resources\Api\CategoryCollection;
use App\Http\Resources\Api\CategoryResource;
use App\Models\Category as CategoryModel;
use App\Models\Category\Group as CategoryGroupModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Categories',
    description: 'Manage categories within a category group. Categories support hierarchical nesting (parent_id) and carry optional dynamic custom fields defined by the group\'s field layout.'
)]
class Categories extends Controller
{
    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/category-groups/{group_id}/categories',
        operationId: 'getCategories',
        summary: 'List categories in a group',
        description: 'Returns a paginated list of categories belonging to the specified group. Root categories only by default; pass ?all=1 to include all levels.',
        security: [['sanctum' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'group_id',
                in: 'path',
                required: true,
                description: 'ID of the category group',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'all',
                in: 'query',
                required: false,
                description: 'Pass 1 to return all categories regardless of depth (not just roots)',
                schema: new OA\Schema(type: 'integer', enum: [0, 1], default: 0)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: 'Number of results per page (max 100)',
                schema: new OA\Schema(type: 'integer', default: 10)
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Page number',
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'sort',
                in: 'query',
                required: false,
                description: 'Column to sort by',
                schema: new OA\Schema(type: 'string', default: 'id')
            ),
            new OA\Parameter(
                name: 'direction',
                in: 'query',
                required: false,
                description: 'Sort direction',
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')
            ),
            new OA\Parameter(
                name: 'created_after',
                in: 'query',
                required: false,
                description: 'Filter to categories created after this date',
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
            new OA\Parameter(
                name: 'created_before',
                in: 'query',
                required: false,
                description: 'Filter to categories created before this date',
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Category')),
                        new OA\Property(property: 'meta', type: 'array', items: new OA\Items(ref: '#/components/schemas/Meta')),
                        new OA\Property(property: 'links', type: 'array', items: new OA\Items(ref: '#/components/schemas/Links')),
                        new OA\Property(property: 'pagination_info', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaginationInfo')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Category group not found'),
        ]
    )]
    public function index(Request $request, int $group_id): CategoryCollection
    {
        if (!$this->can('read categories')) {
            abort(404);
        }

        $group = CategoryGroupModel::find($group_id);

        if (!$group instanceof CategoryGroupModel) {
            abort(404);
        }

        $query = CategoryModel::with(['fieldValues.field.fieldType'])
            ->where('group_id', $group_id);

        if (!$request->boolean('all')) {
            $query->whereNull('parent_id');
        }

        $where = $this->buildWhere([], $request);
        foreach ($where as $condition) {
            $query->where(...$condition);
        }

        if ($this->sort($request) && $this->sortDir($request)) {
            $query->orderBy($this->sort($request), $this->sortDir($request));
        }

        return new CategoryCollection($query->paginate($this->limit($request)));
    }

    // -------------------------------------------------------------------------
    // store — reuses existing App\Http\Requests\Category\StoreCategoryRequest
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/v1/category-groups/{group_id}/categories',
        operationId: 'createCategory',
        summary: 'Create a new category',
        description: 'Creates a category inside the specified group. The `fields` object accepts dynamic field values keyed by handle.',
        security: [['sanctum' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'group_id',
                in: 'path',
                required: true,
                description: 'ID of the category group the new category will belong to',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Display name', example: 'PHP'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255, nullable: true, description: 'URL-safe identifier (auto-generated from name when omitted)', example: 'php'),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true, description: 'ID of the parent category for hierarchical nesting'),
                    new OA\Property(property: 'sort_order', type: 'integer', nullable: true, description: 'Display sort position among siblings'),
                    new OA\Property(
                        property: 'fields',
                        type: 'object',
                        nullable: true,
                        description: 'Dynamic field values keyed by handle (e.g. {"colour": "#ff0000", "summary": "The PHP language"})',
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Category created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Category'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Category group not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreCategoryRequest $request, int $group_id): JsonResponse
    {
        $group = CategoryGroupModel::find($group_id);

        if (!$group instanceof CategoryGroupModel) {
            abort(404);
        }

        $category = app(CreateNewCategory::class)->create(
            array_merge($request->validated(), ['group_id' => $group_id])
        );

        $category->load(['fieldValues.field.fieldType']);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/category-groups/{group_id}/categories/{category}',
        operationId: 'getCategory',
        summary: 'Get a single category',
        description: 'Returns a category with its dynamic field values and immediate children.',
        security: [['sanctum' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'group_id',
                in: 'path',
                required: true,
                description: 'ID of the category group',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'category',
                in: 'path',
                required: true,
                description: 'ID of the category',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Category'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $group_id, int $category): CategoryResource
    {
        if (!$this->can('read categories')) {
            abort(404);
        }

        $cat = CategoryModel::with(['fieldValues.field.fieldType', 'children'])
            ->where('group_id', $group_id)
            ->find($category);

        if (!$cat instanceof CategoryModel) {
            abort(404);
        }

        return new CategoryResource($cat);
    }

    // -------------------------------------------------------------------------
    // update — reuses existing App\Http\Requests\Category\EditCategoryRequest
    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/v1/category-groups/{group_id}/categories/{category}',
        operationId: 'updateCategory',
        summary: 'Update a category',
        description: 'Updates a category\'s core attributes and/or its dynamic field values. Any `fields` keys sent will overwrite the stored value for that handle; omit a key to leave it unchanged.',
        security: [['sanctum' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'group_id',
                in: 'path',
                required: true,
                description: 'ID of the category group',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'category',
                in: 'path',
                required: true,
                description: 'ID of the category to update',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Display name', example: 'PHP'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255, nullable: true, description: 'URL-safe identifier'),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true, description: 'ID of the parent category (cannot be a descendant of this category)'),
                    new OA\Property(property: 'sort_order', type: 'integer', nullable: true, description: 'Display sort position'),
                    new OA\Property(
                        property: 'fields',
                        type: 'object',
                        nullable: true,
                        description: 'Dynamic field values keyed by handle',
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Category updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Category'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(EditCategoryRequest $request, int $group_id, int $category): CategoryResource
    {
        $cat = CategoryModel::where('group_id', $group_id)->find($category);

        if (!$cat instanceof CategoryModel) {
            abort(404);
        }

        $cat = app(EditCategory::class)->edit($cat, $request->validated());

        $cat->load(['fieldValues.field.fieldType', 'children']);

        return new CategoryResource($cat);
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/v1/category-groups/{group_id}/categories/{category}',
        operationId: 'deleteCategory',
        summary: 'Delete a category',
        description: 'Permanently deletes the category. Child categories are not automatically deleted; reassign or delete them first.',
        security: [['sanctum' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'group_id',
                in: 'path',
                required: true,
                description: 'ID of the category group',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'category',
                in: 'path',
                required: true,
                description: 'ID of the category to delete',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(int $group_id, int $category): JsonResponse
    {
        if (!$this->can('delete category')) {
            abort(403);
        }

        $cat = CategoryModel::where('group_id', $group_id)->find($category);

        if (!$cat instanceof CategoryModel) {
            abort(404);
        }

        $cat->delete();

        return response()->json(null, 204);
    }
}
