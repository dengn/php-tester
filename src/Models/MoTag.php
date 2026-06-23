<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Tag model exercising polymorphic many-to-many (morphToMany / morphedByMany)
 * via the mo_taggables pivot table.
 */
class MoTag extends Model
{
    protected $table = 'mo_tags';
    public $timestamps = false;
    protected $guarded = [];

    public function articles(): MorphToMany
    {
        return $this->morphedByMany(MoArticle::class, 'taggable', 'mo_taggables', 'tag_id', 'taggable_id');
    }
}
