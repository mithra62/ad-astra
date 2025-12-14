<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UsState extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = ['id', 'title'];

    /**
     * @var string
     */
    protected $table = 'us_states';

    /**
     * @return BelongsToMany
     */
    public function submissions(): BelongsToMany
    {
        return $this->belongsToMany(Submission::class)->withPivot('submission_id', 'us_state_id');
    }
}
