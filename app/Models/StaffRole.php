<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class StaffRole extends Pivot
{
    protected $table = 'staff_roles';
    protected $fillable = [
        'id',
        'staff_id',
        'role_id',
        'organization_code',
        'assigned_at',
        'assigned_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
}
