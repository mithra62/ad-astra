<?php

namespace App\Http\Controllers\Api\v1;

use App\Facades\EntryGroups as EntryGroupsFacade;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Entry\Group\EditEntryGroupRequest;
use App\Http\Requests\Entry\Group\StoreEntryGroupRequest;
use App\Http\Resources\Api\EntryGroupCollection;
use App\Http\Resources\Api\EntryGroupResource;
use App\Models\EntryGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Entry Groups',
    description: 'Manage entry groups. An entry group is a named collection of entries with a shared status group, field layout, and optional category groups.'
)]
class EntryGroups extends Controller
{
    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/entry-groups',
        operationId: 'getEntryGroups',
        summary: 'List all entry groups',
        description: 'Returns a paginated list of entry groups including entry and entry-type counts.',
        security: [['sanctum' => []]],
        tags: ['Entry Groups'],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Results per page (max 100)', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Column to sort by', schema: new OA\Schema(type: 'string', default: 'id')),
            new OA\Parameter(name: 'direction', in: 'query', required: false, description: 'Sort direction', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EntryGroup')),
                        new OA\Property(property: 'meta', type: 'array', items: new OA\Items(ref: '#/components/schemas/Meta')),
                        new OA\Property(property: 'links', type: 'array', items: new OA\Items(ref: '#/components/schemas/Links')),
                        new OA\Property(property: 'pagination_info', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaginationInfo')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): EntryGroupCollection
    {
        if (!$this->can('read entry groups')) {
            abort(404);
        }

        $query = EntryGroup::withCount(['entries', 'entryTypes']);

        if ($this->sort($request) && $this->sortDir($request)) {
            $query->orderBy($this->sort($request), $this->sortDir($request));
        }

        return new EntryGroupCollection($query->paginate($this->limit($request)));
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/v1/entry-groups',
        operationId: 'createEntryGroup',
        summary: 'Create a new entry group',
        security: [['sanctum' => []]],
        tags: ['Entry Groups'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'handle', 'status_group_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Human-readable name', example: 'Blog Posts'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255, description: 'URL-safe identifier (must be unique)', example: 'blog-posts'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, description: 'Optional description'),
                    new OA\Property(property: 'sort_order', type: 'integer', nullable: true, description: 'Display sort position', example: 1),
                    new OA\Property(property: 'status_group_id', type: 'integer', description: 'ID of the status group to use', example: 1),
                    new OA\Property(property: 'field_layout_id', type: 'integer', nullable: true, description: 'ID of an optional field layout'),
                    new OA\Property(property: 'category_groups', type: 'array', nullable: true, items: new OA\Items(type: 'integer'), description: 'IDs of category groups to attach'),
                    new OA\Property(property: 'field_groups', type: 'array', nullable: true, items: new OA\Items(type: 'integer'), description: 'IDs of field groups to attach'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Entry group created',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EntryGroup')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreEntryGroupRequest $request): JsonResponse
    {
        $group = EntryGroupsFacade::create($request->validated());

        return (new EntryGroupResource($group->loadCount(['entries', 'entryTypes'])))
            ->response()
            ->setStatusCode(201);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/entry-groups/{group}',
        operationId: 'getEntryGroup',
        summary: 'Get a single entry group',
        security: [['sanctum' => []]],
        tags: ['Entry Groups'],
        parameters: [
            new OA\Parameter(name: 'group', in: 'path', required: true, description: 'ID of the entry group', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EntryGroup')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $group): EntryGroupResource
    {
        if (!$this->can('read entry groups')) {
            abort(404);
        }

        $model = EntryGroup::withCount(['entries', 'entryTypes'])->find($group);

        if (!$model instanceof EntryGroup) {
            abort(404);
        }

        return new EntryGroupResource($model);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/v1/entry-groups/{group}',
        operationId: 'updateEntryGroup',
        summary: 'Update an entry group',
        security: [['sanctum' => []]],
        tags: ['Entry Groups'],
        parameters: [
            new OA\Parameter(name: 'group', in: 'path', required: true, description: 'ID of the entry group to update', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'handle', 'status_group_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Blog Posts'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255, example: 'blog-posts'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'sort_order', type: 'integer', nullable: true),
                    new OA\Property(property: 'status_group_id', type: 'integer', example: 1),
                    new OA\Property(property: 'field_layout_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'category_groups', type: 'array', nullable: true, items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'field_groups', type: 'array', nullable: true, items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Entry group updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EntryGroup')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(EditEntryGroupRequest $request, int $group): EntryGroupResource
    {
        $model = EntryGroup::find($group);

        if (!$model instanceof EntryGroup) {
            abort(404);
        }

        EntryGroupsFacade::update($model, $request->validated());

        return new EntryGroupResource($model->refresh()->loadCount(['entries', 'entryTypes']));
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/v1/entry-groups/{group}',
        operationId: 'deleteEntryGroup',
        summary: 'Delete an entry group',
        description: 'Permanently deletes the entry group and all of its entries.',
        security: [['sanctum' => []]],
        tags: ['Entry Groups'],
        parameters: [
            new OA\Parameter(name: 'group', in: 'path', required: true, description: 'ID of the entry group to delete', schema: new OA\Schema(type: 'integer')),
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
        if (!$this->can('delete entry group')) {
            abort(403);
        }

        $model = EntryGroup::find($group);

        if (!$model instanceof EntryGroup) {
            abort(404);
        }

        EntryGroupsFacade::delete($model);

        return response()->json(null, 204);
    }
}
