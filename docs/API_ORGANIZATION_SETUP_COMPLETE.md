# Organization Setup API - Complete Reference

## Overview

The Organization Setup API provides three endpoints for managing organization creation and membership in the Lelang application. Users can create new organizations or join existing ones during the onboarding flow.

## Endpoints

### 1. Create Organization
**POST** `/api/v1/organization/create`

Create a new organization as the founder/admin.

#### Request Headers
```
Authorization: Bearer {accessToken}
Content-Type: application/json
```

#### Request Body
```json
{
  "organizationName": "string (required, 3-100 chars)",
  "description": "string (optional, max 500 chars)"
}
```

#### Success Response (201)
```json
{
  "success": true,
  "data": {
    "organizationCode": "ORG-COMPANYNAME-001",
    "name": "Company Name",
    "description": "Optional description",
    "createdAt": "2026-01-28T06:41:19+00:00",
    "createdBy": "user-uuid",
    "accessToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refreshToken": "qJ6rW4hXNgYSGtM5GdJSBPkf02b5VN1H4rxfbIKdboiNyfZKB1U7ljZbEvrY3Sgo",
    "expiresIn": 3600,
    "tokenType": "Bearer"
  },
  "message": "Organization created successfully"
}
```

#### Error Responses

**409 - User Already in Organization**
```json
{
  "success": false,
  "error": "You already belong to an organization. Cannot create another organization.",
  "code": "USER_ALREADY_IN_ORG"
}
```

**409 - Organization Name Exists**
```json
{
  "success": false,
  "error": "Organization name already exists",
  "code": "ORG_NAME_EXISTS"
}
```

**400 - Validation Error**
```json
{
  "success": false,
  "error": "Organization name must be at least 3 characters",
  "code": "VALIDATION_ERROR"
}
```

---

### 2. Join Organization
**POST** `/api/v1/organization/join`

Join an existing organization using its organization code.

#### Request Headers
```
Authorization: Bearer {accessToken}
Content-Type: application/json
```

#### Request Body
```json
{
  "organizationCode": "string (required, format: ORG-XXXXXXXX-NNN)"
}
```

#### Success Response (200)
```json
{
  "success": true,
  "data": {
    "organizationCode": "ORG-DERALY-001",
    "name": "Startup Onboarding Demo",
    "description": "Demo startup created via onboarding flow",
    "accessToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refreshToken": "QPzZ3IzuG6KbxLgda91nSR5yqIobOf7RfENiLD1KAsbp9qgB9L5sRZPmcwEskmFq",
    "expiresIn": 3600,
    "tokenType": "Bearer"
  },
  "message": "Successfully joined organization"
}
```

#### Error Responses

**409 - User Already in Organization**
```json
{
  "success": false,
  "error": "You already belong to an organization",
  "code": "USER_ALREADY_IN_ORG"
}
```

**404 - Organization Not Found**
```json
{
  "success": false,
  "error": "Organization not found",
  "code": "ORG_NOT_FOUND"
}
```

**403 - Organization in Maintenance**
```json
{
  "success": false,
  "error": "Cannot join this organization at the moment",
  "code": "ORG_MAINTENANCE"
}
```

**400 - Invalid Organization Code Format**
```json
{
  "success": false,
  "error": "Organization code format is invalid (e.g., ORG-DERALY-001)",
  "code": "VALIDATION_ERROR"
}
```

---

### 3. Check Setup Status
**GET** `/api/v1/organization/check-setup`

Check if the current user has completed organization setup. Used by frontend route guards.

#### Request Headers
```
Authorization: Bearer {accessToken}
```

#### Success Response (200)

**User Needs Setup**
```json
{
  "success": true,
  "data": {
    "needsSetup": true,
    "organizationCode": null,
    "role": "MEMBER"
  },
  "message": "User needs to complete organization setup"
}
```

**User Completed Setup**
```json
{
  "success": true,
  "data": {
    "needsSetup": false,
    "organizationCode": "ORG-TESTORGS-001",
    "role": "ADMIN"
  },
  "message": "User has completed organization setup"
}
```

---

## Key Features

### JWT Token Refresh
- When users create or join an organization, **new JWT tokens** are returned in the response
- The new `accessToken` contains updated:
  - `organizationCode`: The user's new organization
  - `role`: ADMIN for creators, MEMBER for joiners
  - `permissions`: Role-based permissions array
- Frontend should use the returned `accessToken` immediately
- **No re-login required** after org operations

### Organization Code Format
- Format: `ORG-{FIRST 8 CHARS OF NAME}-{SEQUENCE}`
- Examples:
  - `ORG-COMPANYNAME-001`
  - `ORG-STARTUP-002`
  - `ORG-DERALY-001`
- Ensures uniqueness across all organizations

