<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MoUser extends Model
{
    protected $table = 'mo_users';
    public $timestamps = true;
    protected $guarded = [];
    protected $casts = [
        'meta' => 'array',
        'active' => 'boolean',
        'score' => 'decimal:2',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(MoPost::class, 'user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(MoProfile::class, 'user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(MoRole::class, 'mo_role_user', 'user_id', 'role_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(MoComment::class, 'commentable');
    }
}
