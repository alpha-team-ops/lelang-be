<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Organization;
use App\Models\OrgSettingsHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    private const VALID_TIMEZONES = [
        'Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'Asia/Bangkok',
        'Asia/Singapore', 'Asia/Kuala_Lumpur', 'America/New_York',
        'America/Los_Angeles', 'Europe/London', 'Europe/Paris',
        'Australia/Sydney', 'UTC',
    ];

    private const VALID_CURRENCIES = ['IDR', 'USD', 'EUR', 'GBP', 'SGD', 'MYR', 'THB'];
    private const VALID_LANGUAGES = ['id', 'en', 'zh', 'ja', 'ko'];

    /**
     * Get Organization Settings
     * GET /api/v1/organization/settings
     */
    public function getSettings(Request $request)
    {
        try {
            $organizationCode = $request->input('organizationCode') ?? $request->user()->organization_code;

            $organization = Organization::where('code', $organizationCode)->first();

            if (!$organization) {
                return response()->json(
                    ApiResponse::error('Organization not found'),
                    404
                );
            }

            // Check multi-tenant access (users can only see their own org)
            if ($request->user()->organization_code !== $organizationCode) {
                return response()->json(
                    ApiResponse::error('Unauthorized access to organization'),
                    403
                );
            }

            $data = [
                'organizationCode' => $organization->code,
                'name' => $organization->name,
                'email' => $organization->email,
                'phone' => $organization->phone,
                'website' => $organization->website,
                'address' => $organization->address,
                'city' => $organization->city,
                'country' => $organization->country,
                'logo' => $organization->logo,
                'description' => $organization->description,
                'timezone' => $organization->timezone,
                'currency' => $organization->currency,
                'language' => $organization->language,
                'emailNotifications' => $organization->email_notifications,
                'auctionNotifications' => $organization->auction_notifications,
                'bidNotifications' => $organization->bid_notifications,
                'twoFactorAuth' => $organization->two_factor_auth,
                'maintenanceMode' => $organization->maintenance_mode,
                'portalInvitationCode' => $organization->portal_invitation_code,
                'portalInvitationActive' => $organization->portal_invitation_active,
            ];

            return response()->json(
                ApiResponse::success($data, 'Organization settings retrieved successfully'),
                200
            );
        } catch (\Exception $e) {
            return response()->json(
                ApiResponse::error('Failed to retrieve organization settings'),
                500
            );
        }
    }

    /**
     * Update Organization Settings
     * PUT /api/v1/organization/settings
     */
    public function updateSettings(Request $request)
    {
        try {
            // Check permission
            if (!$this->hasPermission($request->user(), 'manage_settings')) {
                return response()->json(
                    ApiResponse::error('Unauthorized: manage_settings permission required'),
                    403
                );
            }

            $organizationCode = $request->user()->organization_code;
            $organization = Organization::where('code', $organizationCode)->first();

            if (!$organization) {
                return response()->json(
                    ApiResponse::error('Organization not found'),
                    404
                );
            }

            // Validate inputs
            $validated = $request->validate([
                'name' => 'nullable|string|min:2|max:100',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:20',
                'website' => 'nullable|url',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'description' => 'nullable|string|max:1000',
                'timezone' => 'nullable|string|in:' . implode(',', self::VALID_TIMEZONES),
                'currency' => 'nullable|string|in:' . implode(',', self::VALID_CURRENCIES),
                'language' => 'nullable|string|in:' . implode(',', self::VALID_LANGUAGES),
                'emailNotifications' => 'nullable|boolean',
                'auctionNotifications' => 'nullable|boolean',
                'bidNotifications' => 'nullable|boolean',
                'twoFactorAuth' => 'nullable|boolean',
                'maintenanceMode' => 'nullable|boolean',
            ]);

            // Track changes
            $changes = [];
            foreach ($validated as $key => $value) {
                if ($value !== null) {
                    $snakeKey = Str::snake($key);
                    $oldValue = $organization->{$snakeKey};
                    
                    if ($oldValue !== $value) {
                        $changes[$snakeKey] = [
                            'old' => $oldValue,
                            'new' => $value,
                        ];
                    }
                }
            }

            // Update organization
            $updateData = [];
            foreach ($validated as $key => $value) {
                if ($value !== null) {
                    $updateData[Str::snake($key)] = $value;
                }
            }

            $organization->update($updateData);

            // Log changes to history
            foreach ($changes as $field => $change) {
                OrgSettingsHistory::create([
                    'organization_code' => $organizationCode,
                    'changed_by' => $request->user()->id,
                    'field_name' => $field,
                    'old_value' => is_bool($change['old']) ? ($change['old'] ? '1' : '0') : $change['old'],
                    'new_value' => is_bool($change['new']) ? ($change['new'] ? '1' : '0') : $change['new'],
                    'changed_at' => now(),
                ]);
            }

            // Return updated settings
            $data = [
                'organizationCode' => $organization->code,
                'name' => $organization->name,
                'email' => $organization->email,
                'phone' => $organization->phone,
                'website' => $organization->website,
                'address' => $organization->address,
                'city' => $organization->city,
                'country' => $organization->country,
                'logo' => $organization->logo,
                'description' => $organization->description,
                'timezone' => $organization->timezone,
                'currency' => $organization->currency,
                'language' => $organization->language,
                'emailNotifications' => $organization->email_notifications,
                'auctionNotifications' => $organization->auction_notifications,
                'bidNotifications' => $organization->bid_notifications,
                'twoFactorAuth' => $organization->two_factor_auth,
                'maintenanceMode' => $organization->maintenance_mode,
                'portalInvitationCode' => $organization->portal_invitation_code,
                'portalInvitationActive' => $organization->portal_invitation_active,
            ];

            return response()->json(
                ApiResponse::success($data, 'Organization settings updated successfully'),
                200
            );
        } catch (ValidationException $e) {
            return response()->json(
                ApiResponse::error('Validation failed', null),
                422
            );
        } catch (\Exception $e) {
            return response()->json(
                ApiResponse::error('Failed to update organization settings'),
                500
            );
        }
    }

    /**
     * Upload Organization Logo
     * POST /api/v1/organization/logo
     */
    public function uploadLogo(Request $request)
    {
        try {
            // Check permission
            if (!$this->hasPermission($request->user(), 'manage_settings')) {
                return response()->json(
                    ApiResponse::error('Unauthorized: manage_settings permission required'),
                    403
                );
            }

            $validated = $request->validate([
                'logo' => 'required|image|max:5120', // 5MB in KB
            ]);

            $organizationCode = $request->user()->organization_code;
            $organization = Organization::where('code', $organizationCode)->first();

            if (!$organization) {
                return response()->json(
                    ApiResponse::error('Organization not found'),
                    404
                );
            }

            // Store file
            $file = $request->file('logo');
            $fileName = 'org-' . $organizationCode . '-' . Str::random(16) . '.' . $file->getClientOriginalExtension();
            
            $path = Storage::disk('public')->putFileAs('logos', $file, $fileName);
            $logoUrl = url('storage/' . $path);

            // Delete old logo if exists
            if ($organization->logo) {
                Storage::disk('public')->delete(str_replace(url('storage/'), '', $organization->logo));
            }

            // Update organization
            $organization->update(['logo' => $logoUrl]);

            // Log to history
            OrgSettingsHistory::create([
                'organization_code' => $organizationCode,
                'changed_by' => $request->user()->id,
                'field_name' => 'logo',
                'old_value' => null,
                'new_value' => $logoUrl,
                'changed_at' => now(),
            ]);

            return response()->json(
                ApiResponse::success([
                    'logoUrl' => $logoUrl,
                    'fileName' => $fileName,
                    'uploadedAt' => now()->toIso8601String(),
                ], 'Logo uploaded successfully'),
                200
            );
        } catch (ValidationException $e) {
            return response()->json(
                ApiResponse::error('Validation failed'),
                422
            );
        } catch (\Exception $e) {
            return response()->json(
                ApiResponse::error('Failed to upload logo'),
                500
            );
        }
    }

    /**
     * Get Organization Code & Portal Invitation Code
     * GET /api/v1/organization/code
     */
    public function getOrganizationCode(Request $request)
    {
        try {
            $organization = Organization::where('code', $request->user()->organization_code)->first();

            if (!$organization) {
                return response()->json(
                    ApiResponse::error('Organization not found'),
                    404
                );
            }

            return response()->json(
                ApiResponse::success([
                    'organizationCode' => $organization->code,
                    'portalInvitationCode' => $organization->portal_invitation_code,
                    'portalInvitationActive' => $organization->portal_invitation_active,
                ], 'Organization codes retrieved successfully'),
                200
            );
        } catch (\Exception $e) {
            return response()->json(
                ApiResponse::error('Failed to retrieve organization code'),
                500
            );
        }
    }

    /**
     * Check if user has permission
     */
    private function hasPermission($user, $permission): bool
    {
        if (!isset($user->permissions)) {
            return false;
        }

        // Decode JWT permissions if needed
        $permissions = is_array($user->permissions) ? $user->permissions : json_decode($user->permissions, true);
        
        return in_array($permission, $permissions ?? []);
    }
}
