<?php

declare(strict_types=1);

namespace MoTest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Soft-deletable model used to exercise Eloquent's SoftDeletes trait against
 * MatrixOne (delete()/trashed()/withTrashed()/onlyTrashed()/restore()/forceDelete()).
 */
class MoSoftItem extends Model
{
    use SoftDeletes;

    protected $table = 'mo_soft_items';
    public $timestamps = true;
    protected $guarded = [];
    protected $casts = [
        'qty' => 'integer',
    ];
}
