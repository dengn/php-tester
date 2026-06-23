<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoProfile extends Model
{
    protected $table = 'mo_profiles';
    public $timestamps = false;
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(MoUser::class, 'user_id');
    }
}
