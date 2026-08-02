<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'municipality_id',
    'church_id',
    'name',
])]
class ExternalCommunity extends Model
{
    use SoftDeletes;

    protected $connection = 'hostinger';

    protected $table = 'communities';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;
}
