<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
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
        ];

        // Get or create permissions
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

        $this->command->info('Permissions created successfully.');

        // Create built-in roles for each organization
        // For now, we'll create them for ORG-ALPHACORP-001
        $orgCode = 'ORG-ALPHACORP-001';

        // Admin Role
        $adminRole = Role::where('organization_code', $orgCode)
                         ->where('name', 'Admin')
                         ->first();

        if (!$adminRole) {
            $adminRole = Role::create([
                'id' => Str::uuid()->toString(),
                'organization_code' => $orgCode,
                'name' => 'Admin',
                'description' => 'Full system access',
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
            $this->command->info('Admin role created for ' . $orgCode);
        }

        // Moderator Role
        $moderatorRole = Role::where('organization_code', $orgCode)
                             ->where('name', 'Moderator')
                             ->first();

        if (!$moderatorRole) {
            $moderatorRole = Role::create([
                'id' => Str::uuid()->toString(),
                'organization_code' => $orgCode,
                'name' => 'Moderator',
                'description' => 'Auction management and moderation',
                'is_active' => true,
            ]);

            // Attach specific permissions to Moderator role
            $moderatorPerms = [
                'manage_auctions',
                'view_bids',
                'view_analytics',
                'view_staff',
            ];
            $moderatorPermIds = array_intersect_key($permissionMap, array_flip($moderatorPerms));
            foreach (array_values($moderatorPermIds) as $permissionId) {
                DB::table('role_permissions')->insert([
                    'id' => Str::uuid()->toString(),
                    'role_id' => $moderatorRole->id,
                    'permission_id' => $permissionId,
                ]);
            }
            $this->command->info('Moderator role created for ' . $orgCode);
        }

        // Analyst Role
        $analystRole = Role::where('organization_code', $orgCode)
                           ->where('name', 'Analyst')
                           ->first();

        if (!$analystRole) {
            $analystRole = Role::create([
                'id' => Str::uuid()->toString(),
                'organization_code' => $orgCode,
                'name' => 'Analyst',
                'description' => 'Data analysis and reporting',
                'is_active' => true,
            ]);

            // Attach specific permissions to Analyst role
            $analystPerms = [
                'view_analytics',
                'view_auctions',
                'view_bids',
            ];
            $analystPermIds = array_intersect_key($permissionMap, array_flip($analystPerms));
            foreach (array_values($analystPermIds) as $permissionId) {
                DB::table('role_permissions')->insert([
                    'id' => Str::uuid()->toString(),
                    'role_id' => $analystRole->id,
                    'permission_id' => $permissionId,
                ]);
            }
            $this->command->info('Analyst role created for ' . $orgCode);
        }

        // Assign Admin role to alpha.dev user
        $alphaUser = \App\Models\User::where('email', 'alpha.dev@localhost')->first();
        if ($alphaUser) {
            $adminAssignment = $alphaUser->roles()
                                        ->where('role_id', $adminRole->id)
                                        ->first();
            if (!$adminAssignment) {
                DB::table('staff_roles')->insert([
                    'id' => Str::uuid()->toString(),
                    'staff_id' => $alphaUser->id,
                    'role_id' => $adminRole->id,
                    'organization_code' => $orgCode,
                    'assigned_by' => $alphaUser->id,
                    'assigned_at' => now(),
                ]);
                $this->command->info('Admin role assigned to alpha.dev user.');
            }
        }
    }
}
