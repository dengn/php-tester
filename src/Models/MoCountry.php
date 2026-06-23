<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Root of a through-relationship chain: Country -> Citizen -> Article.
 * Backs hasManyThrough / hasOneThrough scenarios.
 */
class MoCountry extends Model
{
    protected $table = 'mo_countries';
    public $timestamps = false;
    protected $guarded = [];

    public function citizens(): HasMany
    {
        return $this->hasMany(MoCitizen::class, 'country_id');
    }

    public function articles(): HasManyThrough
    {
        return $this->hasManyThrough(
            MoArticle::class,
            MoCitizen::class,
            'country_id', // FK on mo_citizens
            'citizen_id', // FK on mo_articles
            'id',
            'id'
        );
    }

    public function firstArticle(): HasOneThrough
    {
        return $this->hasOneThrough(
            MoArticle::class,
            MoCitizen::class,
            'country_id',
            'citizen_id',
            'id',
            'id'
        );
    }
}
