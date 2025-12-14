<?php

namespace App\Models\Remittance;

use App\Models\Commodity;
use App\Models\Organization;
use App\Models\Submission;
use App\Models\UsState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Soybean extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'id',
        'title',
        'num_bushels_purchased',
        'num_bushels_assessed',
        'net_market_value_assessed_bushels',
        'total',
        'us_state_id',
        'organization_id',
        'commodity_id',
        'first_purchased_submission_id',
        'base_date_created',
        'base_date_updated'
    ];

    /**
     * @var string
     */
    protected $table = 'soybean_remittances';

    /**
     * @param array $remittance
     * @return array
     */
    public static function prepareApiData(array $remittance): array
    {
        $remittance['us_state_id'] = $remittance['state']['id'];
        $remittance['commodity_id'] = $remittance['commodity']['id'];
        $remittance['organization_id'] = $remittance['organization']['id'] ?? null;
        $remittance['base_date_created'] = $remittance['dateCreated'];
        $remittance['base_date_updated'] = $remittance['dateUpdated'];
        $remittance['num_bushels_purchased'] = $remittance['numberOfBushelsPurchased'];
        $remittance['num_bushels_assessed'] = $remittance['numberOfBushelsAssessed'];
        $remittance['net_market_value_assessed_bushels'] = $remittance['netMarketValueOfAssessedBushels'];
        $remittance['first_purchased_submission_id'] = $remittance['firstPurchaserSubmissionId'];

        return $remittance;
    }

    /**
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    /**
     * @return BelongsTo
     */
    public function us_state(): BelongsTo
    {
        return $this->belongsTo(UsState::class);
    }

    public function submissions(): MorphToMany
    {
        return $this->morphToMany(Submission::class, 'submittable');
    }
}
