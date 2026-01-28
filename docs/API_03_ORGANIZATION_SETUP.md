# Organization Setup API Documentation

**Version:** 1.0.0  
**Priority:** Phase 1 - Foundation  
**Depends on:** API_01_AUTHENTICATION.md, API_02_ORGANIZATION_SETTINGS.md  
**Status:** ⏳ To Be Implemented

---

## Overview

Manages organization creation and joining for new users during onboarding. These endpoints are critical for the post-login organization setup flow.

**Base URL:** `/api/v1/organization`

---

## Data Model

```typescript
interface CreateOrganizationRequest {
  organizationName: string;  // Required, min 3 chars, max 100
  description: string;       // Optional, max 500 chars
}

interface JoinOrganizationRequest {
  organizationCode: string;  // Required, must be uppercase
}

interface OrganizationSetupResponse {
  organizationCode: string;  // Unique org identifier
  name: string;              // Organization name
  description: string;       // Organization description
  createdAt?: string;        // ISO 8601 timestamp (create only)
  createdBy?: string;        // User ID who created (create only)
}
```

---

## Endpoints

### 1. Create Organization
**Endpoint:** `POST /api/v1/organization/create`

**Authentication:**
- Required: Bearer Token (JWT)
- User must be authenticated

**Headers:**
```
Authorization: Bearer <ACCESS_TOKEN>
Content-Type: application/json
```

**Request Body:**
```json
{
  "organizationName": "PT. Deraly Lelang Indonesia",
  "description": "Platform lelang online terpercaya untuk berbagai kategori produk"
}
```

**Request Validation:**
| Field | Type | Required | Rules |
|-------|------|----------|-------|
| organizationName | string | Yes | Min 3 chars, Max 100 chars, alphanumeric + spaces allowed |
| description | string | No | Max 500 chars |

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "organizationCode": "ORG-PTDERALY-001",
    "name": "PT. Deraly Lelang Indonesia",
    "description": "Platform lelang online terpercaya untuk berbagai kategori produk",
    "createdAt": "2026-01-28T10:30:00Z",
    "createdBy": "user-id-12345"
  }
}
```

**Response (400 Bad Request):**
```json
{
  "success": false,
  "error": "Invalid input data",
  "code": "INVALID_INPUT",
  "details": {
    "fields": [
      {
        "field": "organizationName",
        "message": "Organization name must be between 3 and 100 characters"
      }
    ]
  }
}
```

**Response (409 Conflict):**
```json
{
  "success": false,
  "error": "Organization name already exists",
  "code": "ORG_NAME_EXISTS"
}
```

**Response (401 Unauthorized):**
```json
{
  "success": false,
  "error": "Authentication required",
  "code": "UNAUTHORIZED"
}
```

**Business Logic:**
1. Verify user is authenticated
2. Validate input data
3. Generate unique `organizationCode`:
   - Format: `ORG-{FIRST 8 CHARS OF NAME}-{SEQUENCE}`
   - Example: `ORG-PTDERAL-001`, `ORG-PTDERAL-002`
   - Must be unique in database
4. Create organization record with:
   - organizationCode (generated)
   - name (from request)
   - description (from request)
   - createdAt (current timestamp)
   - createdBy (current user ID)
   - Default settings:
     - timezone: "Asia/Jakarta"
     - currency: "IDR"
     - language: "id"
     - emailNotifications: true
     - auctionNotifications: true
     - bidNotifications: true
     - twoFactorAuth: false
     - maintenanceMode: false
5. Update user record:
   - Set user.organizationCode = generated code
   - Set user.role = "ADMIN" or "OWNER" (in their org)
6. Create audit log entry:
   - action: "ORGANIZATION_CREATED"
   - userId: authenticated user ID
   - organizationCode: new org code
   - timestamp: current
7. Return success response with org details

**Permissions Required:**
- Must be authenticated (any authenticated user can create org)

**Database Changes Required:**
- Insert into `organizations` table
- Update `users` table: set organizationCode where id = current_user_id
- Insert into audit log

---

### 2. Join Organization
**Endpoint:** `POST /api/v1/organization/join`

**Authentication:**
- Required: Bearer Token (JWT)
- User must be authenticated
- User must NOT already have an organization

**Headers:**
```
Authorization: Bearer <ACCESS_TOKEN>
Content-Type: application/json
```

**Request Body:**
```json
{
  "organizationCode": "ORG-DERALY-001"
}
```

**Request Validation:**
| Field | Type | Required | Rules |
|-------|------|----------|-------|
| organizationCode | string | Yes | Must be uppercase, alphanumeric, 3-50 chars |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "organizationCode": "ORG-DERALY-001",
    "name": "PT. Deraly",
    "description": "Platform lelang online terpercaya"
  }
}
```

