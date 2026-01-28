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
                'organizationCode' => 'required|exists:organizations,code',
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
                    'role' => 'MODERATOR',
                    'status' => 'ACTIVE',
                    'organization_code' => $validated['organizationCode'],
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
                    'permissions' => $this->authService->getPermissions($user->role),
                ],
            ], 'Token is valid'),
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
