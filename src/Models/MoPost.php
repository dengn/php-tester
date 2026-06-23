<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MoPost extends Model
{
    protected $table = 'mo_posts';
    public $timestamps = true;
    protected $guarded = [];
    protected $casts = [
        'published' => 'boolean',
        'tags' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(MoUser::class, 'user_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(MoComment::class, 'commentable');
    }
}
