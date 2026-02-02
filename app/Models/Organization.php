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
        'portal_invitation_code',
        'portal_invitation_active',
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'auction_notifications' => 'boolean',
        'bid_notifications' => 'boolean',
        'two_factor_auth' => 'boolean',
        'maintenance_mode' => 'boolean',
        'portal_invitation_active' => 'boolean',
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

    /**
     * Generate a new portal invitation code for this organization
     */
    public function generatePortalInvitationCode(): string
    {
        $code = 'PORT-' . strtoupper(bin2hex(random_bytes(6)));
        
        $this->update([
            'portal_invitation_code' => $code,
            'portal_invitation_active' => true,
        ]);
        
        return $code;
    }

    /**
     * Regenerate the portal invitation code
     */
    public function regeneratePortalInvitationCode(): string
    {
        return $this->generatePortalInvitationCode();
    }

    /**
     * Deactivate the portal invitation code
     */
    public function deactivatePortalInvitation(): void
    {
        $this->update(['portal_invitation_active' => false]);
    }

    /**
     * Check if portal invitation code is valid
     */
    public function isPortalInvitationValid(): bool
    {
        return $this->portal_invitation_active && !empty($this->portal_invitation_code);
    }
}
