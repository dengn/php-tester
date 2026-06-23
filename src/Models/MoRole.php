<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MoRole extends Model
{
    protected $table = 'mo_roles';
    public $timestamps = false;
    protected $guarded = [];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(MoUser::class, 'mo_role_user', 'role_id', 'user_id');
    }
}
