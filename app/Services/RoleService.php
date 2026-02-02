<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RoleService
{
    /**
     * Create default roles for a new organization
     * Creates Admin and Member roles with appropriate permissions
     *
     * @param string $organizationCode
     * @return array Array of created roles
     */
    public function createDefaultRoles(string $organizationCode): array
    {
        $createdRoles = [];

        // First ensure permissions exist (they should be global and not org-specific)
        $permissionMap = $this->ensurePermissionsExist();

        // Check if roles already exist for this organization
        $adminExists = Role::where('organization_code', $organizationCode)
                          ->where('name', 'Admin')
                          ->exists();

        // Admin Role
        if (!$adminExists) {
            $adminRole = Role::create([
                'id' => Str::uuid()->toString(),
                'organization_code' => $organizationCode,
                'name' => 'Admin',
                'description' => 'Full system access with all permissions',
                'is_active' => true,
            ]);

            // Attach all permissions to Admin role
            foreach (array_values($permissionMap) as $permissionId) {
                DB::table('role_permissions')->insert([
                    'id' => Str::uuid()->toString(),
                    'role_id' => $adminRole->id,
                    'permission_id' => $permissionId,
                ]);
            }
            $createdRoles['admin'] = $adminRole;
        }

        // Member Role
        $memberExists = Role::where('organization_code', $organizationCode)
                           ->where('name', 'Member')
                           ->exists();

        if (!$memberExists) {
            $memberRole = Role::create([
                'id' => Str::uuid()->toString(),
                'organization_code' => $organizationCode,
                'name' => 'Member',
                'description' => 'Basic member access with limited permissions',
                'is_active' => true,
            ]);

            // Attach basic read permissions to Member role
            $memberPerms = [
                'view_overview',
                'view_analytics',
            ];
            $memberPermIds = array_intersect_key($permissionMap, array_flip($memberPerms));
            foreach (array_values($memberPermIds) as $permissionId) {
                DB::table('role_permissions')->insert([
                    'id' => Str::uuid()->toString(),
                    'role_id' => $memberRole->id,
                    'permission_id' => $permissionId,
                ]);
            }
            $createdRoles['member'] = $memberRole;
        }

        return $createdRoles;
    }

    /**
     * Ensure all required permissions exist in the system
     *
     * @return array Map of permission names to IDs
     */
    private function ensurePermissionsExist(): array
    {
        $permissions = [
            // Auction Permissions
            ['name' => 'manage_auctions', 'description' => 'Create, edit, delete, publish auctions', 'resource' => 'auctions', 'action' => 'write'],
            ['name' => 'view_auctions', 'description' => 'View all auction details', 'resource' => 'auctions', 'action' => 'read'],

            // Bid Permissions
            ['name' => 'view_bids', 'description' => 'View bid history and activity', 'resource' => 'bids', 'action' => 'read'],
            ['name' => 'manage_bids', 'description' => 'Accept/reject bids', 'resource' => 'bids', 'action' => 'write'],

            // Staff Permissions
            ['name' => 'manage_staff', 'description' => 'Create, edit, delete staff members', 'resource' => 'staff', 'action' => 'write'],
            ['name' => 'view_staff', 'description' => 'View staff details', 'resource' => 'staff', 'action' => 'read'],

            // Role Permissions
            ['name' => 'manage_roles', 'description' => 'Create, edit, delete roles', 'resource' => 'roles', 'action' => 'write'],
            ['name' => 'view_roles', 'description' => 'View role assignments', 'resource' => 'roles', 'action' => 'read'],

            // Organization Permissions
            ['name' => 'manage_organization', 'description' => 'Edit organization settings', 'resource' => 'organization', 'action' => 'write'],
            ['name' => 'view_settings', 'description' => 'View organization settings', 'resource' => 'organization', 'action' => 'read'],

            // Analytics Permissions
            ['name' => 'view_analytics', 'description' => 'View statistics and analytics', 'resource' => 'analytics', 'action' => 'read'],

            // Overview Permissions
            ['name' => 'view_overview', 'description' => 'View dashboard overview', 'resource' => 'overview', 'action' => 'read'],
        ];

        $permissionMap = [];
        foreach ($permissions as $perm) {
            $permission = Permission::where('name', $perm['name'])->first();
            if (!$permission) {
                $permission = Permission::create([
                    'id' => Str::uuid()->toString(),
                    'name' => $perm['name'],
                    'description' => $perm['description'],
                    'resource' => $perm['resource'],
                    'action' => $perm['action'],
                ]);
            }
            $permissionMap[$perm['name']] = $permission->id;
        }

        return $permissionMap;
    }
}
