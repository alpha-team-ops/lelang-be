<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrgSettingsHistory extends Model
{
    use HasUuids;

    protected $table = 'org_settings_history';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'organization_code',
        'changed_by',
        'field_name',
        'old_value',
        'new_value',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_code', 'code');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by', 'id');
    }
}
