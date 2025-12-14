<?php
namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RemittanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $return = [
            'id' => $this->id,
            'type' => $this->type,
            //'title' => $this->title,
            'num_bushels_purchased' => $this->num_bushels_purchased,
            'total' => $this->total,
            'first_purchased_submission_id' => $this->first_purchased_submission_id,
            'organization' => new RelatedItem($this->whenLoaded('organization')),
            'commodity' => new RelatedItem($this->whenLoaded('commodity')),
            'state' => new RelatedItem($this->whenLoaded('us_state'))
        ];

        foreach($this->meta->all() AS $meta) {
            $return['meta'][$meta['key']] = $meta['value'];
        }

        $return['submissions'] = new SubmissionCollection($this->whenLoaded('submissions'));
        $return['created_at'] = $this->created_at;
        $return['updated_at'] = $this->updated_at;

        return $return;
    }
}
