<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Model with an anonymous global scope (only "published" rows are visible by
 * default). Drives the global-scope / withoutGlobalScope scenarios.
 */
class MoScoped extends Model
{
    protected $table = 'mo_scoped';
    public $timestamps = false;
    protected $guarded = [];

    public const PUBLISHED_SCOPE = 'published_only';

    protected static function booted(): void
    {
        static::addGlobalScope(self::PUBLISHED_SCOPE, function (Builder $builder) {
            $builder->where('published', 1);
        });
    }
}