**Response (400 Bad Request):**
```json
{
  "success": false,
  "error": "Invalid organization code format",
  "code": "INVALID_ORG_CODE_FORMAT"
}
```

**Response (404 Not Found):**
```json
{
  "success": false,
  "error": "Organization not found",
  "code": "ORG_NOT_FOUND"
}
```

**Response (409 Conflict):**
```json
{
  "success": false,
  "error": "You already belong to an organization",
  "code": "USER_ALREADY_IN_ORG"
}
```

**Response (403 Forbidden):**
```json
{
  "success": false,
  "error": "You cannot join this organization",
  "code": "PERMISSION_DENIED"
}
```

**Response (401 Unauthorized):**
```json
{
  "success": false,
  "error": "Authentication required",
  "code": "UNAUTHORIZED"
}
```

**Business Logic:**
1. Verify user is authenticated
2. Check user doesn't already have organizationCode:
   - If user.organizationCode exists → return 409 CONFLICT
3. Validate organizationCode format (uppercase, alphanumeric)
4. Query organizations table for matching organizationCode
   - If not found → return 404 NOT FOUND
   - If found → continue
5. Check if organization accepts new members (not in maintenance):
   - If maintenanceMode = true → return 403 FORBIDDEN
6. Update user record:
   - Set user.organizationCode = provided code
   - Set user.role = "USER" or "MEMBER" (in the organization)
7. Create audit log entry:
   - action: "USER_JOINED_ORGANIZATION"
   - userId: authenticated user ID
   - organizationCode: organization code
   - timestamp: current
8. Optionally notify organization admins:
   - Email notification (if emailNotifications enabled)
   - In-app notification
9. Return success response with organization details

**Permissions Required:**
- Must be authenticated (any authenticated user can join)
- Organization must allow joining (not in maintenance mode)

**Database Changes Required:**
- Update `users` table: set organizationCode where id = current_user_id
- Insert into audit log
- Optionally: insert into notifications table

---

## Database Schema Changes

### Update Users Table
```sql
-- If organizationCode column doesn't exist, add it
ALTER TABLE users ADD COLUMN organization_code VARCHAR(50) DEFAULT NULL;
ALTER TABLE users ADD CONSTRAINT fk_users_org FOREIGN KEY (organization_code) REFERENCES organizations(code);
ALTER TABLE users ADD INDEX idx_org_code (organization_code);
```

### Organizations Table (Verify Fields)
```sql
-- Ensure these fields exist
CREATE TABLE organizations (
  code VARCHAR(50) PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  timezone VARCHAR(50) DEFAULT 'Asia/Jakarta',
  currency VARCHAR(3) DEFAULT 'IDR',
  language VARCHAR(2) DEFAULT 'id',
  email_notifications BOOLEAN DEFAULT TRUE,
  auction_notifications BOOLEAN DEFAULT TRUE,
  bid_notifications BOOLEAN DEFAULT TRUE,
  two_factor_auth BOOLEAN DEFAULT FALSE,
  maintenance_mode BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_by VARCHAR(36) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_name (name),
  INDEX idx_created_by (created_by)
);
```

### Audit Log Table (Optional but Recommended)
```sql
CREATE TABLE audit_logs (
  id VARCHAR(36) PRIMARY KEY,
  user_id VARCHAR(36) NOT NULL,
  action VARCHAR(100) NOT NULL,
  resource_type VARCHAR(50),
  resource_id VARCHAR(100),
  organization_code VARCHAR(50),
  old_value JSON,
  new_value JSON,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (organization_code) REFERENCES organizations(code),
  INDEX idx_user_id (user_id),
  INDEX idx_action (action),
  INDEX idx_created_at (created_at),
  INDEX idx_org_code (organization_code)
);
```

