<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Leaf model of the through chain; also the morphable side of the
 * morphToMany tag relationship.
 */
class MoArticle extends Model
{
    protected $table = 'mo_articles';
    public $timestamps = false;
    protected $guarded = [];

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(MoCitizen::class, 'citizen_id');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(MoTag::class, 'taggable', 'mo_taggables', 'taggable_id', 'tag_id');
    }
}
