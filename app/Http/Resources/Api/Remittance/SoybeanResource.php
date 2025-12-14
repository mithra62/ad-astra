<?php

namespace App\Http\Resources\Api\Remittance;

use App\Http\Resources\Api\RemittanceResource;

/**
 * @OA\Schema(
 *     schema="SoybeanRemittance",
 *     title="Soybean Remittance",
 *     description="corn remittance model",
 *     @OA\Property(property="id", type="integer", format="int64", description=""),
 *     @OA\Property(property="title", type="string", description=""),
 *     @OA\Property(property="num_bushels_purchased", type="number", format="float", description=""),
 *     @OA\Property(property="num_bushels_assessed", type="number", format="float", description=""),
 *     @OA\Property(property="net_market_value_assessed_bushels", type="number", format="float", description=""),
 *     @OA\Property(property="total", type="number", format="float", description=""),
 *     @OA\Property(property="first_purchased_submission_id", type="number", format="float", description=""),
 *     @OA\Property(property="organization",type="array",description="Organization",@OA\Items(ref="#/components/schemas/RelatedItem")),
 *     @OA\Property(property="commodity",type="array",description="Commodity",@OA\Items(ref="#/components/schemas/RelatedItem")),
 *     @OA\Property(property="state",type="array",description="State",@OA\Items(ref="#/components/schemas/RelatedItem"))
 * )
 */
class SoybeanResource extends RemittanceResource
{

}
