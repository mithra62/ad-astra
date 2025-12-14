<?php

namespace App\Http\Controllers\Api\Remittance;

use App\Http\Resources\Api\Remittance\SoybeanCollection;
use App\Http\Resources\Api\Remittance\SoybeanResource;
use App\Models\Remittance;
use Illuminate\Http\Request;

class Soybean extends AbstractRemittance
{
    /**
     * @OA\Get(
     *      path="/api/remittances/soybean",
     *      operationId="getAllSoybeanRemittances",
     *      tags={"Remittances"},
     *      summary="Get details on Soybean Remittances",
     *      security={{"sanctum": {}}},
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="data",type="array",description="Remittances",@OA\Items(ref="#/components/schemas/SoybeanRemittance")),
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
        if (!$this->can('read soybean')) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        $query = $this->buildQuery();
        $where = [
            'type' => 'soybean',
        ];
        $soybeans = $query->where($where)->paginate($this->limit($request));
        return new SoybeanCollection($soybeans);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return response()->json(['error' => 'Not Implemented'], 501);
    }

    /**
     * @OA\Get(
     *     path="/api/remittances/soybean/{id}",
     *      operationId="getSoybeanRemittance",
     *      tags={"Remittances"},
     *      summary="Get details on a Soybean Remittance",
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
        if (!$this->can('read soybean')) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        $query = $this->buildQuery();
        $where = [
            'id' => $id,
            'type' => 'soybean',
        ];

        $soybeans = $query->where($where)->first();
        if (!$soybeans) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        return new SoybeanResource($soybeans);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Remittance $remittance)
    {
        return response()->json(['error' => 'Not Implemented'], 501);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Remittance $remittance)
    {
        return response()->json(['error' => 'Not Implemented'], 501);
    }
}
