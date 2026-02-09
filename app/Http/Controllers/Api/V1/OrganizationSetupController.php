<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Organization;
use App\Models\User;
use App\Models\Role;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationSetupController
{
    /**
     * Create a new organization
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'organizationName' => 'required|string|min:3|max:100',
            'description' => 'nullable|string|max:500',
        ], [
            'organizationName.required' => 'Organization name is required',
            'organizationName.min' => 'Organization name must be at least 3 characters',
            'organizationName.max' => 'Organization name cannot exceed 100 characters',
            'description.max' => 'Description cannot exceed 500 characters',
        ]);

        try {
            return DB::transaction(function () use ($validated, $request) {
                $user = $request->user();
                $organizationName = trim($validated['organizationName']);

                // Check if user already has an organization
                if ($user->organization_code !== null) {
                    return ApiResponse::error(
                        'You already belong to an organization. Cannot create another organization.',
                        'USER_ALREADY_IN_ORG',
                        409
                    );
                }

                // Check if organization name already exists
                if (Organization::where('name', $organizationName)->exists()) {
                    return ApiResponse::error('Organization name already exists', 'ORG_NAME_EXISTS', 409);
                }

                // Generate unique organization code
                $organizationCode = $this->generateOrganizationCode($organizationName);

                // Create organization with default settings
                $organization = Organization::create([
                    'code' => $organizationCode,
                    'name' => $organizationName,
                    'description' => $validated['description'] ?? null,
                    'timezone' => 'Asia/Jakarta',
                    'currency' => 'IDR',
                    'language' => 'id',
                    'email_notifications' => true,
                    'auction_notifications' => true,
                    'bid_notifications' => true,
                    'two_factor_auth' => false,
                    'maintenance_mode' => false,
                    'status' => 'active',
                    'portal_invitation_code' => $this->generatePortalInvitationCode(),
                    'portal_invitation_active' => true,
                ]);

                // Create default roles for the organization (Admin and Member)
                $roleService = app(RoleService::class);
                $roleService->createDefaultRoles($organizationCode);

                // Update user to be ADMIN of this organization
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'organization_code' => $organizationCode,
                        'role' => 'ADMIN',
                        'updated_at' => now(),
                    ]);

                // Assign user to Admin role via staff_roles
                $adminRole = Role::where('organization_code', $organizationCode)
                    ->where('name', 'Admin')
                    ->first();
                
                if ($adminRole) {
                    DB::table('staff_roles')->insert([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'staff_id' => $user->id,
                        'role_id' => $adminRole->id,
                        'organization_code' => $organizationCode,
                        'assigned_at' => now(),
                        'assigned_by' => $user->id,
                    ]);
                }

                // Log audit action
                $this->logAuditAction($user->id, 'create_organization', 'organization', $organizationCode, null, [
                    'name' => $organizationName,
                    'code' => $organizationCode,
                    'created_by' => $user->id,
                ]);

                // Refresh user data to get updated organization_code and role
                $user->refresh();

                // Generate new tokens with updated organization info
                $authService = app(AuthService::class);
                $tokens = $authService->generateTokens($user);

                return ApiResponse::success(
                    [
                        'organizationCode' => $organization->code,
                        'name' => $organization->name,
                        'description' => $organization->description,
                        'portalInvitationCode' => $organization->portal_invitation_code,
                        'createdAt' => $organization->created_at?->toIso8601String(),
                        'createdBy' => $user->id,
                        'accessToken' => $tokens['accessToken'],
                        'refreshToken' => $tokens['refreshToken'],
                        'expiresIn' => $tokens['expiresIn'],
                        'tokenType' => $tokens['tokenType'],
                    ],
                    'Organization created successfully'
                );
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create organization: ' . $e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * Join an existing organization
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function join(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'organizationCode' => 'required|string|regex:/^ORG-[A-Z0-9-]+$/|min:3|max:50',
        ], [
            'organizationCode.required' => 'Organization code is required',
            'organizationCode.regex' => 'Organization code format is invalid (e.g., ORG-DERALY-001)',
            'organizationCode.min' => 'Organization code must be at least 3 characters',
            'organizationCode.max' => 'Organization code cannot exceed 50 characters',
        ]);

        try {
            return DB::transaction(function () use ($validated, $request) {
                $user = $request->user();
                $organizationCode = strtoupper(trim($validated['organizationCode']));

                // Check if user already has an organization
                if ($user->organization_code !== null) {
                    return ApiResponse::error(
                        'You already belong to an organization',
                        'USER_ALREADY_IN_ORG',
                        409
                    );
                }

                // Find organization
                $organization = Organization::where('code', $organizationCode)->first();
                if (!$organization) {
                    return ApiResponse::error(
                        'Organization not found',
                        'ORG_NOT_FOUND',
                        404
                    );
                }

                // Check if organization is in maintenance mode
                if ($organization->maintenance_mode) {
                    return ApiResponse::error(
                        'Cannot join this organization at the moment',
                        'ORG_MAINTENANCE',
                        403
                    );
                }

                // Update user to join organization as MEMBER
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'organization_code' => $organizationCode,
                        'role' => 'MEMBER',
                        'updated_at' => now(),
                    ]);

                // Assign Member role to the user
                $memberRole = Role::where('organization_code', $organizationCode)
                                  ->where('name', 'Member')
                                  ->first();

                if ($memberRole) {
                    DB::table('staff_roles')->insert([
                        'id' => Str::uuid()->toString(),
                        'staff_id' => $user->id,
                        'role_id' => $memberRole->id,
                        'organization_code' => $organizationCode,
                        'assigned_by' => null, // System assignment
                        'assigned_at' => now(),
                    ]);
                }

                // Log audit action
                $this->logAuditAction($user->id, 'join_organization', 'organization', $organizationCode, null, [
                    'organization_code' => $organizationCode,
                    'user_id' => $user->id,
                    'joined_at' => now()->toIso8601String(),
                ]);

                // TODO: Send notification to organization admins
                // $this->notifyAdminsOfNewMember($organization, $user);

                // Refresh user data to get updated organization_code and role
                $user->refresh();

                // Generate new tokens with updated organization info
                $authService = app(AuthService::class);
                $tokens = $authService->generateTokens($user);

                return ApiResponse::success(
                    [
                        'organizationCode' => $organization->code,
                        'name' => $organization->name,
                        'description' => $organization->description,
                        'accessToken' => $tokens['accessToken'],
                        'refreshToken' => $tokens['refreshToken'],
                        'expiresIn' => $tokens['expiresIn'],
                        'tokenType' => $tokens['tokenType'],
                    ],
                    'Successfully joined organization'
                );
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to join organization: ' . $e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * Check if user needs to complete setup
     * Used by frontend route guards to determine setup flow
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkSetup(Request $request)
    {
        try {
            $user = $request->user();

            $needsSetup = $user->organization_code === null;

            return ApiResponse::success(
                [
                    'needsSetup' => $needsSetup,
                    'organizationCode' => $user->organization_code,
                    'role' => $user->role,
                ],
                $needsSetup ? 'User needs to complete organization setup' : 'User has completed organization setup'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to check setup status: ' . $e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * Generate unique organization code
     * Format: ORG-{FIRST 8 CHARS}-{SEQUENCE}
     * Example: ORG-DERALY-001
     * 
     * @param string $organizationName
     * @return string
     */
    private function generateOrganizationCode(string $organizationName): string
    {
        // Extract first 8 characters and convert to uppercase
        $namePrefix = strtoupper(substr(str_replace(' ', '', $organizationName), 0, 8));
        
        // Remove any non-alphanumeric characters
        $namePrefix = preg_replace('/[^A-Z0-9]/', '', $namePrefix);
        
        // Pad if necessary (in case name is shorter than 8 chars)
        $namePrefix = str_pad($namePrefix, 8, 'X', STR_PAD_RIGHT);

        // Find the next sequence number
        $sequence = 1;
        while (Organization::where('code', "ORG-{$namePrefix}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT))->exists()) {
            $sequence++;
        }

        return "ORG-{$namePrefix}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate unique portal invitation code
     * Format: PORTAL-{8 RANDOM CHARS}
     * Example: PORTAL-A7K3M9L2
     * 
     * @return string
     */
    private function generatePortalInvitationCode(): string
    {
        do {
            $code = 'PORTAL-' . strtoupper(Str::random(8));
        } while (Organization::where('portal_invitation_code', $code)->exists());

        return $code;
    }

    /**
     * Log audit action
     * 
     * @param string $userId
     * @param string $action
     * @param string $resourceType
     * @param string|null $resourceId
     * @param mixed $oldValue
     * @param mixed $newValue
     * @return void
     */
    private function logAuditAction(
        string $userId,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        $oldValue = null,
        $newValue = null
    ): void {
        // Use org_settings_history table for consistency with existing audit logging
        DB::table('org_settings_history')->insert([
            'id' => Str::uuid(),
            'organization_code' => $resourceId,
            'changed_by' => $userId,
            'field_name' => $action,
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null,
            'changed_at' => now(),
        ]);
    }
}
