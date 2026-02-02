<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuthLog;
use App\Models\Organization;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\AuthService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * User Login
     * POST /api/v1/auth/login
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|min:8',
            ]);

            // Log attempt
            $this->logAuthAction($request->input('email'), 'login', 'pending', $request);

            // Find user
            $user = User::where('email', $validated['email'])
                ->where('status', 'ACTIVE')
                ->first();

            if (!$user || !$this->authService->verifyPassword($validated['password'], $user->password_hash)) {
                $this->logAuthAction($validated['email'], 'login', 'failed', $request);
                return response()->json(
                    ApiResponse::error('Invalid email or password'),
                    401
                );
            }

            // Update last login
            $user->update(['last_login' => now()]);

            // Generate tokens
            $tokens = $this->authService->generateTokens($user);

            // Log success
            $this->logAuthAction($user->email, 'login', 'success', $request, $user->id);

            return response()->json(
                ApiResponse::success($tokens, 'Login successful'),
                200
            );
        } catch (ValidationException $e) {
            return response()->json(
                ApiResponse::error('Validation failed'),
                422
            );
        }
    }

    /**
     * User Registration
     * POST /api/v1/auth/register
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|min:2|max:100',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:8',
                'organizationCode' => 'nullable|exists:organizations,code',
            ]);

            // Validate password strength
            $passwordErrors = $this->authService->validatePasswordStrength($validated['password']);
            if (!empty($passwordErrors)) {
                return response()->json(
                    ApiResponse::error(
                        implode('; ', $passwordErrors),
                        'WEAK_PASSWORD',
                        400
                    ),
                    400
                );
            }

            // Create user in transaction
            $user = DB::transaction(function () use ($validated) {
                return User::create([
                    'id' => (string) Str::uuid(),
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password_hash' => $this->authService->hashPassword($validated['password']),
                    'role' => 'MEMBER',
                    'status' => 'ACTIVE',
                    'organization_code' => $validated['organizationCode'] ?? null,
                ]);
            });

            // Log registration
            $this->logAuthAction($user->email, 'register', 'success', $request, $user->id);

            return response()->json(
                ApiResponse::success([
                    'userId' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'organizationCode' => $user->organization_code,
                    'role' => $user->role,
                    'status' => $user->status,
                ], 'Registration successful'),
                201
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['email'])) {
                return response()->json(
                    ApiResponse::error('Email already registered', 'DUPLICATE_EMAIL', 400),
                    400
                );
            }
            if (isset($errors['organizationCode'])) {
                return response()->json(
                    ApiResponse::error('Organization does not exist', 'INVALID_ORGANIZATION', 400),
                    400
                );
            }
            return response()->json(
                ApiResponse::error('Validation failed', 'VALIDATION_ERROR', 422),
                422
            );
        }
    }

    /**
     * Refresh Access Token
     * POST /api/v1/auth/refresh
     */
    public function refresh(Request $request)
    {
        try {
            $validated = $request->validate([
                'refreshToken' => 'required|string',
            ]);

            // Get user from authorization header (optional, for security)
            $token = $request->bearerToken();
            if ($token) {
                $decoded = $this->authService->verifyAccessToken($token);
                if (!$decoded) {
                    return response()->json(
                        ApiResponse::error('Invalid or expired access token', 'INVALID_TOKEN', 401),
                        401
                    );
                }
                $userId = $decoded['userId'];
            } else {
                // Try to get user_id from refresh token
                $tokenHash = hash('sha256', $validated['refreshToken']);
                $refreshToken = DB::table('refresh_tokens')
                    ->where('token_hash', $tokenHash)
                    ->where('revoked', false)
                    ->where('expires_at', '>', now())
                    ->first();

                if (!$refreshToken) {
                    return response()->json(
                        ApiResponse::error('Invalid or expired refresh token', 'INVALID_REFRESH_TOKEN', 401),
                        401
                    );
                }
                $userId = $refreshToken->user_id;
            }

            $user = User::find($userId);
            if (!$user) {
                return response()->json(
                    ApiResponse::error('User not found', 'USER_NOT_FOUND', 401),
                    401
                );
            }

            // Generate new access token
            $tokens = $this->authService->generateTokens($user);

            return response()->json(
                ApiResponse::success($tokens, 'Token refreshed successfully'),
                200
            );
        } catch (ValidationException $e) {
            return response()->json(
                ApiResponse::error('Validation failed', 'VALIDATION_ERROR', 422),
                422
            );
        }
    }

    /**
     * Logout
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request)
    {
        try {
            $validated = $request->validate([
                'refreshToken' => 'required|string',
            ]);

            // Get user from token
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(
                    ApiResponse::error('Missing access token', 'MISSING_TOKEN', 401),
                    401
                );
            }

            $decoded = $this->authService->verifyAccessToken($token);
            if (!$decoded) {
                return response()->json(
                    ApiResponse::error('Invalid or expired token', 'INVALID_TOKEN', 401),
                    401
                );
            }

            $user = User::find($decoded['userId']);
            if (!$user) {
                return response()->json(
                    ApiResponse::error('User not found', 'USER_NOT_FOUND', 401),
                    401
                );
            }

            // Revoke refresh token
            $this->authService->revokeRefreshToken($validated['refreshToken']);

            // Log logout
            $this->logAuthAction($user->email, 'logout', 'success', $request, $user->id);

            return response()->json(
                ApiResponse::success(null, 'Logged out successfully'),
                200
            );
        } catch (ValidationException $e) {
            return response()->json(
                ApiResponse::error('Validation failed', 'VALIDATION_ERROR', 422),
                422
            );
        }
    }

    /**
     * Verify Token
     * GET /api/v1/auth/verify
     */
    public function verify(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(
                ApiResponse::error('Missing access token', 'MISSING_TOKEN', 401),
                401
            );
        }

        $decoded = $this->authService->verifyAccessToken($token);
        if (!$decoded) {
            return response()->json(
                ApiResponse::error('Token is invalid or expired', 'INVALID_TOKEN', 401),
                401
            );
        }

        $user = User::find($decoded['userId']);
        if (!$user || $user->status !== 'ACTIVE') {
            return response()->json(
                ApiResponse::error('User not found or inactive', 'USER_NOT_FOUND', 401),
                401
            );
        }

        return response()->json(
            ApiResponse::success([
                'valid' => true,
                'user' => [
                    'userId' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $user->role,
                    'organizationCode' => $user->organization_code,
                    'permissions' => $this->authService->getPermissions($user),
                ],
            ], 'Token is valid'),
            200
        );
    }

    /**
     * Get Current User with Permissions
     * GET /api/v1/auth/me
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(
                ApiResponse::error('User not found', 'USER_NOT_FOUND', 401),
                401
            );
        }

        // Get user's roles and permissions
        $staffRoles = DB::table('staff_roles')
            ->where('staff_id', $user->id)
            ->where('organization_code', $user->organization_code)
            ->get();

        $permissions = [];
        foreach ($staffRoles as $staffRole) {
            $rolePerms = DB::table('role_permissions')
                ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->where('role_permissions.role_id', $staffRole->role_id)
                ->select('permissions.name', 'permissions.resource', 'permissions.action')
                ->distinct()
                ->get();
            
            foreach ($rolePerms as $perm) {
                $permissions[] = $perm->name;
            }
        }

        // Remove duplicates
        $permissions = array_values(array_unique($permissions));

        $roles = [];
        foreach ($staffRoles as $sr) {
            $role = DB::table('roles')->where('id', $sr->role_id)->first();
            if ($role) {
                $roles[] = [
                    'roleId' => $sr->role_id,
                    'roleName' => $role->name,
                    'organizationCode' => $sr->organization_code,
                    'assignedAt' => $sr->assigned_at,
                ];
            }
        }

        return response()->json(
            ApiResponse::success([
                'userId' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'organizationCode' => $user->organization_code,
                'role' => $user->role,
                'roles' => $roles,
                'permissions' => $permissions,
            ], 'Current user data retrieved'),
            200
        );
    }

    /**
     * Check if User has Specific Permission
     * POST /api/v1/auth/check-permission
     */
    public function checkPermission(Request $request)
    {
        $validated = $request->validate([
            'permission' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(
                ApiResponse::error('User not found', 'USER_NOT_FOUND', 401),
                401
            );
        }

        // Get user's roles for current organization
        $staffRoles = DB::table('staff_roles')
            ->where('staff_id', $user->id)
            ->where('organization_code', $user->organization_code)
            ->pluck('role_id');

        // Check if user has this permission through any of their roles
        $hasPermission = DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('role_permissions.role_id', $staffRoles)
            ->where('permissions.name', $validated['permission'])
            ->exists();

        return response()->json(
            ApiResponse::success([
                'permission' => $validated['permission'],
                'hasPermission' => $hasPermission,
            ], $hasPermission ? 'User has this permission' : 'User does not have this permission'),
            200
        );
    }

    /**
     * Change Password
     * POST /api/v1/auth/change-password
     */
    public function changePassword(Request $request)
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(
                    ApiResponse::error('Missing access token', 'MISSING_TOKEN', 401),
                    401
                );
            }

            $decoded = $this->authService->verifyAccessToken($token);
            if (!$decoded) {
                return response()->json(
                    ApiResponse::error('Invalid or expired token', 'INVALID_TOKEN', 401),
                    401
                );
            }

            $user = User::find($decoded['userId']);
            if (!$user) {
                return response()->json(
                    ApiResponse::error('User not found', 'USER_NOT_FOUND', 401),
                    401
                );
            }

            $validated = $request->validate([
                'currentPassword' => 'required|min:8',
                'newPassword' => 'required|min:8',
            ]);

            // Verify current password
            if (!$this->authService->verifyPassword($validated['currentPassword'], $user->password_hash)) {
                return response()->json(
                    ApiResponse::error('Current password is incorrect', 'INVALID_PASSWORD', 401),
                    401
                );
            }

            // Validate new password strength
            $passwordErrors = $this->authService->validatePasswordStrength($validated['newPassword']);
            if (!empty($passwordErrors)) {
                return response()->json(
                    ApiResponse::error(
                        implode('; ', $passwordErrors),
                        'WEAK_PASSWORD',
                        400
                    ),
                    400
                );
            }

            if ($validated['currentPassword'] === $validated['newPassword']) {
                return response()->json(
                    ApiResponse::error('New password must be different from current password', 'SAME_PASSWORD', 400),
                    400
                );
            }

            // Update password in transaction
            DB::transaction(function () use ($user, $validated) {
                $user->update([
                    'password_hash' => $this->authService->hashPassword($validated['newPassword']),
                ]);

                // Revoke all refresh tokens (force re-login)
                $this->authService->revokeAllRefreshTokens($user);
            });

            // Log password change
            $this->logAuthAction($user->email, 'change_password', 'success', $request, $user->id);

            return response()->json(
                ApiResponse::success(null, 'Password changed successfully'),
                200
            );
        } catch (ValidationException $e) {
            return response()->json(
                ApiResponse::error('Validation failed', 'VALIDATION_ERROR', 422),
                422
            );
        }
    }

    /**
     * Request Password Reset
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
            ]);

            $user = User::where('email', $validated['email'])->first();

            // Always return same message for security
            if ($user) {
                // Generate reset token
                $resetToken = Str::random(64);
                $tokenHash = hash('sha256', $resetToken);

                PasswordResetToken::create([
                    'user_id' => $user->id,
                    'token_hash' => $tokenHash,
                    'expires_at' => now()->addHour(),
                ]);

                // TODO: Send email with reset link
                // Mail::send('emails.password-reset', [...], ...);

                $this->logAuthAction($user->email, 'forgot_password', 'success', $request, $user->id);
            } else {
                $this->logAuthAction($validated['email'], 'forgot_password', 'not_found', $request);
            }

            return response()->json(
                ApiResponse::success(null, 'Password reset link sent to email'),
                200
            );
        } catch (ValidationException $e) {
            return response()->json(
                ApiResponse::error('Validation failed', 'VALIDATION_ERROR', 422),
                422
            );
        }
    }

    /**
     * Reset Password
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
                'newPassword' => 'required|min:8',
            ]);

            // Verify reset token
            $tokenHash = hash('sha256', $validated['token']);
            $resetToken = PasswordResetToken::where('token_hash', $tokenHash)
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$resetToken) {
                return response()->json(
                    ApiResponse::error('Reset token is invalid or expired', 'INVALID_RESET_TOKEN', 400),
                    400
                );
            }

            $user = $resetToken->user;

            // Validate password strength
            $passwordErrors = $this->authService->validatePasswordStrength($validated['newPassword']);
            if (!empty($passwordErrors)) {
                return response()->json(
                    ApiResponse::error(
                        implode('; ', $passwordErrors),
                        'WEAK_PASSWORD',
                        400
                    ),
                    400
                );
            }

            // Update password in transaction
            DB::transaction(function () use ($user, $resetToken, $validated) {
                $user->update([
                    'password_hash' => $this->authService->hashPassword($validated['newPassword']),
                ]);

                $resetToken->update(['used' => true]);

                // Revoke all refresh tokens
                $this->authService->revokeAllRefreshTokens($user);
            });

            $this->logAuthAction($user->email, 'reset_password', 'success', $request, $user->id);

            return response()->json(
                ApiResponse::success(null, 'Password reset successfully'),
                200
            );
        } catch (ValidationException $e) {
            return response()->json(
                ApiResponse::error('Validation failed', 'VALIDATION_ERROR', 422),
                422
            );
        }
    }

    /**
     * Portal User Login (First-Time Access)
     * POST /api/v1/auth/portal-login
     * 
     * Auto-creates portal user if doesn't exist (based on corporateIdNip)
     * Or loads existing user if already registered
     */
    public function portalLogin(Request $request)
    {
        try {
            $validated = $request->validate([
                'fullName' => 'required|string|min:3|max:100',
                'corporateIdNip' => 'required|string|min:3|max:50',
                'directorate' => 'required|string|min:2|max:100',
                'invitationCode' => 'required|string',
            ]);

            // Validate invitation code and get organization
            $organization = Organization::where('portal_invitation_code', $validated['invitationCode'])
                ->where('portal_invitation_active', true)
                ->first();

            if (!$organization) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid or inactive invitation code',
                    'code' => 'INVALID_INVITATION_CODE'
                ], 400);
            }

            // Log attempt
            $this->logAuthAction($validated['corporateIdNip'], 'portal_login', 'pending', $request);

            // Check if portal user already exists with this NIP
            $user = User::where('corporate_id_nip', $validated['corporateIdNip'])
                ->where('organization_code', $organization->code)
                ->where('user_type', 'PORTAL')
                ->first();

            $isNewUser = false;

            if ($user) {
                // Update existing user's name if different
                if ($user->name !== $validated['fullName']) {
                    $user->update(['name' => $validated['fullName']]);
                }
                $user->update(['last_login' => now()]);
            } else {
                // Create new portal user
                $user = User::create([
                    'id' => (string) Str::uuid(),
                    'name' => $validated['fullName'],
                    'corporate_id_nip' => $validated['corporateIdNip'],
                    'directorate' => $validated['directorate'],
                    'organization_code' => $organization->code,
                    'user_type' => 'PORTAL',
                    'role' => 'MEMBER', // Portal users use MEMBER role
                    'status' => 'ACTIVE',
                    'email' => Str::uuid() . '@portal.local', // Generate dummy email
                    'password_hash' => null,
                    'last_login' => now(),
                ]);
                $isNewUser = true;
            }

            // Generate portal token (JWT with 1 hour expiry)
            $token = $this->authService->generatePortalToken($user);

            // Log success
            $this->logAuthAction($validated['corporateIdNip'], 'portal_login', 'success', $request, $user->id);

            $statusCode = $isNewUser ? 201 : 200;

            return response()->json([
                'success' => true,
                'data' => [
                    'userId' => $user->id,
                    'portalToken' => $token,
                    'expiresIn' => 3600, // 1 hour
                    'message' => $isNewUser ? 'User registered successfully' : 'User loaded successfully',
                    'isNewUser' => $isNewUser,
                ]
            ], $statusCode);
        } catch (ValidationException $e) {
            $this->logAuthAction($request->input('corporateIdNip', 'unknown'), 'portal_login', 'failed', $request);
            
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'code' => 'VALIDATION_ERROR',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logAuthAction($request->input('corporateIdNip', 'unknown'), 'portal_login', 'failed', $request);
            
            return response()->json([
                'success' => false,
                'error' => 'Portal login failed',
                'code' => 'PORTAL_LOGIN_ERROR'
            ], 500);
        }
    }

    /**
     * Log authentication action
     */
    protected function logAuthAction(string $email, string $action, string $status, Request $request, ?string $userId = null): void
    {
        AuthLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'email' => $email,
            'action' => $action,
            'status' => $status,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
