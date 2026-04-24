<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Http\Resources\Api\UserCollection;
use App\Http\Resources\Api\UserResource;
use App\Http\Controllers\Api\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\User as UserModel;

class User extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v1/users",
     *      operationId="getAllUsers",
     *      tags={"Users"},
     *      summary="Get a paginated list of Users",
     *      security={{"sanctum": {}}},
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="data",type="array",description="Users",@OA\Items(ref="#/components/schemas/User")),
     *              @OA\Property(property="meta",type="array",description="",@OA\Items(ref="#/components/schemas/Meta")),
     *              @OA\Property(property="links",type="array",description="",@OA\Items(ref="#/components/schemas/Links")),
     *              @OA\Property(property="pagination_info",type="array",description="",@OA\Items(ref="#/components/schemas/PaginationInfo")),
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="limit",
     *          in="query",
     *          description="Number of items per page",
     *          required=false,
     *          @OA\Schema(type="integer", default=10)
     *      ),
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Starting page number",
     *          required=false,
     *          @OA\Schema(type="integer", default=1)
     *      ),
     *      @OA\Parameter(
     *            name="created_after",
     *            in="query",
     *            description="Retrieve submissions that were created after a specific date",
     *            required=false,
     *            @OA\Schema(type="string", format="date")
     *      ),
     *      @OA\Parameter(
     *             name="created_before",
     *             in="query",
     *             description="Retrieve submissions that were before after a specific date",
     *             required=false,
     *             @OA\Schema(type="string", format="date")
     *      ),
     *      @OA\Parameter(
     *              name="sort",
     *              in="query",
     *              description="How you want to order results",
     *              required=false,
     *              @OA\Schema(type="string", default="created_at")
     *      ),
     *      @OA\Parameter(
     *              name="direction",
     *              in="query",
     *              description="The direction for sorted orders (asc, desc)",
     *              required=false,
     *              @OA\Schema(type="string", default="asc")
     *          ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *      )
     * )
     */
    public function index(Request $request)
    {
        if (!$this->can('read users')) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        $query = UserModel::with('roles');
        if($this->sortDir($request) && $this->sort($request)) {
            $query->orderBy($this->sort($request), $this->sortDir($request));
        }

        $submissions = $query->paginate($this->limit($request));
        return new UserCollection($submissions);
    }


    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *      operationId="getUser",
     *      tags={"Users"},
     *      summary="Get details on a specific User",
     *      security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user to retrieve",
     *         @OA\Schema(type="integer")
     *     ),
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
    public function show($id)
    {
        if (!$this->can('read users') || Auth::user()->id === $id) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        $user = UserModel::find($id);
        if (!$user instanceof UserModel) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        return new UserResource($user);
    }
}
