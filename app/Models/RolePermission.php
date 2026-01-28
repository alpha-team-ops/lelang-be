<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RolePermission extends Pivot
{
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
}
