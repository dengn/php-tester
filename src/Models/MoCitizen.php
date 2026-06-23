<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Intermediate model in the Country -> Citizen -> Article through chain.
 */
class MoCitizen extends Model
{
    protected $table = 'mo_citizens';
    public $timestamps = false;
    protected $guarded = [];

    public function country(): BelongsTo
    {
        return $this->belongsTo(MoCountry::class, 'country_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(MoArticle::class, 'citizen_id');
    }
}
