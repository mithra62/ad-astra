<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Api\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\Api\EntryCollection;
use App\Http\Resources\Api\EntryResource;
use App\Facades\Content;

class Entries extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v1/entries",
     *      operationId="getAllEntries",
     *      tags={"Entries"},
     *      summary="Get a paginated list of Entries",
     *      security={{"sanctum": {}}},
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="data",type="array",description="Users",@OA\Items(ref="#/components/schemas/Entry")),
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
        return response()->json(['message' => 'Content index endpoint']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/entries/{id}",
     *      operationId="getEntry",
     *      tags={"Entries"},
     *      summary="Get details on a specific Entry",
     *      security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the entry to retrieve",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Entry")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $entry = Content::find($id);
        return new EntryResource($entry);
    }

    public function store(Request $request)
    {
        return response()->json(['message' => 'Content store endpoint']);
    }

    public function update(Request $request, $slug)
    {
        return response()->json(['message' => 'Content update endpoint']);
    }

    public function destroy(Request $request, $slug)
    {
        return response()->json(['message' => 'Content destroy endpoint']);
    }

    public function search(Request $request)
    {
        return response()->json(['message' => 'Content search endpoint']);
    }
}
