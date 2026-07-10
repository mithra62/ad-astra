<?php

namespace AdAstra\Http\Controllers\Api\v1;

use AdAstra\Facades\Users;
use AdAstra\Http\Controllers\Api\Controller;
use AdAstra\Http\Requests\Account\EditPasswordRequest;
use AdAstra\Http\Requests\Account\UpdateAccountRequest;
use AdAstra\Http\Requests\Account\UpdateEmailRequest;
use AdAstra\Http\Resources\Api\UserResource;
use AdAstra\Models\User as UserModel;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Account',
    description: 'Read and manage the authenticated user\'s own account.'
)]
class Account extends Controller
{
    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/v1/account',
        operationId: 'getAccount',
        summary: 'Get the authenticated user',
        description: 'Returns the account of the currently authenticated user, including roles and custom field values.',
        security: [['sanctum' => []]],
        tags: ['Account'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(): UserResource
    {
        /** @var UserModel $user */
        $user = Auth::user();

        return new UserResource($user->load(['roles', 'fieldValues']));
    }

    // -------------------------------------------------------------------------
    // update (name + custom fields)
    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/v1/account',
        operationId: 'updateAccount',
        summary: 'Update the authenticated user\'s profile',
        description: 'Updates the display name and/or custom field values. Email is changed via the dedicated email endpoint.',
        security: [['sanctum' => []]],
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Jane Smith'),
                    new OA\Property(property: 'fields', type: 'object', nullable: true, description: 'Custom field values keyed by handle', additionalProperties: new OA\AdditionalProperties(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateAccountRequest $request): UserResource
    {
        /** @var UserModel $user */
        $user = Auth::user();

        Users::update($user, $request->validated());

        return new UserResource($user->refresh()->load(['roles', 'fieldValues']));
    }

    // -------------------------------------------------------------------------
    // updatePassword
    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/v1/account/password',
        operationId: 'updateAccountPassword',
        summary: 'Change the authenticated user\'s password',
        description: 'Requires the current password. Sets a new password on success.',
        security: [['sanctum' => []]],
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', description: 'The user\'s existing password'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, description: 'New password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', description: 'Must match password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password changed',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updatePassword(EditPasswordRequest $request): UserResource
    {
        /** @var UserModel $user */
        $user = Auth::user();

        Users::setPassword($user, $request->validated()['password']);

        return new UserResource($user->refresh()->load(['roles', 'fieldValues']));
    }

    // -------------------------------------------------------------------------
    // updateEmail
    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/v1/account/email',
        operationId: 'updateAccountEmail',
        summary: 'Change the authenticated user\'s email address',
        description: 'Requires the current password. The new email must be unique.',
        security: [['sanctum' => []]],
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'current_password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'jane@example.com'),
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', description: 'The user\'s existing password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Email changed',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateEmail(UpdateEmailRequest $request): UserResource
    {
        /** @var UserModel $user */
        $user = Auth::user();

        Users::update($user, ['email' => $request->validated()['email']]);

        return new UserResource($user->refresh()->load(['roles', 'fieldValues']));
    }
}
