<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Remittance extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'id',
        'title',
        'type',
        'total',
        'us_state_id',
        'organization_id',
        'commodity_id',
        'first_purchased_submission_id',
        'num_bushels_purchased',
        'synced_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @var string
     */
    protected $table = 'remittances';

    /**
     * @param array $remittance
     * @return array
     */
    public static function prepareApiData(array $remittance): array
    {
        $remittance['synced_at'] = now();
        $remittance['us_state_id'] = $remittance['state']['id'];
        $remittance['organization_id'] = $remittance['organization']['id'] ?? null;
        $remittance['created_at'] = $remittance['dateCreated'];
        $remittance['updated_at'] = $remittance['dateUpdated'];
        $remittance['num_bushels_purchased'] = $remittance['numberOfBushelsPurchased'];
        $remittance['first_purchased_submission_id'] = $remittance['firstPurchaserSubmissionId'];
        $remittance['commodity_id'] = $remittance['commodity']['id'];

        return $remittance;
    }

    /**
     * @param array $remittance
     * @return void
     */
    public function saveMeta(array $remittance): void
    {
        Remittance\Meta::where('remittance_id', $this->id)->delete();
        foreach($remittance As $key => $value) {
            $data = [
                'remittance_id' => $this->id,
                'key' => $key,
                'value' => $value,
            ];

            Remittance\Meta::create($data);
        }
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

    /**
     * @return BelongsToMany
     */
    public function submissions(): BelongsToMany
    {
        return $this->belongsToMany(Submission::class)->withPivot('remittance_id', 'submission_id');
    }

    /**
     * @return HasMany
     */
    public function meta(): HasMany
    {
        return $this->hasMany(Remittance\Meta::class);
    }
}
