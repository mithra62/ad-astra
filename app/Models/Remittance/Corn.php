<?php

namespace App\Models\Remittance;

use App\Models\Commodity;
use App\Models\Organization;
use App\Models\UsState;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Corn extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'id',
        'title',
        'num_bushels_purchased',
        'bushels_purchased_as_second_purchaser',
        'bushels_sweet_corn_popcorn_seed_corn',
        'total_bushels_subject_to_checkoff',
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
    protected $table = 'corn_remittances';

    /**
     * @param array $remittance
     * @return array
     */
    public static function prepareApiData(array $remittance): array
    {
        $remittance['us_state_id'] = $remittance['state']['id'];
        $remittance['organization_id'] = $remittance['organization']['id'] ?? null;
        $remittance['base_date_created'] = $remittance['dateCreated'];
        $remittance['base_date_updated'] = $remittance['dateUpdated'];
        $remittance['num_bushels_purchased'] = $remittance['numberOfBushelsPurchased'];
        $remittance['bushels_purchased_as_second_purchaser'] = $remittance['bushelsPurchasedAsASecondPurchaser'];
        $remittance['total_bushels_subject_to_checkoff'] = $remittance['totalBushelsSubjectToCheckoff'];
        $remittance['bushels_sweet_corn_popcorn_seed_corn'] = $remittance['bushelsOfSweetCornPopcornAndSeedCorn'];
        $remittance['first_purchased_submission_id'] = $remittance['firstPurchaserSubmissionId'];
        $remittance['commodity_id'] = $remittance['commodity']['id'];

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
