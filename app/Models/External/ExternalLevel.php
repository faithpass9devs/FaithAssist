<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'church_id',
])]
class ExternalLevel extends Model
{
    use SoftDeletes;

    protected $connection = 'hostinger';

    protected $table = 'levels';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;
}
