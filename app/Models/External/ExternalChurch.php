<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
])]
class ExternalChurch extends Model
{
    protected $connection = 'hostinger';

    protected $table = 'churches';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;
}
