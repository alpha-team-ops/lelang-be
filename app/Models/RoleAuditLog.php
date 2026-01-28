<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleAuditLog extends Model
{
    protected $table = 'role_audit_logs';

    protected $fillable = [
        'id',
        'role_id',
        'staff_id',
        'action',
        'changes',
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'changes' => 'json',
        'performed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Get the role that this audit log is for
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the staff that this audit log is for
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the user that performed the action
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'performed_by');
    }
}
