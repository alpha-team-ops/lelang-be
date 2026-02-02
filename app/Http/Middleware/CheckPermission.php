<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Responses\ApiResponse;

class CheckPermission
{
    /**
     * Handle an incoming request.
     * Usage: Route::middleware('permission:manage_auctions')
     */
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(
                ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401),
                401
            );
        }

        // Get user's roles for current organization
        $staffRoles = DB::table('staff_roles')
            ->where('staff_id', $user->id)
            ->where('organization_code', $user->organization_code)
            ->pluck('role_id');

        if ($staffRoles->isEmpty()) {
            return response()->json(
                ApiResponse::error('No roles assigned in this organization', 'NO_ROLE', 403),
                403
            );
        }

        // Check if user has this permission through any of their roles
        $hasPermission = DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('role_permissions.role_id', $staffRoles)
            ->where('permissions.name', $permission)
            ->exists();

        if (!$hasPermission) {
            return response()->json(
                ApiResponse::error(
                    "Permission '{$permission}' denied",
                    'PERMISSION_DENIED',
                    403
                ),
                403
            );
        }

        return $next($request);
    }
}
