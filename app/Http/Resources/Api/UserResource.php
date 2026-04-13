<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema(
 *     schema="User",
 *     title="User Details",
 *     description="user model",
 *     @OA\Property(property="id", type="integer", format="int64", description=""),
 *     @OA\Property(property="title", type="string", description=""),
 *     @OA\Property(property="start_date", type="string", format="date-time", description=""),
 *     @OA\Property(property="end_date", type="string", format="date-time", description=""),
 *     @OA\Property(property="total_remittance", type="number", format="float", description=""),
 *     @OA\Property(property="submitted", type="boolean", description=""),
 *     @OA\Property(property="paid_by_check", type="boolean", description=""),
 *     @OA\Property(property="payment_date", type="date", description=""),
 *     @OA\Property(property="total", type="number", format="float", description=""),
 *     @OA\Property(property="first_purchased_submission_id", type="number", format="float", description=""),
 *     @OA\Property(property="organization",type="array",description="Organization",@OA\Items(ref="#/components/schemas/RelatedItem")),
 *     @OA\Property(property="commodity",type="array",description="Commodity",@OA\Items(ref="#/components/schemas/RelatedItem")),
 *     @OA\Property(property="state",type="array",description="State",@OA\Items(ref="#/components/schemas/RelatedItem"))
 * )
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
