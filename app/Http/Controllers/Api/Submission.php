<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\SubmissionCollection;
use App\Http\Resources\Api\SubmissionResource;
use App\Models\Submission as SubmissionModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class Submission extends Controller
{
    /**
     * @var array|string[]
     */
    protected array $with = [
        'organization',
        'commodity',
        'us_state',
        'remittances',
    ];

    /**
     * @OA\Get(
     *      path="/api/submissions",
     *      operationId="getAllSubmissions",
     *      tags={"Submissions"},
     *      summary="Get a paginated list of Submissions",
     *      security={{"sanctum": {}}},
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="data",type="array",description="Submissions",@OA\Items(ref="#/components/schemas/Submission")),
     *              @OA\Property(property="meta",type="array",description="",@OA\Items(ref="#/components/schemas/Meta")),
     *              @OA\Property(property="links",type="array",description="",@OA\Items(ref="#/components/schemas/Links")),
     *              @OA\Property(property="pagination_info",type="array",description="",@OA\Items(ref="#/components/schemas/PaginationInfo")),
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
        if (!$this->can('read submissions')) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        $query = SubmissionModel::with($this->with);
        if (!$this->can('read all locations')) {
            $query->whereIn('id', $this->getPermissionStatesIds());
        }

        $submissions = $query->paginate($this->limit($request));
        return new SubmissionCollection($submissions);
    }

    /**
     * @return array
     */
    protected function getPermissionStatesIds(): array
    {
        $states_ids = $this->getPermissionStates();
        $states = Db::table('submission_us_state')
            ->select('submission_id')->distinct()
            ->whereIn('us_state_id', $states_ids)
            ->get();

        $return = [];
        if($states instanceof Collection && $states->count() >= 1) {
            foreach($states->all() AS $state) {
                $return[] = $state->submission_id;
            }
        }

        return $return;
    }

    public function store(Request $request)
    {
        return response()->json(['error' => 'Not Implemented'], 501);
    }

    /**
     * @OA\Get(
     *     path="/api/submissions/{id}",
     *      operationId="getSubmission",
     *      tags={"Submissions"},
     *      summary="Get details on a specific Submission",
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
     *         @OA\JsonContent(ref="#/components/schemas/Submission")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Submission not found"
     *     )
     * )
     */
    public function show($id)
    {
        if (!$this->can('read submissions')) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        $query = SubmissionModel::with($this->with);
        if (!$this->can('read all locations')) {
            $query->whereIn('id', $this->getPermissionStatesIds());
        }

        $where = [
            'id' => $id,
        ];
        $submission = $query->find($where)->first();
        if (!$submission) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        return new SubmissionResource($submission);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SubmissionModel $submission)
    {
        return response()->json(['error' => 'Not Implemented'], 501);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SubmissionModel $submission)
    {
        return response()->json(['error' => 'Not Implemented'], 501);
    }
}
