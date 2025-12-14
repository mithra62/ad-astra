<?php

namespace App\Http\Resources\Api\Remittance;

use App\Http\Resources\Api\RemittanceResource;

/**
 * @OA\Schema(
 *     schema="CornRemittance",
 *     title="Corn Remittance",
 *     description="corn remittance model",
 *     @OA\Property(property="id", type="integer", format="int64", description=""),
 *     @OA\Property(property="title", type="string", description=""),
 *     @OA\Property(property="num_bushels_purchased", type="number", format="float", description=""),
 *     @OA\Property(property="bushels_purchased_as_second_purchaser", type="number", format="float", description=""),
 *     @OA\Property(property="bushels_sweet_corn_popcorn_seed_corn", type="number", format="float", description=""),
 *     @OA\Property(property="total_bushels_subject_to_checkoff", type="number", format="float", description=""),
 *     @OA\Property(property="total", type="number", format="float", description=""),
 *     @OA\Property(property="first_purchased_submission_id", type="number", format="float", description=""),
 *     @OA\Property(property="organization",type="array",description="Organization",@OA\Items(ref="#/components/schemas/RelatedItem")),
 *     @OA\Property(property="commodity",type="array",description="Commodity",@OA\Items(ref="#/components/schemas/RelatedItem")),
 *     @OA\Property(property="state",type="array",description="State",@OA\Items(ref="#/components/schemas/RelatedItem"))
 * )
 */
class CornResource extends RemittanceResource
{

}