### Default Organization Settings
When creating an organization, these defaults are applied:
- **Timezone**: Asia/Jakarta
- **Currency**: IDR
- **Language**: Indonesian (id)
- **Email Notifications**: Enabled
- **Auction Notifications**: Enabled
- **Bid Notifications**: Enabled
- **Two-Factor Auth**: Disabled
- **Maintenance Mode**: Disabled
- **Status**: Active

All settings can be updated later via the Organization Settings API.

### User Roles After Operations
- **Organization Creator**: Role = `ADMIN`
  - Permissions: manage_users, manage_auctions, view_analytics, manage_settings
- **Organization Joiner**: Role = `MEMBER`
  - Permissions: Limited to user's own actions

---

## Complete Onboarding Flow

### Step-by-Step

```
1. User Registration
   POST /api/v1/auth/register
   - organizationCode: null (no org yet)
   - role: MEMBER

2. User Login
   POST /api/v1/auth/login
   - Returns accessToken with organizationCode: null
   
3. Frontend Check Setup Status (Route Guard)
   GET /api/v1/organization/check-setup
   - needsSetup: true
   - Redirect to setup page

4. CREATE Path - Founder Creates Organization
   POST /api/v1/organization/create
   - Returns NEW accessToken with:
     - organizationCode: "ORG-COMPANYNAME-001"
     - role: "ADMIN"
     - permissions: [admin permissions]
   - Ready to use immediately!

   OR

4. JOIN Path - User Joins Existing Organization
   POST /api/v1/organization/join
   - User provides organizationCode
   - Returns NEW accessToken with:
     - organizationCode: "ORG-EXISTING-001"
     - role: "MEMBER"
     - permissions: []
   - Ready to use immediately!

5. Verify Setup Complete
   GET /api/v1/organization/check-setup
   - needsSetup: false
   - Redirect to dashboard
```

---

## Frontend Implementation Example

### React/JavaScript
```javascript
// Step 1: Check if setup needed
const setupStatus = await api.get('/organization/check-setup');

if (setupStatus.data.needsSetup) {
  // Show setup form
  showSetupPage();
} else {
  // Setup complete, go to dashboard
  router.push('/dashboard');
}

// Step 2: Create organization
const createResponse = await api.post('/organization/create', {
  organizationName: 'My Company',
  description: 'My awesome company'
});

// Update local token with response
const newToken = createResponse.data.accessToken;
localStorage.setItem('accessToken', newToken);
api.defaults.headers.authorization = `Bearer ${newToken}`;

// Continue with app - no re-login needed!
router.push('/dashboard');

// Step 3: Or join existing organization
const joinResponse = await api.post('/organization/join', {
  organizationCode: 'ORG-EXAMPLE-001'
});

// Update token
const newToken = joinResponse.data.accessToken;
localStorage.setItem('accessToken', newToken);
api.defaults.headers.authorization = `Bearer ${newToken}`;

// Go to dashboard
router.push('/dashboard');
```

### Error Handling
```javascript
try {
  await api.post('/organization/create', data);
} catch (error) {
  const code = error.response.data.code;
  
  if (code === 'USER_ALREADY_IN_ORG') {
    showError('You already belong to an organization');
  } else if (code === 'ORG_NAME_EXISTS') {
    showError('Organization name already taken');
  } else if (code === 'VALIDATION_ERROR') {
    showError(error.response.data.error);
  }
}
```

---

## Testing Checklist

- [ ] Can register user without organizationCode
- [ ] Can login and verify token has organizationCode: null
- [ ] Check-setup endpoint returns needsSetup: true
- [ ] Create organization returns new accessToken with organizationCode
- [ ] JWT token payload shows updated organizationCode
- [ ] Cannot create second organization (409 error)
- [ ] Can join existing organization with valid code
- [ ] JWT token after join shows new organizationCode
- [ ] Cannot join organization twice (409 error)
- [ ] Check-setup endpoint returns needsSetup: false after setup
- [ ] Invalid org code format returns error
- [ ] Non-existent org code returns 404

---

## Security Considerations

1. **Token Expiry**: Access tokens expire in 3600 seconds (1 hour)
2. **Refresh Tokens**: Should be stored securely (HttpOnly cookies recommended)
3. **Organization Isolation**: Each user belongs to exactly one organization
4. **Role-Based Access**: Permissions determined by role + organizationCode
5. **Audit Logging**: All organization operations logged to org_settings_history

---

## Related Endpoints

- `POST /api/v1/auth/register` - User registration
- `POST /api/v1/auth/login` - User login
- `POST /api/v1/auth/refresh` - Token refresh
- `GET /api/v1/organization/settings` - Get org settings
- `PUT /api/v1/organization/settings` - Update org settings
