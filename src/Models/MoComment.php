<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MoComment extends Model
{
    protected $table = 'mo_comments';
    public $timestamps = false;
    protected $guarded = [];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
