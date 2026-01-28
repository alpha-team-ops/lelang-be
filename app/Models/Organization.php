<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $table = 'organizations';
    protected $keyType = 'string';
    protected $primaryKey = 'code';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'country',
        'logo',
        'description',
        'timezone',
        'currency',
        'language',
        'email_notifications',
        'auction_notifications',
        'bid_notifications',
        'two_factor_auth',
        'maintenance_mode',
        'status',
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'auction_notifications' => 'boolean',
        'bid_notifications' => 'boolean',
        'two_factor_auth' => 'boolean',
        'maintenance_mode' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_code', 'code');
    }

    public function settingsHistory(): HasMany
    {
        return $this->hasMany(OrgSettingsHistory::class, 'organization_code', 'code');
    }
}
