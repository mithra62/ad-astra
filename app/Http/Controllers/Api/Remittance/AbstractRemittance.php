<?php
namespace App\Http\Controllers\Api\Remittance;

use App\Http\Controllers\Api\Controller;
use App\Models\Remittance;
use Illuminate\Database\Eloquent\Builder;

abstract class AbstractRemittance extends Controller
{
    /**
     * @var array|string[]
     */
    protected array $with = [
        'organization',
        'commodity',
        'us_state',
        'submissions',
        'meta'
    ];

    /**
     * @return Builder
     */
    protected function buildQuery(): Builder
    {
        $query = Remittance::with($this->with);
        if (!$this->can('read all locations')) {
            $query->whereIn('us_state_id', $this->getPermissionStates());
        }

        return $query;
    }
}
