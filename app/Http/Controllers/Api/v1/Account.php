<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Account\EditUserRequest;

class Account extends Controller
{
    public function update(EditUserRequest $request)
    {
        return response()->json(['message' => 'Account information updated successfully'], 200);
    }

    public function updatePassword()
    {
        return response()->json(['message' => 'Password updated successfully'], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/account",
     *      operationId="getAccount",
     *      tags={"Account"},
     *      summary="Get details on the authenticated user",
     *      security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function show()
    {
        return response()->json(['message' => 'Profile updated successfully'], 200);
    }

    public function updateAvatar()
    {
        return response()->json(['message' => 'Avatar updated successfully'], 200);
    }

    public function updateEmail()
    {
        return response()->json(['message' => 'Email updated successfully'], 200);
    }

}
