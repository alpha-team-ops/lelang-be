# Authentication API Documentation

**Version:** 1.0.0  
**Priority:** Phase 1 - Foundation (Must build first)  
**Organization Code:** ORG-DERALY-001

---

## Overview

The Authentication API handles user login, registration, token generation, and token refresh. This is a foundational API that must be implemented first before any other endpoints.

**Base URL:** `/api/v1/auth`

---

## Data Models

### User Login Request
```typescript
interface LoginRequest {
  email: string;
  password: string;
}
```

### User Registration Request
```typescript
interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  organizationCode: string; // Can be predefined or new
}
```

### JWT Token Response
```typescript
interface TokenResponse {
  accessToken: string;  // Bearer token
  refreshToken: string; // For token refresh
  expiresIn: number;    // Seconds (default: 3600 = 1 hour)
  tokenType: string;    // Always "Bearer"
}
```

### JWT Payload (Claims)
```typescript
interface JWTPayload {
  userId: string;
  email: string;
  name: string;
  role: 'ADMIN' | 'MODERATOR';
  organizationCode: string;
  permissions: string[];
  iat: number;          // Issued at (Unix timestamp)
  exp: number;          // Expiration (Unix timestamp)
}
```

---

## Endpoints

### 1. User Login
**Endpoint:** `POST /api/v1/auth/login`

**Description:** Authenticate user and return JWT tokens

**Request Body:**
```json
{
  "email": "alpha.dev@deraly.id",
  "password": "SecurePassword123!"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "accessToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOiIxIiwibmFtZSI6IkFscGhhIERldiIsImVtYWlsIjoiYWxwaGEuZGV2QGRlcmFseS5pZCIsInJvbGUiOiJBRE1JTiIsIm9yZ2FuaXphdGlvbkNvZGUiOiJPUkctREVSQUxZLTAwMSIsInBlcm1pc3Npb25zIjpbIm1hbmFnZV91c2VycyIsIm1hbmFnZV9hdWN0aW9ucyJdLCJpYXQiOjE3MDQwNjcyMDAsImV4cCI6MTcwNDA3MDgwMH0.signature",
    "refreshToken": "refresh_token_here_very_long_string",
    "expiresIn": 3600,
    "tokenType": "Bearer"
  }
}
```

**Response (401 Unauthorized):**
```json
{
  "success": false,
  "error": "Invalid email or password",
  "code": "INVALID_CREDENTIALS"
}
```

**Validation Rules:**
- Email required and must be valid format
- Password required, min 8 characters
- Email/password combination must exist
- Account must be ACTIVE status

**Business Rules:**
- Return user's organization code from database
- Include user's permissions in token based on role
- Set token expiration to 1 hour
- Log login attempt (success/failure)

---

### 2. User Registration
**Endpoint:** `POST /api/v1/auth/register`

