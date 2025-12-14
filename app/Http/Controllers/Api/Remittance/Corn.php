<?php

namespace App\Http\Controllers\Api\Remittance;

use App\Http\Resources\Api\Remittance\CornCollection;
use App\Http\Resources\Api\Remittance\CornResource;
use App\Models\Remittance;
use Illuminate\Http\Request;

class Corn extends AbstractRemittance
{
    /**
     * @OA\Get(
     *      path="/api/remittances/corn",
     *      operationId="getAllCornRemittances",
     *      tags={"Remittances"},
     *      summary="Get details on Corn Remittances",
     *      security={{"sanctum": {}}},
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="data",type="array",description="Remittances",@OA\Items(ref="#/components/schemas/CornRemittance")),
     *              @OA\Property(property="meta",type="array",description="Remittances",@OA\Items(ref="#/components/schemas/Meta")),
     *              @OA\Property(property="links",type="array",description="Remittances",@OA\Items(ref="#/components/schemas/Links")),
     *              @OA\Property(property="pagination_info",type="array",description="Remittances",@OA\Items(ref="#/components/schemas/PaginationInfo")),
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="limit",
     *          in="query",
     *          description="Number of items per page",
     *          required=false,
     *          @OA\Schema(type="integer", default=10)
     *      ),
     *     @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Starting page number",
     *          required=false,
     *          @OA\Schema(type="integer", default=1)
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *      )
     * )
     */
    public function index(Request $request)
    {
        if (!$this->can('read corn')) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        $query = $this->buildQuery();
        $where = [
            'type' => 'corn'
        ];
        $corn = $query->where($where)->paginate($this->limit($request));
        return new CornCollection($corn);
    }

    public function store(Request $request)
    {
        return response()->json(['error' => 'Not Implemented'], 501);
    }

    /**
     * @OA\Get(
     *     path="/api/remittances/corn/{id}",
     *      operationId="getCorn",
     *      tags={"Remittances"},
     *      summary="Get details on a Corn Remittance",
     *      security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the product to retrieve",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CornRemittance")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function show($id)
    {
        if (!$this->can('read corn')) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        $query = $this->buildQuery();
        $where = [
            'id' => $id,
            'type' => 'corn',
        ];
        $corn = $query->where($where)->first();
        if(!$corn) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        return new CornResource($corn);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Remittance $cornRemittance)
    {
        return response()->json(['error' => 'Not Implemented'], 501);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Remittance $cornRemittance)
    {
        return response()->json(['error' => 'Not Implemented'], 501);
    }
}
