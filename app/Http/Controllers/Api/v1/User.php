<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Http\Resources\Api\UserCollection;
use App\Http\Resources\Api\UserResource;
use App\Http\Controllers\Api\Controller;

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
     *            @OA\Schema(type="date", format="YYYY-MM-DD")
     *      ),
     *      @OA\Parameter(
     *             name="created_before",
     *             in="query",
     *             description="Retrieve submissions that were before after a specific date",
     *             required=false,
     *             @OA\Schema(type="date", format="YYYY-MM-DD")
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
        return ['fdsa'];
    }

    public function show($id)
    {

    }
}
