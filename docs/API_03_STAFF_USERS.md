# Staff/Users API Documentation

**Version:** 1.0.0  
**Priority:** Phase 1 - Foundation  
**Organization Code:** ORG-DERALY-001  
**Depends on:** API_01_AUTHENTICATION.md, API_03_ORGANIZATION_SETUP.md  
**Prerequisite:** User must complete organization setup first

---

## Overview

Manages staff/users within an organization. Handles CRUD operations, status management, and activity tracking.

**Base URL:** `/api/v1/staff`

---

## Data Model

```typescript
interface Staff {
  id: string;
  name: string;
  email: string;
  role: 'ADMIN' | 'MODERATOR';
  status: 'ACTIVE' | 'INACTIVE';
  joinDate: string;      // ISO 8601
  lastActivity: string;  // ISO 8601
  organizationCode: string;
}
```

---

## Endpoints

### 1. Get All Staff
**Endpoint:** `GET /api/v1/staff`

**Headers:**
```
Authorization: Bearer <TOKEN>
```

**Query Parameters:**
| Parameter | Type | Optional |
|-----------|------|----------|
| status | string | Yes |
| role | string | Yes |
| search | string | Yes |
| page | number | Yes |
| limit | number | Yes |

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "1",
      "name": "Alpha Dev",
      "email": "alpha.dev@deraly.id",
      "role": "ADMIN",
      "status": "ACTIVE",
      "joinDate": "2024-01-10",
      "lastActivity": "2026-01-28",
      "organizationCode": "ORG-DERALY-001"
    }
  ],
  "pagination": {
    "total": 5,
    "page": 1,
    "limit": 10,
    "totalPages": 1
  }
}
```

**Permissions Required:**
- `manage_users`

---

### 2. Get Staff by ID
**Endpoint:** `GET /api/v1/staff/:id`

**Response:** Single Staff object

---

### 3. Create Staff
**Endpoint:** `POST /api/v1/staff`

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "role": "MODERATOR",
  "password": "SecurePass123!"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": "user-uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "role": "MODERATOR",
    "status": "ACTIVE",
    "joinDate": "2026-01-28",
    "lastActivity": "2026-01-28",
    "organizationCode": "ORG-DERALY-001"
  }
}
```

**Permissions Required:**
- `manage_users`

**Validation:**
- Email unique per organization
- Password meets requirements
- Role valid enum

**Business Rules:**
- Auto-generated ID (UUID)
- Default status: ACTIVE
- joinDate = current date
- Hash password with bcrypt
- Send welcome email

---

### 4. Update Staff
**Endpoint:** `PUT /api/v1/staff/:id`

**Request Body:**
```json
{
  "name": "John Updated",
  "status": "INACTIVE",
  "role": "ADMIN"
}
```

**Response (200 OK):** Updated Staff object

**Permissions Required:**
- `manage_users`

**Restrictions:**
- Cannot change own status to INACTIVE
- Cannot update own role

---

### 5. Delete Staff
**Endpoint:** `DELETE /api/v1/staff/:id`

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Staff deleted successfully"
}
```

**Permissions Required:**
- `manage_users`

**Business Rules:**
- Cannot delete own account
- Cannot delete if assigned to critical roles
- Soft delete (set status to INACTIVE) recommended

---

### 6. Update Last Activity
**Endpoint:** `PUT /api/v1/staff/:id/activity`

**Internal use - called on every user action**

**Request Body:**
```json
{
  "lastActivity": "2026-01-28T10:30:00Z"
}
```

---

## Error Codes

```
DUPLICATE_EMAIL - Email already exists in org
STAFF_NOT_FOUND - Staff member doesn't exist
PERMISSION_DENIED - Insufficient permissions
CANNOT_DELETE_OWN_ACCOUNT - User tried to delete themselves
INVALID_ROLE - Role not in enum
INVALID_STATUS - Status not valid
```

---

## Database Schema

```sql
CREATE TABLE staff (
  id VARCHAR(36) PRIMARY KEY,
  organization_code VARCHAR(50) NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ADMIN', 'MODERATOR') DEFAULT 'MODERATOR',
  status ENUM('ACTIVE', 'INACTIVE') DEFAULT 'ACTIVE',
  join_date DATE DEFAULT CURRENT_DATE,
  last_activity DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (organization_code) REFERENCES organizations(code),
  UNIQUE KEY unique_org_email (organization_code, email),
  INDEX idx_org_code (organization_code),
  INDEX idx_status (status),
  INDEX idx_role (role)
);
```

---

## Testing Checklist

- [ ] Get all staff with pagination
- [ ] Filter by status
- [ ] Filter by role
- [ ] Search by name/email
- [ ] Get staff by ID
- [ ] Create new staff
- [ ] Duplicate email rejection
- [ ] Update staff info
- [ ] Update staff status
- [ ] Delete staff
- [ ] Cannot delete own account
- [ ] Multi-tenant isolation
- [ ] Permission checks

---

## Next APIs

1. [API_04_ROLES.md](API_04_ROLES.md) - Role management
