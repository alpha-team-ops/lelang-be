<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Staff;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class StaffController
{
    /**
     * Get all staff within organization
     * GET /api/v1/staff
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $organizationCode = $user->organization_code;

            // Validate user has organization
            if (!$organizationCode) {
                return response()->json(
                    ApiResponse::error('User not assigned to organization', 'NO_ORG', 403),
                    403
                );
            }

            // Check permission
            if (!$this->hasPermission($user, 'manage_users')) {
                return response()->json(
                    ApiResponse::error('Insufficient permissions', 'PERMISSION_DENIED', 403),
                    403
                );
            }

            // Get filters
            $status = $request->query('status');
            $role = $request->query('role');
            $search = $request->query('search');
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);

            // Build query
            $query = Staff::where('organization_code', $organizationCode);

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($role) {
                $query->where('role', $role);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%");
                });
            }

            // Get total count
            $total = $query->count();

            // Get paginated results
            $staff = $query->orderBy('created_at', 'desc')
                          ->paginate($limit, ['*'], 'page', $page);

            // Format response
            $staffArray = collect($staff->items())->map(fn($s) => $s->toStaffArray())->toArray();

            $response = [
                'success' => true,
                'data' => $staffArray,
                'meta' => [
                    'pagination' => [
                        'total' => $total,
                        'page' => (int)$page,
                        'limit' => (int)$limit,
                        'totalPages' => ceil($total / $limit),
                    ]
                ],
                'message' => 'Staff list retrieved successfully',
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json(
                ApiResponse::error('Failed to retrieve staff: ' . $e->getMessage(), 'INTERNAL_ERROR', 500),
                500
            );
        }
    }

    /**
     * Get staff by ID
     * GET /api/v1/staff/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $organizationCode = $user->organization_code;

            if (!$organizationCode) {
                return response()->json(
                    ApiResponse::error('User not assigned to organization', 'NO_ORG', 403),
                    403
                );
            }

            if (!$this->hasPermission($user, 'manage_users')) {
                return response()->json(
                    ApiResponse::error('Insufficient permissions', 'PERMISSION_DENIED', 403),
                    403
                );
            }

            $staff = Staff::where('organization_code', $organizationCode)
                         ->where('id', $id)
                         ->first();

            if (!$staff) {
                return response()->json(
                    ApiResponse::error('Staff not found', 'STAFF_NOT_FOUND', 404),
                    404
                );
            }

            return response()->json(
                ApiResponse::success($staff->toStaffArray(), 'Staff retrieved successfully')
            );

        } catch (\Exception $e) {
            return response()->json(
                ApiResponse::error('Failed to retrieve staff: ' . $e->getMessage(), 'INTERNAL_ERROR', 500),
                500
            );
        }
    }

    /**
     * Create new staff
     * POST /api/v1/staff
     */
    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[a-zA-Z\d@$!%*?&]{8,}$/',
            'role' => 'required|in:ADMIN,MODERATOR',
        ], [
            'name.required' => 'Name is required',
            'email.required' => 'Email is required',
            'email.email' => 'Email must be valid',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.regex' => 'Password must contain uppercase, lowercase, number, and special character',
            'role.required' => 'Role is required',
            'role.in' => 'Role must be ADMIN or MODERATOR',
        ]);

        try {
            $user = $request->user();
            $organizationCode = $user->organization_code;

            if (!$organizationCode) {
                return response()->json(
                    ApiResponse::error('User not assigned to organization', 'NO_ORG', 403),
                    403
                );
            }

            if (!$this->hasPermission($user, 'manage_users')) {
                return response()->json(
                    ApiResponse::error('Insufficient permissions', 'PERMISSION_DENIED', 403),
                    403
                );
            }

            // Check email uniqueness within organization
            $existingStaff = Staff::where('organization_code', $organizationCode)
                                 ->where('email', $validated['email'])
                                 ->first();

            if ($existingStaff) {
                return response()->json(
                    ApiResponse::error('Email already exists in organization', 'DUPLICATE_EMAIL', 409),
                    409
                );
            }

            // Create staff
            $staff = Staff::create([
                'id' => Str::uuid(),
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password_hash' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'status' => 'ACTIVE',
                'organization_code' => $organizationCode,
                'email_verified' => false,
            ]);

            // Log action
            $this->logAuditAction(
                $user->id,
                'create_staff',
                'staff',
                $staff->id,
                null,
                [
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'role' => $staff->role,
                ]
            );

            // TODO: Send welcome email to new staff

            return response()->json(
                ApiResponse::success($staff->toStaffArray(), 'Staff created successfully'),
                201
            );

        } catch (\Exception $e) {
            return response()->json(
                ApiResponse::error('Failed to create staff: ' . $e->getMessage(), 'INTERNAL_ERROR', 500),
                500
            );
        }
    }

    /**
     * Update staff
     * PUT /api/v1/staff/{id}
     */
    public function update(Request $request, $id)
    {
        // Validate input
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'status' => 'sometimes|in:ACTIVE,INACTIVE',
            'role' => 'sometimes|in:ADMIN,MODERATOR',
        ], [
            'name.string' => 'Name must be a string',
            'name.max' => 'Name cannot exceed 100 characters',
            'status.in' => 'Status must be ACTIVE or INACTIVE',
            'role.in' => 'Role must be ADMIN or MODERATOR',
        ]);

        try {
            $user = $request->user();
            $organizationCode = $user->organization_code;

            if (!$organizationCode) {
                return response()->json(
                    ApiResponse::error('User not assigned to organization', 'NO_ORG', 403),
                    403
                );
            }

            if (!$this->hasPermission($user, 'manage_users')) {
                return response()->json(
                    ApiResponse::error('Insufficient permissions', 'PERMISSION_DENIED', 403),
                    403
                );
            }

            $staff = Staff::where('organization_code', $organizationCode)
                         ->where('id', $id)
                         ->first();

            if (!$staff) {
                return response()->json(
                    ApiResponse::error('Staff not found', 'STAFF_NOT_FOUND', 404),
                    404
                );
            }

            // Business rules
            if ($staff->id === $user->id) {
                if (isset($validated['status']) && $validated['status'] === 'INACTIVE') {
                    return response()->json(
                        ApiResponse::error('Cannot deactivate own account', 'CANNOT_DEACTIVATE_SELF', 409),
                        409
                    );
                }
                if (isset($validated['role'])) {
                    return response()->json(
                        ApiResponse::error('Cannot change own role', 'CANNOT_CHANGE_OWN_ROLE', 409),
                        409
                    );
                }
            }

            // Store old values for audit
            $oldValues = [
                'name' => $staff->name,
                'status' => $staff->status,
                'role' => $staff->role,
            ];

            // Update staff
            $staff->update($validated);

            // Log action
            $this->logAuditAction(
                $user->id,
                'update_staff',
                'staff',
                $staff->id,
                $oldValues,
                array_intersect_key($validated, $oldValues)
            );

            return response()->json(
                ApiResponse::success($staff->toStaffArray(), 'Staff updated successfully')
            );

        } catch (\Exception $e) {
            return response()->json(
                ApiResponse::error('Failed to update staff: ' . $e->getMessage(), 'INTERNAL_ERROR', 500),
                500
            );
        }
    }

    /**
     * Delete staff
     * DELETE /api/v1/staff/{id}
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $organizationCode = $user->organization_code;

            if (!$organizationCode) {
                return response()->json(
                    ApiResponse::error('User not assigned to organization', 'NO_ORG', 403),
                    403
                );
            }

            if (!$this->hasPermission($user, 'manage_users')) {
                return response()->json(
                    ApiResponse::error('Insufficient permissions', 'PERMISSION_DENIED', 403),
                    403
                );
            }

            $staff = Staff::where('organization_code', $organizationCode)
                         ->where('id', $id)
                         ->first();

            if (!$staff) {
                return response()->json(
                    ApiResponse::error('Staff not found', 'STAFF_NOT_FOUND', 404),
                    404
                );
            }

            // Cannot delete own account
            if ($staff->id === $user->id) {
                return response()->json(
                    ApiResponse::error('Cannot delete own account', 'CANNOT_DELETE_OWN_ACCOUNT', 409),
                    409
                );
            }

            // Log action before deletion
            $this->logAuditAction(
                $user->id,
                'delete_staff',
                'staff',
                $staff->id,
                [
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'role' => $staff->role,
                ],
                null
            );

            // Delete staff
            $staff->delete();

            return response()->json(
                ApiResponse::success(null, 'Staff deleted successfully')
            );

        } catch (\Exception $e) {
            return response()->json(
                ApiResponse::error('Failed to delete staff: ' . $e->getMessage(), 'INTERNAL_ERROR', 500),
                500
            );
        }
    }

    /**
     * Update last activity
     * PUT /api/v1/staff/{id}/activity
     * Internal use only
     */
    public function updateActivity(Request $request, $id)
    {
        try {
            $user = $request->user();
            $organizationCode = $user->organization_code;

            if (!$organizationCode) {
                return response()->json(
                    ApiResponse::error('User not assigned to organization', 'NO_ORG', 403),
                    403
                );
            }

            $staff = Staff::where('organization_code', $organizationCode)
                         ->where('id', $id)
                         ->first();

            if (!$staff) {
                return response()->json(
                    ApiResponse::error('Staff not found', 'STAFF_NOT_FOUND', 404),
                    404
                );
            }

            // Update last activity (last_login in users table)
            $staff->update(['last_login' => now()]);

            return response()->json(
                ApiResponse::success($staff->toStaffArray(), 'Activity updated')
            );

        } catch (\Exception $e) {
            return response()->json(
                ApiResponse::error('Failed to update activity: ' . $e->getMessage(), 'INTERNAL_ERROR', 500),
                500
            );
        }
    }

    /**
     * Check if user has permission
     */
    private function hasPermission($user, $permission)
    {
        // ADMIN role has all permissions
        if ($user->role === 'ADMIN') {
            return true;
        }

        // TODO: Implement role-based permissions checking
        // For now, only ADMIN can manage users
        return false;
    }

    /**
     * Log audit action
     */
    private function logAuditAction(
        $userId,
        $action,
        $resourceType,
        $resourceId,
        $oldValue = null,
        $newValue = null
    )
    {
        try {
            DB::table('org_settings_history')->insert([
                'id' => Str::uuid(),
                'organization_code' => $resourceId, // For now using this, could be improved
                'changed_by' => $userId,
                'field_name' => $action,
                'old_value' => $oldValue ? json_encode($oldValue) : null,
                'new_value' => $newValue ? json_encode($newValue) : null,
                'changed_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the main operation
            \Log::error('Failed to log audit action: ' . $e->getMessage());
        }
    }
}
