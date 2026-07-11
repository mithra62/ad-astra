<?php

namespace AdAstra\Http\Controllers\Api\v1;

use AdAstra\Actions\Entry\CreateNewEntry;
use AdAstra\Actions\Entry\UpdateEntry;
use AdAstra\Facades\Content;
use AdAstra\Http\Controllers\Api\Controller;
use AdAstra\Http\Requests\Entry\EditEntryRequest;
use AdAstra\Http\Requests\Entry\StoreEntryRequest;
use AdAstra\Http\Resources\Api\EntryCollection;
use AdAstra\Http\Resources\Api\EntryResource;
use AdAstra\Models\Entry as EntryModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Entries',
    description: 'Manage content entries within an entry group. Entries support dynamic field values, authors, categories, and status.'
)]
class Entries extends Controller
{
    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/entry-groups/{group_id}/entries',
        operationId: 'getEntries',
        summary: 'List entries in a group',
        description: 'Returns a paginated list of entries belonging to the specified entry group.',
        security: [['sanctum' => []]],
        tags: ['Entries'],
        parameters: [
            new OA\Parameter(name: 'group_id', in: 'path', required: true, description: 'ID of the entry group', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Results per page (max 100)', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Column to sort by', schema: new OA\Schema(type: 'string', default: 'id')),
            new OA\Parameter(name: 'direction', in: 'query', required: false, description: 'Sort direction', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')),
            new OA\Parameter(name: 'created_after', in: 'query', required: false, description: 'Filter to entries created after this date', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'created_before', in: 'query', required: false, description: 'Filter to entries created before this date', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Entry')),
                        new OA\Property(property: 'meta', type: 'array', items: new OA\Items(ref: '#/components/schemas/Meta')),
                        new OA\Property(property: 'links', type: 'array', items: new OA\Items(ref: '#/components/schemas/Links')),
                        new OA\Property(property: 'pagination_info', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaginationInfo')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Entry group not found, or no entries present'),
        ]
    )]
    public function index(Request $request, int $group_id): EntryCollection
    {
        if (!$this->can('read entries')) {
            abort(404);
        }

        $query = EntryModel::query()
            ->where('entry_group_id', $group_id)
            ->with(['fieldValues', 'authors', 'categories']);

        $where = $this->buildWhere([], $request);
        foreach ($where as $condition) {
            $query->where(...$condition);
        }

        $query->orderBy(
            $this->sort($request, ['id', 'title', 'handle', 'published_at', 'created_at', 'updated_at']),
            $this->sortDir($request),
        );

        $entries = $query->paginate($this->limit($request));

        if ($entries->isEmpty()) {
            abort(404);
        }

        return new EntryCollection($entries);
    }

    // -------------------------------------------------------------------------
    // store — uses StoreEntryRequest which resolves field schema from {group_id}
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/v1/entry-groups/{group_id}/entries',
        operationId: 'createEntry',
        summary: 'Create a new entry',
        description: 'Creates an entry within the specified entry group. Field validation rules are resolved dynamically from the group and entry type schemas. The type_handle must belong to this group.',
        security: [['sanctum' => []]],
        tags: ['Entries'],
        parameters: [
            new OA\Parameter(name: 'group_id', in: 'path', required: true, description: 'ID of the entry group', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type_handle', 'title', 'handle'],
                properties: [
                    new OA\Property(property: 'type_handle', type: 'string', description: 'Handle of the entry type (must belong to this group)', example: 'article'),
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, description: 'Entry title', example: 'My First Post'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255, description: 'URL-safe identifier within the group'),
                    new OA\Property(property: 'status', type: 'string', nullable: true, description: 'Status handle (must exist in the group status group)', example: 'draft'),
                    new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'authors', type: 'array', nullable: true, items: new OA\Items(type: 'integer'), description: 'User IDs to assign as authors'),
                    new OA\Property(property: 'categories', type: 'array', nullable: true, items: new OA\Items(type: 'integer'), description: 'Category IDs to assign'),
                    new OA\Property(property: 'fields', type: 'object', nullable: true, description: 'Dynamic field values keyed by field handle', additionalProperties: new OA\AdditionalProperties(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Entry created',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Entry')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreEntryRequest $request): JsonResponse
    {
        $entry = app(CreateNewEntry::class)->create($request->validated());

        return (new EntryResource(Content::find($entry->id)))
            ->response()
            ->setStatusCode(201);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/entry-groups/{group_id}/entries/{entry}',
        operationId: 'getEntry',
        summary: 'Get a single entry',
        security: [['sanctum' => []]],
        tags: ['Entries'],
        parameters: [
            new OA\Parameter(name: 'group_id', in: 'path', required: true, description: 'ID of the entry group', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'entry', in: 'path', required: true, description: 'ID of the entry', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Entry')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $group_id, int $entry): EntryResource
    {
        if (!$this->can('read entries')) {
            abort(404);
        }

        $model = Content::find($entry);

        if (!$model instanceof EntryModel || $model->entry_group_id !== $group_id) {
            abort(404);
        }

        return new EntryResource($model);
    }

    // -------------------------------------------------------------------------
    // update — EditEntryRequest resolves field schema from the entry itself
    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/v1/entry-groups/{group_id}/entries/{entry}',
        operationId: 'updateEntry',
        summary: 'Update an entry',
        description: "Updates an entry's core fields and/or dynamic field values. Field validation rules are resolved dynamically from the entry's group and type.",
        security: [['sanctum' => []]],
        tags: ['Entries'],
        parameters: [
            new OA\Parameter(name: 'group_id', in: 'path', required: true, description: 'ID of the entry group', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'entry', in: 'path', required: true, description: 'ID of the entry to update', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'handle'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Updated Title'),
                    new OA\Property(property: 'handle', type: 'string', maxLength: 255),
                    new OA\Property(property: 'status', type: 'string', nullable: true, description: 'Status handle'),
                    new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'authors', type: 'array', nullable: true, items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'categories', type: 'array', nullable: true, items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'fields', type: 'object', nullable: true, additionalProperties: new OA\AdditionalProperties(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Entry updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Entry')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(EditEntryRequest $request, int $group_id, int $entry): EntryResource
    {
        $model = EntryModel::find($entry);

        if (!$model instanceof EntryModel || $model->entry_group_id !== $group_id) {
            abort(404);
        }

        app(UpdateEntry::class)->update($model, $request->validated());

        return new EntryResource(Content::find($entry));
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/v1/entry-groups/{group_id}/entries/{entry}',
        operationId: 'deleteEntry',
        summary: 'Delete an entry',
        description: 'Permanently deletes the entry and its field values.',
        security: [['sanctum' => []]],
        tags: ['Entries'],
        parameters: [
            new OA\Parameter(name: 'group_id', in: 'path', required: true, description: 'ID of the entry group', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'entry', in: 'path', required: true, description: 'ID of the entry to delete', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(int $group_id, int $entry): JsonResponse
    {
        if (!$this->can('delete entry')) {
            abort(403);
        }

        $model = EntryModel::find($entry);

        if (!$model instanceof EntryModel || $model->entry_group_id !== $group_id) {
            abort(404);
        }

        Content::delete($model);

        return response()->json(null, 204);
    }
}
