<?php

namespace App\Http\Controllers\Api\v1;

use App\Actions\Status\Group\CreateNewStatusGroup;
use App\Actions\Status\Group\EditStatusGroup;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Status\Group\EditStatusGroupRequest;
use App\Http\Requests\Status\Group\StoreStatusGroupRequest;
use App\Http\Resources\Api\StatusGroupCollection;
use App\Http\Resources\Api\StatusGroupResource;
use App\Models\StatusGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Status Groups',
    description: 'Manage status groups. A status group is a named collection of statuses (e.g. "Publishing Workflow") that can be assigned to entry groups.'
)]
class StatusGroups extends Controller
{
    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/status-groups',
        operationId: 'getStatusGroups',
        summary: 'List all status groups',
        description: 'Returns a paginated list of status groups, each including the total number of statuses it contains.',
        security: [['sanctum' => []]],
        tags: ['Status Groups'],
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
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/StatusGroup')),
                        new OA\Property(property: 'meta', type: 'array', items: new OA\Items(ref: '#/components/schemas/Meta')),
                        new OA\Property(property: 'links', type: 'array', items: new OA\Items(ref: '#/components/schemas/Links')),
                        new OA\Property(property: 'pagination_info', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaginationInfo')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): StatusGroupCollection
    {
        if (!$this->can('read status groups')) {
            abort(404);
        }

        $query = StatusGroup::withCount('statuses');

        if ($this->sort($request) && $this->sortDir($request)) {
            $query->orderBy($this->sort($request), $this->sortDir($request));
        }

        return new StatusGroupCollection($query->paginate($this->limit($request)));
    }

    // -------------------------------------------------------------------------
    // store — reuses App\Http\Requests\Status\Group\StoreStatusGroupRequest
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/v1/status-groups',
        operationId: 'createStatusGroup',
        summary: 'Create a new status group',
        security: [['sanctum' => []]],
        tags: ['Status Groups'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'handle'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Human-readable name (must be unique)', example: 'Publishing Workflow'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255, description: 'URL-safe identifier (must be unique)', example: 'publishing-workflow'),
                    new OA\Property(property: 'sort_order', type: 'integer', nullable: true, description: 'Display sort position', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Status group created',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/StatusGroup')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreStatusGroupRequest $request): JsonResponse
    {
        $group = app(CreateNewStatusGroup::class)->create($request->validated());

        return (new StatusGroupResource($group->loadCount('statuses')))
            ->response()
            ->setStatusCode(201);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/status-groups/{group}',
        operationId: 'getStatusGroup',
        summary: 'Get a single status group',
        security: [['sanctum' => []]],
        tags: ['Status Groups'],
        parameters: [
            new OA\Parameter(name: 'group', in: 'path', required: true, description: 'ID of the status group', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/StatusGroup')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $group): StatusGroupResource
    {
        if (!$this->can('read status groups')) {
            abort(404);
        }

        $statusGroup = StatusGroup::withCount('statuses')->find($group);

        if (!$statusGroup instanceof StatusGroup) {
            abort(404);
        }

        return new StatusGroupResource($statusGroup);
    }

    // -------------------------------------------------------------------------
    // update — reuses App\Http\Requests\Status\Group\EditStatusGroupRequest
    //   (uses route('group') for unique ignore — matches our {group} param)
    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/v1/status-groups/{group}',
        operationId: 'updateStatusGroup',
        summary: 'Update a status group',
        security: [['sanctum' => []]],
        tags: ['Status Groups'],
        parameters: [
            new OA\Parameter(name: 'group', in: 'path', required: true, description: 'ID of the status group to update', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'handle'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Publishing Workflow'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255, example: 'publishing-workflow'),
                    new OA\Property(property: 'sort_order', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status group updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/StatusGroup')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(EditStatusGroupRequest $request, int $group): StatusGroupResource
    {
        $statusGroup = StatusGroup::find($group);

        if (!$statusGroup instanceof StatusGroup) {
            abort(404);
        }

        app(EditStatusGroup::class)->edit($statusGroup, $request->validated());

        return new StatusGroupResource($statusGroup->refresh()->loadCount('statuses'));
    }

    // -------------------------------------------------------------------------
    // destroy — DeleteStatusGroupRequest requires confirm_removal (UI gate only)
    //   so authorization is handled inline here
    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/v1/status-groups/{group}',
        operationId: 'deleteStatusGroup',
        summary: 'Delete a status group',
        description: 'Permanently deletes the status group and all of its statuses.',
        security: [['sanctum' => []]],
        tags: ['Status Groups'],
        parameters: [
            new OA\Parameter(name: 'group', in: 'path', required: true, description: 'ID of the status group to delete', schema: new OA\Schema(type: 'integer')),
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
        if (!$this->can('delete status')) {
            abort(403);
        }

        $statusGroup = StatusGroup::find($group);

        if (!$statusGroup instanceof StatusGroup) {
            abort(404);
        }

        $statusGroup->delete();

        return response()->json(null, 204);
    }
}
