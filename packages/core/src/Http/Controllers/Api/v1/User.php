<?php

namespace AdAstra\Http\Controllers\Api\v1;

use AdAstra\Facades\Users;
use AdAstra\Http\Controllers\Api\Controller;
use AdAstra\Http\Requests\User\EditUserRequest;
use AdAstra\Http\Requests\User\StoreUserRequest;
use AdAstra\Http\Resources\Api\UserCollection;
use AdAstra\Http\Resources\Api\UserResource;
use AdAstra\Models\User as UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Users',
    description: 'Manage user accounts, roles, and custom field values.'
)]
class User extends Controller
{
    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/users',
        operationId: 'getUsers',
        summary: 'List users',
        description: 'Returns a paginated list of users including their roles and custom field values.',
        security: [['sanctum' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Results per page (max 100)', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Column to sort by', schema: new OA\Schema(type: 'string', default: 'id')),
            new OA\Parameter(name: 'direction', in: 'query', required: false, description: 'Sort direction', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')),
            new OA\Parameter(name: 'created_after', in: 'query', required: false, description: 'Filter to users created after this date', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'created_before', in: 'query', required: false, description: 'Filter to users created before this date', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
                        new OA\Property(property: 'meta', type: 'array', items: new OA\Items(ref: '#/components/schemas/Meta')),
                        new OA\Property(property: 'links', type: 'array', items: new OA\Items(ref: '#/components/schemas/Links')),
                        new OA\Property(property: 'pagination_info', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaginationInfo')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): UserCollection
    {
        if (!$this->can('read user')) {
            abort(404);
        }

        $query = UserModel::with(['roles', 'fieldValues']);

        $where = $this->buildWhere([], $request);
        foreach ($where as $condition) {
            $query->where(...$condition);
        }

        $query->orderBy(
            $this->sort($request, ['id', 'name', 'email', 'created_at', 'updated_at']),
            $this->sortDir($request),
        );

        return new UserCollection($query->paginate($this->limit($request)));
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/v1/users',
        operationId: 'createUser',
        summary: 'Create a new user',
        description: 'Creates a user account and optionally assigns roles and custom field values.',
        security: [['sanctum' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Display name', example: 'Jane Smith'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, description: 'Email address (must be unique)', example: 'jane@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, description: 'Password', example: 'secret1234'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', description: 'Must match password', example: 'secret1234'),
                    new OA\Property(property: 'roles', type: 'array', nullable: true, items: new OA\Items(type: 'string'), description: 'Role names to assign', example: ['editor']),
                    new OA\Property(property: 'fields', type: 'object', nullable: true, description: 'Custom field values keyed by handle', additionalProperties: new OA\AdditionalProperties(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        if (!$this->can('create user')) {
            abort(403);
        }

        $user = Users::create($request->validated());

        return (new UserResource($user->load(['roles', 'fieldValues'])))
            ->response()
            ->setStatusCode(201);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/users/{user}',
        operationId: 'getUser',
        summary: 'Get a single user',
        security: [['sanctum' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, description: 'ID of the user', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $user): UserResource
    {
        if (!$this->can('read user')) {
            abort(404);
        }

        $model = UserModel::with(['roles', 'fieldValues'])->find($user);

        if (!$model instanceof UserModel) {
            abort(404);
        }

        return new UserResource($model);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/v1/users/{user}',
        operationId: 'updateUser',
        summary: 'Update a user',
        description: "Updates a user's name, email, password, roles, and/or custom field values. Only provided keys are changed.",
        security: [['sanctum' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, description: 'ID of the user to update', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Jane Smith'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'jane@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, nullable: true, description: 'Leave blank to keep existing password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', nullable: true),
                    new OA\Property(property: 'roles', type: 'array', nullable: true, items: new OA\Items(type: 'string'), description: 'Replaces all current roles'),
                    new OA\Property(property: 'fields', type: 'object', nullable: true, additionalProperties: new OA\AdditionalProperties(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(EditUserRequest $request, int $user): UserResource
    {
        if (!$this->can('edit user')) {
            abort(403);
        }

        $model = UserModel::find($user);

        if (!$model instanceof UserModel) {
            abort(404);
        }

        Users::update($model, $request->validated());

        return new UserResource($model->refresh()->load(['roles', 'fieldValues']));
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/v1/users/{user}',
        operationId: 'deleteUser',
        summary: 'Delete a user',
        description: 'Permanently deletes the user. A user cannot delete their own account.',
        security: [['sanctum' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, description: 'ID of the user to delete', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(int $user): JsonResponse
    {
        if (!$this->can('delete user')) {
            abort(403);
        }

        // Prevent self-deletion (mirrors DeleteUserRequest::authorize())
        if (Auth::id() === $user) {
            abort(403, 'You cannot delete your own account.');
        }

        $model = UserModel::find($user);

        if (!$model instanceof UserModel) {
            abort(404);
        }

        Users::delete($model);

        return response()->json(null, 204);
    }
}
