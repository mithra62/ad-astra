<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SubmissionUsState extends Pivot
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'us_state_id',
        'submission_id',
    ];

    /**
     * @var string
     */
    protected $table = 'submission_us_state';
}
