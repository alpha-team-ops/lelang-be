<?php

namespace App\Services;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthService
{
    protected string $jwtSecret;
    protected int $accessTokenExpiry = 3600; // 1 hour
    protected int $refreshTokenExpiry = 604800; // 7 days

    public function __construct()
    {
        $this->jwtSecret = config('app.key') ?: env('APP_KEY', 'base64:' . base64_encode(Str::random(32)));
        // Remove 'base64:' prefix if exists
        if (str_starts_with($this->jwtSecret, 'base64:')) {
            $this->jwtSecret = base64_decode(substr($this->jwtSecret, 7));
        }
    }

    /**
     * Generate JWT tokens for user
     */
    public function generateTokens(User $user): array
    {
        $accessToken = $this->generateAccessToken($user);
        $refreshToken = $this->generateAndSaveRefreshToken($user);

        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'expiresIn' => $this->accessTokenExpiry,
            'tokenType' => 'Bearer',
        ];
    }

    /**
     * Generate access token (JWT)
     */
    public function generateAccessToken(User $user): string
    {
        $issuedAt = now()->timestamp;
        $expire = now()->addSeconds($this->accessTokenExpiry)->timestamp;

        $payload = [
            'userId' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'organizationCode' => $user->organization_code,
            'permissions' => $this->getPermissions($user->role),
            'iat' => $issuedAt,
            'exp' => $expire,
        ];

        return $this->encodeJWT($payload);
    }

    /**
     * Generate and save refresh token
     */
    public function generateAndSaveRefreshToken(User $user): string
    {
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addSeconds($this->refreshTokenExpiry),
        ]);

        return $token;
    }

    /**
     * Verify and decode JWT token
     */
    public function verifyAccessToken(string $token): ?array
    {
        try {
            return $this->decodeJWT($token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verify refresh token
     */
    public function verifyRefreshToken(string $token, User $user): bool
    {
        $tokenHash = hash('sha256', $token);

        $refreshToken = RefreshToken::where('user_id', $user->id)
            ->where('token_hash', $tokenHash)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        return $refreshToken !== null;
    }

    /**
     * Get permissions based on role
     */
    public function getPermissions(string $role): array
    {
        return match ($role) {
            'ADMIN' => ['manage_users', 'manage_auctions', 'view_analytics'],
            'MODERATOR' => ['manage_auctions', 'view_analytics'],
            default => [],
        };
    }

    /**
     * Revoke refresh token
     */
    public function revokeRefreshToken(string $token): bool
    {
        $tokenHash = hash('sha256', $token);

        return RefreshToken::where('token_hash', $tokenHash)
            ->update(['revoked' => true]) > 0;
    }

    /**
     * Revoke all refresh tokens for user (force logout from all devices)
     */
    public function revokeAllRefreshTokens(User $user): bool
    {
        return RefreshToken::where('user_id', $user->id)
            ->update(['revoked' => true]) > 0;
    }

    /**
     * Validate password strength
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least 1 uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least 1 lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least 1 number';
        }
        if (!preg_match('/[!@#$%^&*]/', $password)) {
            $errors[] = 'Password must contain at least 1 special character (!@#$%^&*)';
        }

        return $errors;
    }

    /**
     * Hash password
     */
    public function hashPassword(string $password): string
    {
        return Hash::make($password);
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return Hash::check($password, $hash);
    }

    /**
     * Encode JWT token
     */
    protected function encodeJWT(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac(
            'sha256',
            "$headerEncoded.$payloadEncoded",
            $this->jwtSecret,
            true
        );
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Decode JWT token
     */
    protected function decodeJWT(string $token): array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new \Exception('Invalid token format');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $signature = hash_hmac(
            'sha256',
            "$headerEncoded.$payloadEncoded",
            $this->jwtSecret,
            true
        );
        $expectedSignature = $this->base64UrlEncode($signature);

        if (!hash_equals($expectedSignature, $signatureEncoded)) {
            throw new \Exception('Invalid token signature');
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            throw new \Exception('Invalid payload');
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \Exception('Token expired');
        }

        return $payload;
    }

    /**
     * Base64 URL encode
     */
    protected function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Base64 URL decode
     */
    protected function base64UrlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $padded));
    }
}