---

## Error Codes

```
INVALID_INPUT - Request validation failed
ORG_NAME_EXISTS - Organization name already taken
INVALID_ORG_CODE_FORMAT - Organization code format invalid
ORG_NOT_FOUND - Organization code doesn't exist
USER_ALREADY_IN_ORG - User already belongs to an organization
PERMISSION_DENIED - User lacks permission to join
UNAUTHORIZED - User not authenticated
ORG_MAINTENANCE - Organization in maintenance mode
```

---

## Integration with Frontend

The frontend expects these endpoints to be available:

**OrganizationSetupPage** will call:
- `POST /api/v1/organization/create` when user clicks "Create Organization"
- `POST /api/v1/organization/join` when user clicks "Join Organization"

**Expected Frontend Flow:**
1. User logs in → AuthContext detects `!organizationCode`
2. Redirects to `/auth/organization-setup`
3. User chooses Create or Join:
   - **Create**: Enters name + description → calls create endpoint → redirects to `/admin`
   - **Join**: Enters organization code → calls join endpoint → redirects to `/admin`
4. After successful setup, `organizationCode` is populated
5. On next login, user goes directly to `/admin` (no setup redirect)

**Frontend Error Handling:**
- 400 Bad Request → Show validation error message
- 404 Not Found → "Organization not found"
- 409 Conflict → "Organization name taken" or "You already have an organization"
- 403 Forbidden → "Cannot join this organization"
- 401 Unauthorized → Redirect to login

---

## Testing Checklist

### Create Organization Endpoint
- [ ] Create organization with valid name + description
- [ ] Create organization with name only (description optional)
- [ ] Reject name shorter than 3 characters
- [ ] Reject name longer than 100 characters
- [ ] Reject duplicate organization name
- [ ] Verify organizationCode is unique
- [ ] Verify organizationCode format (ORG-XXXXX-001)
- [ ] Verify default settings are applied
- [ ] Verify user.organizationCode is updated
- [ ] Verify audit log entry created
- [ ] Reject unauthenticated requests
- [ ] Verify response includes createdAt and createdBy

### Join Organization Endpoint
- [ ] Join with valid organization code
- [ ] Reject invalid organization code format
- [ ] Reject non-existent organization code (404)
- [ ] Reject if user already has organization (409)
- [ ] Reject if organization in maintenance mode (403)
- [ ] Verify user.organizationCode is updated
- [ ] Verify audit log entry created
- [ ] Reject unauthenticated requests
- [ ] Verify response includes organization details
- [ ] Test case-insensitive code handling

### Data Consistency
- [ ] After create, user can view organization settings
- [ ] After create, user appears in organization members
- [ ] After join, user can view organization settings
- [ ] After join, user appears in organization members
- [ ] Token refresh maintains organizationCode
- [ ] Organization code persists across sessions

---

## Response Time Requirements

- Create Organization: < 500ms
- Join Organization: < 300ms
- Both endpoints should complete within API timeout window

---

## Security Considerations

1. **Rate Limiting**: Implement rate limit on create endpoint (5 orgs per user per day)
2. **Input Sanitization**: Clean organizationName and description
3. **SQL Injection**: Use parameterized queries
4. **Authorization**: Always verify authenticated user
5. **Audit Trail**: Log all creation and join attempts
6. **Data Validation**: Enforce all field constraints
7. **Conflict Handling**: Gracefully handle duplicate names

---

## Future Enhancements

- [ ] Organization approval workflow (admin must approve new members)
- [ ] Organization invitation links
- [ ] Sub-organizations/departments
- [ ] Organization member quotas/limits
- [ ] Organization tier system (free, pro, enterprise)
- [ ] Organization custom branding
- [ ] Organization API keys
- [ ] Organization webhook endpoints

---

## Related APIs

- [API_01_AUTHENTICATION.md](API_01_AUTHENTICATION.md) - User authentication
- [API_02_ORGANIZATION_SETTINGS.md](API_02_ORGANIZATION_SETTINGS.md) - Organization configuration
- [API_03_STAFF_USERS.md](API_03_STAFF_USERS.md) - User management (when ready)
