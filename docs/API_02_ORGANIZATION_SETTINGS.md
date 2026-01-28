# Organization Settings API Documentation

**Version:** 1.0.0  
**Priority:** Phase 1 - Foundation  
**Organization Code:** ORG-DERALY-001  
**Depends on:** API_01_AUTHENTICATION.md

---

## Overview

Manages organization configuration, settings, and metadata. This API handles all organization-level settings including timezone, language, notifications, and security policies.

**Base URL:** `/api/v1/organization`

---

## Data Model

```typescript
interface OrganizationSettings {
  organizationCode: string;        // Unique identifier
  name: string;                    // Organization name
  email: string;                   // Organization email
  phone: string;                   // Contact phone
  website: string;                 // Website URL
  address: string;                 // Physical address
  city: string;                    // City
  country: string;                 // Country
  logo: string;                    // Logo URL
  description: string;             // Organization description
  timezone: string;                // e.g., Asia/Jakarta
  currency: string;                // e.g., IDR
  language: string;                // e.g., id, en
  emailNotifications: boolean;      // Email notification enabled
  auctionNotifications: boolean;    // Auction alerts enabled
  bidNotifications: boolean;        // Bid alerts enabled
  twoFactorAuth: boolean;           // 2FA required
  maintenanceMode: boolean;         // Is maintenance mode active
}
```

---

## Endpoints

### 1. Get Organization Settings
**Endpoint:** `GET /api/v1/organization/settings`

**Headers:**
```
Authorization: Bearer <TOKEN>
```

**Query Parameters:**
| Parameter | Type | Optional | Description |
|-----------|------|----------|-------------|
| organizationCode | string | Yes | Multi-tenant (defaults to user's org) |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "organizationCode": "ORG-DERALY-001",
    "name": "Deraly Lelang",
    "email": "contact@deraly.id",
    "phone": "+62-812-3456-7890",
    "website": "https://deraly.id",
    "address": "Jl. Merdeka No. 123",
    "city": "Jakarta",
    "country": "Indonesia",
    "logo": "https://storage.example.com/logo.png",
    "description": "Platform lelang online terpercaya",
    "timezone": "Asia/Jakarta",
    "currency": "IDR",
    "language": "id",
    "emailNotifications": true,
    "auctionNotifications": true,
    "bidNotifications": true,
    "twoFactorAuth": false,
    "maintenanceMode": false
  }
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

**Permissions Required:**
- None (any authenticated user can view their org settings)

---

### 2. Update Organization Settings
**Endpoint:** `PUT /api/v1/organization/settings`

**Headers:**
```
Authorization: Bearer <TOKEN>
```

**Request Body:** (Any/all fields optional)
```json
{
  "name": "Deraly Lelang Updated",
  "email": "newemail@deraly.id",
  "phone": "+62-821-1234-5678",
  "website": "https://new.deraly.id",
  "address": "Jl. Baru No. 456",
  "city": "Bandung",
  "country": "Indonesia",
  "description": "Updated description",
  "timezone": "Asia/Makassar",
  "currency": "IDR",
  "language": "en",
  "emailNotifications": false,
  "auctionNotifications": true,
  "bidNotifications": false,
  "twoFactorAuth": true,
  "maintenanceMode": false
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": { /* Updated organization settings */ }
}
```

**Response (400 Bad Request):**
```json
{
  "success": false,
  "error": "Invalid timezone value",
  "code": "INVALID_INPUT",
  "details": {
    "fields": [
      { "field": "timezone", "message": "Must be valid IANA timezone" }
    ]
  }
}
```

**Permissions Required:**
- `manage_settings`

**Validation Rules:**
- Email must be valid format if provided
- Phone must be valid format if provided
- Timezone must be valid IANA timezone (list below)
- Currency must be valid ISO 4217 code
- Language must be valid ISO 639-1 code
- Cannot update organizationCode

**Business Rules:**
- Log all setting changes with user ID
- Notify admins of critical changes (2FA, maintenanceMode)
- Update lastModified timestamp
- Cache invalidation required

---

### 3. Upload Organization Logo
**Endpoint:** `POST /api/v1/organization/logo`

**Headers:**
```
Authorization: Bearer <TOKEN>
Content-Type: multipart/form-data
```

