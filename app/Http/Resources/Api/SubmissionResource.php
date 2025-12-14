<?php
namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\Remittance\CornCollection;
use App\Http\Resources\Api\RemittanceCollection;

/**
 * @OA\Schema(
 *     schema="Submission",
 *     title="Submission Details",
 *     description="submission model",
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
class SubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            //'title' => $this->title,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'total_remittance' => $this->total_remittance,
            'total' => $this->total,
            'submitted' => $this->submitted,
            'paid_by_check' => $this->paid_by_check,
            'organizations' => new RelatedItems($this->whenLoaded('organization')),
            'commodity' => new RelatedItem($this->whenLoaded('commodity')),
            'states' => new RelatedItems($this->whenLoaded('us_state')),
            'remittances' => new RemittanceCollection($this->whenLoaded('remittances')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
