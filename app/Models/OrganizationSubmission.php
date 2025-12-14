<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizationSubmission extends Pivot
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'organization_id',
        'submission_id',
    ];

    /**
     * @var string
     */
    protected $table = 'organization_submission';
}
