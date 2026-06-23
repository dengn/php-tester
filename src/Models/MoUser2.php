<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A feature-rich user model used by the model2/scope/casts scenarios.
 *
 * Exercises: $casts variety, accessor/mutator via Attribute, $appends,
 * $hidden / $visible, and a local query scope. Uses its own table so it does
 * not collide with the fixed-name mo_users schema rebuilt elsewhere.
 */
class MoUser2 extends Model
{
    protected $table = 'mo_users2';
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'profile' => 'object',
        'tags' => 'collection',
        'is_active' => 'boolean',
        'login_count' => 'integer',
        'rating' => 'float',
        'balance' => 'decimal:2',
        'born_on' => 'date',
        'seen_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    protected $appends = ['display_name'];

    /** Accessor + mutator pair via the modern Attribute API. */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : ucfirst($value),
            set: fn (?string $value) => $value === null ? null : strtolower($value),
        );
    }

    /** Computed attribute exposed via $appends. */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => '@' . ($this->attributes['name'] ?? 'anon'),
        );
    }

    /** Local query scope: scopeActive(). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Local query scope with an argument: scopeOfRating(). */
    public function scopeOfRating(Builder $query, float $min): Builder
    {
        return $query->where('rating', '>=', $min);
    }
}