**Form Data:**
| Field | Type | Required | Validation |
|-------|------|----------|-----------|
| logo | file | Yes | Max 5MB, image/* only |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "logoUrl": "https://storage.example.com/org-logo-abc123.png",
    "fileName": "org-logo-abc123.png",
    "uploadedAt": "2026-01-28T10:30:00Z"
  }
}
```

**Response (400 Bad Request):**
```json
{
  "success": false,
  "error": "File too large. Max size: 5MB",
  "code": "FILE_TOO_LARGE"
}
```

**Permissions Required:**
- `manage_settings`

**Business Rules:**
- Store in cloud storage (AWS S3, Minio, etc)
- Generate unique filename to prevent conflicts
- Delete old logo file
- Update organizationSettings.logo field
- Return CDN URL for public access
- Log file upload event

---

### 4. Get Organization Code
**Endpoint:** `GET /api/v1/organization/code`

**Headers:**
```
Authorization: Bearer <TOKEN>
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "organizationCode": "ORG-DERALY-001"
  }
}
```

**Purpose:** Frontend endpoint to get org code for filtering data

**Permissions Required:**
- None (any authenticated user)

---

## Timezone Options

Valid IANA timezone strings:

**Asia Region (Common for Indonesia):**
- Asia/Jakarta (WIB - UTC+7)
- Asia/Makassar (WITA - UTC+8)
- Asia/Jayapura (WIT - UTC+9)
- Asia/Bangkok (UTC+7)
- Asia/Singapore (UTC+8)
- Asia/Kuala_Lumpur (UTC+8)

**Other Common:**
- America/New_York
- America/Los_Angeles
- Europe/London
- Europe/Paris
- Australia/Sydney
- UTC

---

## Currency Options

Valid ISO 4217 currency codes:
- IDR (Indonesia Rupiah)
- USD (US Dollar)
- EUR (Euro)
- GBP (British Pound)
- SGD (Singapore Dollar)
- MYR (Malaysian Ringgit)
- THB (Thai Baht)

---

## Language Options

Valid ISO 639-1 language codes:
- id (Indonesian)
- en (English)
- zh (Chinese)
- ja (Japanese)
- ko (Korean)

---

## Error Codes

```
ORG_NOT_FOUND - Organization doesn't exist
INVALID_INPUT - Invalid field value
FILE_TOO_LARGE - Logo file exceeds 5MB
INVALID_FILE_TYPE - File must be image type
INVALID_TIMEZONE - Timezone not in IANA list
INVALID_CURRENCY - Currency code invalid
INVALID_LANGUAGE - Language code invalid
PERMISSION_DENIED - User lacks manage_settings
```

---

## Database Schema

```sql
CREATE TABLE organizations (
  code VARCHAR(50) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  phone VARCHAR(20),
  website VARCHAR(255),
  address TEXT,
  city VARCHAR(100),
  country VARCHAR(100),
  logo VARCHAR(255),
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
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_name (name),
  INDEX idx_email (email)
);

CREATE TABLE org_settings_history (
  id VARCHAR(36) PRIMARY KEY,
  organization_code VARCHAR(50) NOT NULL,
  changed_by VARCHAR(36) NOT NULL,
  field_name VARCHAR(100),
  old_value TEXT,
  new_value TEXT,
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (organization_code) REFERENCES organizations(code),
  FOREIGN KEY (changed_by) REFERENCES users(id),
  INDEX idx_org_code (organization_code),
  INDEX idx_changed_at (changed_at)
);
```

---

## Testing Checklist

- [ ] Get organization settings
- [ ] Update single setting
- [ ] Update multiple settings
- [ ] Invalid timezone update
- [ ] Invalid currency update
- [ ] Invalid language update
- [ ] Upload logo within size limit
- [ ] Reject logo over 5MB
- [ ] Reject non-image files
- [ ] Get organization code
- [ ] Verify audit log entries
- [ ] Multi-tenant isolation (can't see other org settings)
- [ ] Permission checks for updates
- [ ] Email format validation
- [ ] Phone format validation
- [ ] Timezone list completeness

---

## Related Endpoints

Next APIs to implement:
1. [API_03_STAFF_USERS.md](API_03_STAFF_USERS.md) - User management
2. [API_04_ROLES.md](API_04_ROLES.md) - Role-based access
