<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Staff Model - Represents staff/users within an organization
 * Uses the users table with organization_code filter
 */
class Staff extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids;

    protected $table = 'users';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'email',
        'password_hash',
        'role',
        'status',
        'organization_code',
        'email_verified',
        'last_login',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified' => 'boolean',
            'last_login' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get formatted response data for staff
     */
    public function toStaffArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'joinDate' => $this->created_at?->format('Y-m-d'),
            'lastActivity' => $this->last_login?->toIso8601String() ?? $this->updated_at?->toIso8601String(),
            'organizationCode' => $this->organization_code,
        ];
    }

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_code', 'code');
    }
}
