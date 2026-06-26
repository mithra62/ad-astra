<?php

namespace App\Http\Controllers\Api\v1;

use App\Actions\Category\Group\CreateNewCategoryGroup;
use App\Actions\Category\Group\EditCategoryGroup;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Category\Group\EditCategoryGroupRequest;
use App\Http\Requests\Category\Group\StoreCategoryGroupRequest;
use App\Http\Resources\Api\CategoryGroupCollection;
use App\Http\Resources\Api\CategoryGroupResource;
use App\Models\Category\Group as CategoryGroupModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Category Groups',
    description: 'Manage category groups. A category group is a named container for a set of categories and defines the field layout that all categories in the group share.'
)]
class CategoryGroups extends Controller
{
    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/category-groups',
        operationId: 'getCategoryGroups',
        summary: 'List all category groups',
        description: 'Returns a paginated list of category groups, each including the total number of categories it contains.',
        security: [['sanctum' => []]],
        tags: ['Category Groups'],
        parameters: [
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
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CategoryGroup')),
                        new OA\Property(property: 'meta', type: 'array', items: new OA\Items(ref: '#/components/schemas/Meta')),
                        new OA\Property(property: 'links', type: 'array', items: new OA\Items(ref: '#/components/schemas/Links')),
                        new OA\Property(property: 'pagination_info', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaginationInfo')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): CategoryGroupCollection
    {
        if (!$this->can('read category groups')) {
            abort(404);
        }

        $query = CategoryGroupModel::withCount('categories');

        $query->orderBy(
            $this->sort($request, ['id', 'name', 'created_at', 'updated_at']),
            $this->sortDir($request),
        );

        return new CategoryGroupCollection($query->paginate($this->limit($request)));
    }

    // -------------------------------------------------------------------------
    // store — reuses existing App\Http\Requests\Category\Group\StoreCategoryGroupRequest
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/v1/category-groups',
        operationId: 'createCategoryGroup',
        summary: 'Create a new category group',
        description: 'Creates a category group along with its backing field layout. Optionally attach existing field groups to define custom fields for categories in this group.',
        security: [['sanctum' => []]],
        tags: ['Category Groups'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'handle'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Human-readable name (must be unique)', example: 'Blog Tags'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255, description: 'URL-safe identifier (must be unique)', example: 'blog-tags'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Category group created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/CategoryGroup'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreCategoryGroupRequest $request): JsonResponse
    {
        $group = app(CreateNewCategoryGroup::class)->create($request->validated());

        return (new CategoryGroupResource($group->loadCount('categories')))
            ->response()
            ->setStatusCode(201);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/category-groups/{group}',
        operationId: 'getCategoryGroup',
        summary: 'Get a single category group',
        security: [['sanctum' => []]],
        tags: ['Category Groups'],
        parameters: [
            new OA\Parameter(
                name: 'group',
                in: 'path',
                required: true,
                description: 'ID of the category group',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/CategoryGroup'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $group): CategoryGroupResource
    {
        if (!$this->can('read category groups')) {
            abort(404);
        }

        $categoryGroup = CategoryGroupModel::withCount('categories')->find($group);

        if (!$categoryGroup instanceof CategoryGroupModel) {
            abort(404);
        }

        return new CategoryGroupResource($categoryGroup);
    }

    // -------------------------------------------------------------------------
    // update — reuses existing App\Http\Requests\Category\Group\EditCategoryGroupRequest
    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/v1/category-groups/{group}',
        operationId: 'updateCategoryGroup',
        summary: 'Update a category group',
        security: [['sanctum' => []]],
        tags: ['Category Groups'],
        parameters: [
            new OA\Parameter(
                name: 'group',
                in: 'path',
                required: true,
                description: 'ID of the category group to update',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'handle'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Human-readable name (must be unique)', example: 'Blog Tags'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255, description: 'URL-safe identifier (must be unique)', example: 'blog-tags'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Category group updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/CategoryGroup'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(EditCategoryGroupRequest $request, int $group): CategoryGroupResource
    {
        $categoryGroup = CategoryGroupModel::find($group);

        if (!$categoryGroup instanceof CategoryGroupModel) {
            abort(404);
        }

        app(EditCategoryGroup::class)->edit($categoryGroup, $request->validated());

        return new CategoryGroupResource($categoryGroup->refresh()->loadCount('categories'));
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/v1/category-groups/{group}',
        operationId: 'deleteCategoryGroup',
        summary: 'Delete a category group',
        description: 'Permanently deletes the category group. All categories belonging to this group will also be deleted.',
        security: [['sanctum' => []]],
        tags: ['Category Groups'],
        parameters: [
            new OA\Parameter(
                name: 'group',
                in: 'path',
                required: true,
                description: 'ID of the category group to delete',
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
    public function destroy(int $group): JsonResponse
    {
        if (!$this->can('delete category group')) {
            abort(403);
        }

        $categoryGroup = CategoryGroupModel::find($group);

        if (!$categoryGroup instanceof CategoryGroupModel) {
            abort(404);
        }

        $categoryGroup->delete();

        return response()->json(null, 204);
    }
}