**Description:** Create new user account

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john.doe@example.com",
  "password": "SecurePassword123!",
  "organizationCode": "ORG-DERALY-001"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "userId": "user-uuid-here",
    "name": "John Doe",
    "email": "john.doe@example.com",
    "organizationCode": "ORG-DERALY-001",
    "role": "MODERATOR",
    "status": "ACTIVE"
  }
}
```

**Response (400 Bad Request):**
```json
{
  "success": false,
  "error": "Email already registered",
  "code": "DUPLICATE_EMAIL"
}
```

**Validation Rules:**
- Name required, 2-100 characters
- Email required, valid format, unique per organization
- Password required:
  - Min 8 characters
  - At least 1 uppercase
  - At least 1 lowercase
  - At least 1 number
  - At least 1 special character (!@#$%^&*)
- Organization code must exist in system

**Business Rules:**
- Default role for new users: MODERATOR
- Default status: ACTIVE
- Hash password with bcrypt (min 10 rounds)
- Send verification email (optional but recommended)
- Log registration event
- Auto-join organization

---

### 3. Refresh Access Token
**Endpoint:** `POST /api/v1/auth/refresh`

**Description:** Get new access token using refresh token

**Headers:**
```
Authorization: Bearer <REFRESH_TOKEN>
```

**Request Body:**
```json
{
  "refreshToken": "refresh_token_from_login"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "accessToken": "new_access_token_here",
    "expiresIn": 3600,
    "tokenType": "Bearer"
  }
}
```

**Response (401 Unauthorized):**
```json
{
  "success": false,
  "error": "Invalid or expired refresh token",
  "code": "INVALID_REFRESH_TOKEN"
}
```

**Business Rules:**
- Refresh token must be valid and not expired (default: 7 days)
- Return new access token with same permissions
- Old access token still works until expiration
- Keep refresh token same or optionally rotate it
- Log token refresh event

---

### 4. Logout
**Endpoint:** `POST /api/v1/auth/logout`

**Description:** Invalidate user session

**Headers:**
```
Authorization: Bearer <ACCESS_TOKEN>
```

**Request Body:**
```json
{
  "refreshToken": "refresh_token_to_invalidate"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

**Business Rules:**
- Invalidate refresh token immediately
- Add access token to blacklist (optional, for immediate logout)
- Log logout event with timestamp
- Clear any sessions in database

---

### 5. Verify Token
**Endpoint:** `GET /api/v1/auth/verify`

**Description:** Verify if access token is valid (useful for frontend)

**Headers:**
```
Authorization: Bearer <ACCESS_TOKEN>
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "valid": true,
    "user": {
      "userId": "1",
      "email": "alpha.dev@deraly.id",
      "name": "Alpha Dev",
      "role": "ADMIN",
      "organizationCode": "ORG-DERALY-001",
      "permissions": ["manage_users", "manage_auctions", "view_analytics"]
    }
  }
}
```

**Response (401 Unauthorized):**
```json
{
  "success": false,
  "error": "Token is invalid or expired",
  "code": "INVALID_TOKEN"
}
```

---

### 6. Change Password
**Endpoint:** `POST /api/v1/auth/change-password`

**Description:** Change user's password

**Headers:**
```
Authorization: Bearer <ACCESS_TOKEN>
```

**Request Body:**
```json
{
  "currentPassword": "OldPassword123!",
  "newPassword": "NewPassword456!"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

**Response (401 Unauthorized):**
```json
{
  "success": false,
  "error": "Current password is incorrect",
  "code": "INVALID_PASSWORD"
}
```

**Validation Rules:**
- Current password must match user's current password
- New password must meet strength requirements
- New password cannot be same as current
- New password min 8 chars with mixed case, numbers, special chars

**Business Rules:**
- Hash new password with bcrypt
- Invalidate all existing refresh tokens (force re-login elsewhere)
- Send confirmation email
- Log password change event

---

### 7. Request Password Reset
**Endpoint:** `POST /api/v1/auth/forgot-password`

**Description:** Send password reset email

**Request Body:**
```json
{
  "email": "alpha.dev@deraly.id"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Password reset link sent to email"
}
```

**Note:** Response same whether email exists or not (security)

**Business Rules:**
- Generate reset token valid for 1 hour
- Send email with reset link containing token
- Log reset request event
- Don't reveal if email exists in system

---

### 8. Reset Password
**Endpoint:** `POST /api/v1/auth/reset-password`

**Description:** Reset password using token from email

**Request Body:**
```json
{
  "token": "reset_token_from_email",
  "newPassword": "NewPassword456!"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Password reset successfully"
}
```

**Response (400 Bad Request):**
```json
{
  "success": false,
  "error": "Reset token is invalid or expired",
  "code": "INVALID_RESET_TOKEN"
}
```

**Validation Rules:**
- Reset token must be valid and not expired
- New password must meet strength requirements
- Token can be used only once

**Business Rules:**
- Hash new password
- Invalidate all existing refresh tokens
- Mark reset token as used
- Send confirmation email
- Log password reset event

---

## Error Codes

```
INVALID_CREDENTIALS - Wrong email/password
DUPLICATE_EMAIL - Email already registered
WEAK_PASSWORD - Password doesn't meet requirements
INVALID_TOKEN - Token expired or invalid
INVALID_REFRESH_TOKEN - Refresh token invalid/expired
INVALID_PASSWORD - Current password incorrect
INVALID_RESET_TOKEN - Reset token invalid/expired
ACCOUNT_DISABLED - Account status is not ACTIVE
INVALID_ORGANIZATION - Organization code doesn't exist
```

---

## Security Considerations

### Password Security
- Hash with bcrypt (rounds: 10+)
- Never store plain passwords
- Never return password in response
- Enforce strong password policy

### Token Security
- Sign JWT with HS256 algorithm
- Use strong secret key (min 32 chars)
- Short expiration for access token (1 hour)
- Longer expiration for refresh token (7 days)
- Store refresh token securely in database

### API Security
- Rate limit login attempts (5 per minute per IP)
- Rate limit registration (2 per minute per IP)
- Implement CAPTCHA after 3 failed attempts
- Log all authentication events
- Monitor for suspicious patterns

### Transport Security
- Always use HTTPS in production
- Set secure flag on refresh token cookie
- Use httpOnly flag for token storage
- Implement CORS properly

---

## Database Schema

### users table
```sql
CREATE TABLE users (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ADMIN', 'MODERATOR') DEFAULT 'MODERATOR',
  status ENUM('ACTIVE', 'INACTIVE') DEFAULT 'ACTIVE',
  organization_code VARCHAR(50) NOT NULL,
  email_verified BOOLEAN DEFAULT FALSE,
  last_login TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (organization_code) REFERENCES organizations(code),
  INDEX idx_email (email),
  INDEX idx_organization (organization_code),
  INDEX idx_status (status)
);

CREATE TABLE refresh_tokens (
  id VARCHAR(36) PRIMARY KEY,
  user_id VARCHAR(36) NOT NULL,
  token_hash VARCHAR(255) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  revoked BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_expires_at (expires_at)
);

CREATE TABLE password_reset_tokens (
  id VARCHAR(36) PRIMARY KEY,
  user_id VARCHAR(36) NOT NULL,
  token_hash VARCHAR(255) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  used BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id)
);

CREATE TABLE auth_logs (
  id VARCHAR(36) PRIMARY KEY,
  user_id VARCHAR(36),
  email VARCHAR(100),
  action VARCHAR(50),
  status VARCHAR(20),
  ip_address VARCHAR(50),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_user_id (user_id),
  INDEX idx_email (email),
  INDEX idx_created_at (created_at)
);
```

---

## Testing Checklist

- [ ] Login with valid credentials
- [ ] Login with invalid password
- [ ] Login with non-existent email
- [ ] Register with valid data
- [ ] Register with duplicate email
- [ ] Register with weak password
- [ ] Refresh token successfully
- [ ] Refresh with invalid token
- [ ] Verify valid token
- [ ] Verify invalid/expired token
- [ ] Logout and invalidate refresh token
- [ ] Change password successfully
- [ ] Change password with wrong current password
- [ ] Password reset flow (request → email → reset)
- [ ] Rate limiting on login attempts
- [ ] CORS headers properly set
- [ ] HTTPS enforcement in production
- [ ] Token expiration timing
- [ ] Concurrent logins from same user
- [ ] Database transaction consistency

---

## Related Endpoints

After implementing auth, proceed to:
1. [API_02_ORGANIZATION_SETTINGS.md](API_02_ORGANIZATION_SETTINGS.md) - Organization setup
2. [API_03_STAFF_USERS.md](API_03_STAFF_USERS.md) - User management
