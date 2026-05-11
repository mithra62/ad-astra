<?php

namespace App\Http\Controllers\Api\v1;

use App\Actions\Status\CreateNewStatus;
use App\Actions\Status\EditStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Status\EditStatusRequest;
use App\Http\Requests\Status\StoreStatusRequest;
use App\Http\Resources\Api\StatusCollection;
use App\Http\Resources\Api\StatusResource;
use App\Models\Status as StatusModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Statuses',
    description: 'Manage individual statuses within a status group. Setting is_default=true on a status will automatically unset the previous default in the same group.'
)]
class Statuses extends Controller
{
    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/statuses',
        operationId: 'getStatuses',
        summary: 'List statuses',
        description: 'Returns a paginated list of statuses. Filter by status group using the status_group_id query parameter.',
        security: [['sanctum' => []]],
        tags: ['Statuses'],
        parameters: [
            new OA\Parameter(
                name: 'status_group_id',
                in: 'query',
                required: false,
                description: 'Filter results to a specific status group',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Results per page (max 100)', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Column to sort by', schema: new OA\Schema(type: 'string', default: 'id')),
            new OA\Parameter(name: 'direction', in: 'query', required: false, description: 'Sort direction', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')),
            new OA\Parameter(name: 'created_after', in: 'query', required: false, description: 'Filter to statuses created after this date', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'created_before', in: 'query', required: false, description: 'Filter to statuses created before this date', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Status')),
                        new OA\Property(property: 'meta', type: 'array', items: new OA\Items(ref: '#/components/schemas/Meta')),
                        new OA\Property(property: 'links', type: 'array', items: new OA\Items(ref: '#/components/schemas/Links')),
                        new OA\Property(property: 'pagination_info', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaginationInfo')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): StatusCollection
    {
        if (!$this->can('read statuses')) {
            abort(404);
        }

        $query = StatusModel::query();

        if ($groupId = $request->integer('status_group_id') ?: null) {
            $query->where('status_group_id', $groupId);
        }

        $where = $this->buildWhere([], $request);
        foreach ($where as $condition) {
            $query->where(...$condition);
        }

        $query->orderBy(
            $this->sort($request, ['id', 'name', 'handle', 'sort_order', 'created_at', 'updated_at']),
            $this->sortDir($request),
        );

        return new StatusCollection($query->paginate($this->limit($request)));
    }

    // -------------------------------------------------------------------------
    // store — reuses App\Http\Requests\Status\StoreStatusRequest
    //   status_group_id must be provided in the request body.
    //   The UniqueHandleByGroup rule falls back to input('status_group_id')
    //   when no {group_id} route param is present (fixed in the request).
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/v1/statuses',
        operationId: 'createStatus',
        summary: 'Create a new status',
        description: 'Creates a status within the specified status group. If is_default is true, the previous default in the same group is automatically cleared.',
        security: [['sanctum' => []]],
        tags: ['Statuses'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'handle', 'status_group_id'],
                properties: [
                    new OA\Property(property: 'status_group_id', type: 'integer', description: 'ID of the status group this status belongs to', example: 1),
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Display name', example: 'Draft'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255, description: 'URL-safe identifier, unique within the group', example: 'draft'),
                    new OA\Property(property: 'color', type: 'string', maxLength: 20, nullable: true, description: 'Optional display colour', example: '#aaaaaa'),
                    new OA\Property(property: 'is_default', type: 'boolean', nullable: true, description: 'Set as the default status for the group (clears any existing default)', example: false),
                    new OA\Property(property: 'is_public', type: 'boolean', nullable: true, description: 'Whether this status is publicly visible', example: false),
                    new OA\Property(property: 'sort_order', type: 'integer', nullable: true, description: 'Sort position within the group', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Status created',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Status')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreStatusRequest $request): JsonResponse
    {
        $status = app(CreateNewStatus::class)->createByGroup($request->validated());

        return (new StatusResource($status))
            ->response()
            ->setStatusCode(201);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/statuses/{status}',
        operationId: 'getStatus',
        summary: 'Get a single status',
        security: [['sanctum' => []]],
        tags: ['Statuses'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'path', required: true, description: 'ID of the status', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Status')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $status): StatusResource
    {
        if (!$this->can('read statuses')) {
            abort(404);
        }

        $model = StatusModel::find($status);

        if (!$model instanceof StatusModel) {
            abort(404);
        }

        return new StatusResource($model);
    }

    // -------------------------------------------------------------------------
    // update — reuses App\Http\Requests\Status\EditStatusRequest
    //   (uses route('status') for UniqueHandleByGroup — matches our {status} param)
    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/v1/statuses/{status}',
        operationId: 'updateStatus',
        summary: 'Update a status',
        description: 'Updates a status. If is_default is set to true, the previous default in the same group is automatically cleared.',
        security: [['sanctum' => []]],
        tags: ['Statuses'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'path', required: true, description: 'ID of the status to update', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'handle'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Published'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255, example: 'published'),
                    new OA\Property(property: 'color', type: 'string', maxLength: 20, nullable: true, example: '#22c55e'),
                    new OA\Property(property: 'is_default', type: 'boolean', nullable: true),
                    new OA\Property(property: 'is_public', type: 'boolean', nullable: true),
                    new OA\Property(property: 'sort_order', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Status')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(EditStatusRequest $request, int $status): StatusResource
    {
        $model = StatusModel::find($status);

        if (!$model instanceof StatusModel) {
            abort(404);
        }

        app(EditStatus::class)->edit($model, $request->validated());

        return new StatusResource($model->refresh());
    }

    // -------------------------------------------------------------------------
    // destroy — DeleteStatusRequest requires confirm_removal (UI gate only)
    //   so authorization is handled inline here
    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/v1/statuses/{status}',
        operationId: 'deleteStatus',
        summary: 'Delete a status',
        description: 'Permanently deletes the status.',
        security: [['sanctum' => []]],
        tags: ['Statuses'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'path', required: true, description: 'ID of the status to delete', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(int $status): JsonResponse
    {
        if (!$this->can('delete status')) {
            abort(403);
        }

        $model = StatusModel::find($status);

        if (!$model instanceof StatusModel) {
            abort(404);
        }

        $model->delete();

        return response()->json(null, 204);
    }
}
