<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(
                ApiResponse::error('Missing authorization token', 'MISSING_TOKEN', 401),
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

        // Set user in request
        $user = User::find($decoded['userId']);
        if (!$user || $user->status !== 'ACTIVE') {
            return response()->json(
                ApiResponse::error('User not found or inactive', 'USER_NOT_FOUND', 401),
                401
            );
        }

        // Add decoded token fields to user object (important for portal users!)
        // This ensures organizationCode, userType, etc are available from token
        if (isset($decoded['permissions'])) {
            $user->permissions = $decoded['permissions'];
        }
        
        // Add all token fields to user (critical for portal users)
        $user->token_organization_code = $decoded['organizationCode'] ?? null;
        $user->token_user_type = $decoded['userType'] ?? null;
        
        // For backwards compatibility with code that accesses organization_code directly
        // If user has organization_code field in database, keep it; otherwise use from token
        if (!$user->organization_code && isset($decoded['organizationCode'])) {
            $user->organization_code = $decoded['organizationCode'];
        }

        // Set user both ways for compatibility
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        // Also set as attribute for safe access
        $request->attributes->set('user', $user);

        return $next($request);
    }
}
