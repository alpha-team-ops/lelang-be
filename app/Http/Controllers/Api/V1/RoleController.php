<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Staff;
use App\Models\RoleAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * Get all roles for the organization
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->organization_code) {
            return response()->json([
                'success' => false,
                'error' => 'User not associated with any organization',
                'code' => 'NO_ORGANIZATION',
            ], 403);
        }

        // Check permission
        if (!$this->hasPermission($user, 'manage_roles')) {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to manage roles',
                'code' => 'PERMISSION_DENIED',
            ], 403);
        }

        $query = Role::where('organization_code', $user->organization_code);

        // Filter by isActive
        if ($request->has('isActive')) {
            $query->where('is_active', $request->boolean('isActive'));
        }

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        $total = $query->count();
        $roles = $query->skip(($page - 1) * $limit)
                       ->take($limit)
                       ->with('permissions')
                       ->get()
                       ->map(function ($role) {
                           return $this->formatRole($role);
                       });

        return response()->json([
            'success' => true,
            'data' => $roles,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
            ],
        ], 200);
    }

    /**
     * Get role by ID
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || !$user->organization_code) {
            return response()->json([
                'success' => false,
                'error' => 'User not associated with any organization',
                'code' => 'NO_ORGANIZATION',
            ], 403);
        }

        // Check permission
        if (!$this->hasPermission($user, 'manage_roles')) {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to manage roles',
                'code' => 'PERMISSION_DENIED',
            ], 403);
        }

        $role = Role::where('organization_code', $user->organization_code)
                    ->where('id', $id)
                    ->with('permissions')
                    ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'error' => 'Role not found',
                'code' => 'ROLE_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatRole($role),
        ], 200);
    }

    /**
     * Create new role
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->organization_code) {
            return response()->json([
                'success' => false,
                'error' => 'User not associated with any organization',
                'code' => 'NO_ORGANIZATION',
            ], 403);
        }

        // Check permission
        if (!$this->hasPermission($user, 'manage_roles')) {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to manage roles',
                'code' => 'PERMISSION_DENIED',
            ], 403);
        }

        // Validate input
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'description' => 'nullable|string',
            'permissions' => 'array|nullable',
            'permissions.*' => 'string|exists:permissions,id',
        ], [
            'name.required' => 'Role name is required',
            'name.max' => 'Role name cannot exceed 50 characters',
            'permissions.*.exists' => 'One or more permissions do not exist',
        ]);

        // Check if role name already exists in organization
        $existingRole = Role::where('organization_code', $user->organization_code)
                            ->where('name', $validated['name'])
                            ->first();

        if ($existingRole) {
            return response()->json([
                'success' => false,
                'error' => 'A role with this name already exists in your organization',
                'code' => 'DUPLICATE_ROLE',
                'message' => 'Failed to create role',
            ], 409);
        }

        // Create role
        $roleId = Str::uuid()->toString();
        $role = Role::create([
            'id' => $roleId,
            'organization_code' => $user->organization_code,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        // Attach permissions if provided
        if (!empty($validated['permissions'])) {
            $permissions = [];
            foreach ($validated['permissions'] as $permissionId) {
                $permissions[] = [
                    'id' => Str::uuid()->toString(),
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ];
            }
            // Insert with IDs directly to database
            DB::table('role_permissions')->insert($permissions);
        }

        // Log audit
        $this->logAuditAction($user->id, $roleId, null, 'create_role', [
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $validated['permissions'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatRole($role->refresh()),
            'message' => 'Role created successfully',
        ], 201);
    }

    /**
     * Update role
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || !$user->organization_code) {
            return response()->json([
                'success' => false,
                'error' => 'User not associated with any organization',
                'code' => 'NO_ORGANIZATION',
            ], 403);
        }

        // Check permission
        if (!$this->hasPermission($user, 'manage_roles')) {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to manage roles',
                'code' => 'PERMISSION_DENIED',
            ], 403);
        }

        $role = Role::where('organization_code', $user->organization_code)
                    ->where('id', $id)
                    ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'error' => 'Role not found',
                'code' => 'ROLE_NOT_FOUND',
            ], 404);
        }

        // Validate input
        $validated = $request->validate([
            'description' => 'nullable|string',
            'permissions' => 'array|nullable',
            'permissions.*' => 'string|exists:permissions,id',
            'isActive' => 'boolean|nullable',
        ], [
            'permissions.*.exists' => 'One or more permissions do not exist',
        ]);

        // Built-in roles cannot be modified
        if (in_array($role->name, ['Admin', 'Moderator'])) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot modify built-in roles',
                'code' => 'BUILTIN_ROLE',
            ], 409);
        }

        // Track changes
        $changes = [];

        if (isset($validated['description']) && $validated['description'] !== $role->description) {
            $changes['description'] = [
                'from' => $role->description,
                'to' => $validated['description'],
            ];
            $role->description = $validated['description'];
        }

        if (isset($validated['isActive']) && $validated['isActive'] !== $role->is_active) {
            $changes['is_active'] = [
                'from' => $role->is_active,
                'to' => $validated['isActive'],
            ];
            $role->is_active = $validated['isActive'];
        }

        // Update permissions
        if (isset($validated['permissions'])) {
            $oldPermissions = $role->permissions()->pluck('id')->toArray();
            $newPermissions = $validated['permissions'];

            if ($oldPermissions !== $newPermissions) {
                $changes['permissions'] = [
                    'from' => $oldPermissions,
                    'to' => $newPermissions,
                ];
                $role->permissions()->sync($newPermissions);
            }
        }

        $role->save();

        // Log audit
        if (!empty($changes)) {
            $this->logAuditAction($user->id, $roleId, null, 'update_role', $changes);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatRole($role->refresh()),
        ], 200);
    }

    /**
     * Delete role
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || !$user->organization_code) {
            return response()->json([
                'success' => false,
                'error' => 'User not associated with any organization',
                'code' => 'NO_ORGANIZATION',
            ], 403);
        }

        // Check permission
        if (!$this->hasPermission($user, 'manage_roles')) {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to manage roles',
                'code' => 'PERMISSION_DENIED',
            ], 403);
        }

        $role = Role::where('organization_code', $user->organization_code)
                    ->where('id', $id)
                    ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'error' => 'Role not found',
                'code' => 'ROLE_NOT_FOUND',
            ], 404);
        }

        // Cannot delete built-in roles
        if (in_array($role->name, ['Admin', 'Moderator'])) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete built-in roles',
                'code' => 'BUILTIN_ROLE',
            ], 409);
        }

        // Check if role has active staff assignments
        $staffCount = $role->staffMembers()->count();
        if ($staffCount > 0) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete role with active staff assignments. Please reassign staff first.',
                'code' => 'ROLE_IN_USE',
            ], 409);
        }

        // Log audit
        $this->logAuditAction($user->id, null, null, 'delete_role', [
            'role_id' => $role->id,
            'role_name' => $role->name,
        ]);

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ], 204);
    }

    /**
     * Get all permissions
     */
    public function getPermissions(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->organization_code) {
            return response()->json([
                'success' => false,
                'error' => 'User not associated with any organization',
                'code' => 'NO_ORGANIZATION',
            ], 403);
        }

        $permissions = Permission::all()
                                ->map(function ($permission) {
                                    return [
                                        'id' => $permission->id,
                                        'name' => $permission->name,
                                        'description' => $permission->description,
                                        'resource' => $permission->resource,
                                        'action' => $permission->action,
                                    ];
                                });

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ], 200);
    }

    /**
     * Assign role to staff
     */
    public function assignRole(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || !$user->organization_code) {
            return response()->json([
                'success' => false,
                'error' => 'User not associated with any organization',
                'code' => 'NO_ORGANIZATION',
            ], 403);
        }

        // Check permission
        if (!($this->hasPermission($user, 'manage_staff') || $this->hasPermission($user, 'manage_roles'))) {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to assign roles',
                'code' => 'PERMISSION_DENIED',
            ], 403);
        }

        // Validate input
        $validated = $request->validate([
            'staffId' => 'required|string|exists:users,id',
        ], [
            'staffId.required' => 'Staff ID is required',
            'staffId.exists' => 'Staff member not found',
        ]);

        $role = Role::where('organization_code', $user->organization_code)
                    ->where('id', $id)
                    ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'error' => 'Role not found',
                'code' => 'ROLE_NOT_FOUND',
            ], 404);
        }

        // Verify staff belongs to same organization
        $staff = Staff::where('id', $validated['staffId'])
                      ->where('organization_code', $user->organization_code)
                      ->first();

        if (!$staff) {
            return response()->json([
                'success' => false,
                'error' => 'Staff member not found in your organization',
                'code' => 'STAFF_NOT_FOUND',
            ], 404);
        }

        // Check if staff already has this role
        if ($staff->roles()->where('role_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Staff member already has this role',
                'code' => 'DUPLICATE_ROLE_ASSIGNMENT',
            ], 409);
        }

        // Assign role
        $staff->roles()->attach($id, [
            'organization_code' => $user->organization_code,
            'assigned_by' => $user->id,
        ]);

        // Log audit
        $this->logAuditAction($user->id, $role->id, $staff->id, 'assign_role', [
            'role_name' => $role->name,
            'staff_name' => $staff->name,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'staffId' => $staff->id,
                'roleId' => $role->id,
                'roleName' => $role->name,
                'assignedAt' => now()->toIso8601String(),
                'assignedBy' => $user->id,
            ],
        ], 200);
    }

    /**
     * Remove role from staff
     */
    public function unassignRole(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || !$user->organization_code) {
            return response()->json([
                'success' => false,
                'error' => 'User not associated with any organization',
                'code' => 'NO_ORGANIZATION',
            ], 403);
        }

        // Check permission
        if (!($this->hasPermission($user, 'manage_staff') || $this->hasPermission($user, 'manage_roles'))) {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to unassign roles',
                'code' => 'PERMISSION_DENIED',
            ], 403);
        }

        // Validate input
        $validated = $request->validate([
            'staffId' => 'required|string|exists:users,id',
        ], [
            'staffId.required' => 'Staff ID is required',
            'staffId.exists' => 'Staff member not found',
        ]);

        $role = Role::where('organization_code', $user->organization_code)
                    ->where('id', $id)
                    ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'error' => 'Role not found',
                'code' => 'ROLE_NOT_FOUND',
            ], 404);
        }

        $staff = Staff::where('id', $validated['staffId'])
                      ->where('organization_code', $user->organization_code)
                      ->first();

        if (!$staff) {
            return response()->json([
                'success' => false,
                'error' => 'Staff member not found in your organization',
                'code' => 'STAFF_NOT_FOUND',
            ], 404);
        }

        // Check if staff has this role
        if (!$staff->roles()->where('role_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Staff member does not have this role',
                'code' => 'ROLE_NOT_ASSIGNED',
            ], 404);
        }

        // Log audit before removing
        $this->logAuditAction($user->id, $role->id, $staff->id, 'unassign_role', [
            'role_name' => $role->name,
            'staff_name' => $staff->name,
        ]);

        // Remove role
        $staff->roles()->detach($id);

        return response()->json([
            'success' => true,
            'message' => 'Role removed from staff successfully',
        ], 200);
    }

    /**
     * Format role for response
     */
    private function formatRole($role)
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'organizationCode' => $role->organization_code,
            'permissions' => $role->permissions->pluck('id')->toArray(),
            'isActive' => $role->is_active,
            'createdAt' => $role->created_at->toIso8601String(),
            'updatedAt' => $role->updated_at->toIso8601String(),
        ];
    }

    /**
     * Check if user has permission
     */
    private function hasPermission($user, $permission)
    {
        // TODO: Implement full RBAC
        // For now, only ADMIN role has all permissions
        return $user->role === 'ADMIN';
    }

    /**
     * Log audit action
     */
    private function logAuditAction($performedBy, $roleId, $staffId, $action, $changes)
    {
        RoleAuditLog::create([
            'id' => Str::uuid()->toString(),
            'role_id' => $roleId,
            'staff_id' => $staffId,
            'action' => $action,
            'changes' => $changes,
            'performed_by' => $performedBy,
            'performed_at' => now(),
        ]);
    }
}
