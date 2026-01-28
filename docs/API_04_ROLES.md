# Roles & Permissions API Documentation

**Version:** 1.0.0  
**Priority:** Phase 2 - Access Control  
**Organization Code:** ORG-DERALY-001  
**Depends on:** API_01_AUTHENTICATION.md, API_03_ORGANIZATION_SETUP.md, API_03_STAFF_USERS.md  
**Prerequisite:** User must belong to organization and be staff member

---

## Overview

Manages role-based access control (RBAC) system. Defines roles, permissions, and role assignments for staff members.

**Base URL:** `/api/v1/roles`

---

## Data Model

```typescript
interface Role {
  id: string;
  name: string;
  description: string;
  organizationCode: string;
  permissions: string[];
  isActive: boolean;
  createdAt: Date;
  updatedAt: Date;
}

interface Permission {
  id: string;
  name: string;
  description: string;
  resource: string;
  action: string;
}

interface RoleAssignment {
  staffId: string;
  roleId: string;
  assignedAt: Date;
  assignedBy: string;
}
```

---

## Endpoints

### 1. Get All Roles
**Endpoint:** `GET /api/v1/roles`

**Query Parameters:**
| Parameter | Type |
|-----------|------|
| isActive | boolean |
| page | number |
| limit | number |

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "1",
      "name": "Admin",
      "description": "Full system access",
      "organizationCode": "ORG-DERALY-001",
      "permissions": [
        "manage_auctions",
        "manage_staff",
        "manage_roles",
        "view_analytics",
        "manage_organization"
      ],
      "isActive": true,
      "createdAt": "2026-01-01T10:00:00Z",
      "updatedAt": "2026-01-01T10:00:00Z"
    },
    {
      "id": "2",
      "name": "Moderator",
      "description": "Auction management and moderation",
      "organizationCode": "ORG-DERALY-001",
      "permissions": [
        "manage_auctions",
        "view_bids",
        "view_analytics"
      ],
      "isActive": true,
      "createdAt": "2026-01-01T10:00:00Z",
      "updatedAt": "2026-01-01T10:00:00Z"
    }
  ],
  "pagination": {
    "total": 2,
    "page": 1,
    "limit": 10
  }
}
```

**Permissions Required:**
- `manage_roles`

---

### 2. Get Role by ID
**Endpoint:** `GET /api/v1/roles/:id`

**Response:** Single Role object with full permission list

---

### 3. Create Role
**Endpoint:** `POST /api/v1/roles`

**Request Body:**
```json
{
  "name": "Analyst",
  "description": "Data analysis and reporting",
  "permissions": [
    "view_analytics",
    "view_bids",
    "view_auctions"
  ]
}
```

**Response (201 Created):** New Role object

**Permissions Required:**
- `manage_roles`

**Validation:**
- Name is unique per organization
- Permissions must exist
- Maximum 50 characters for name

---

### 4. Update Role
**Endpoint:** `PUT /api/v1/roles/:id`

**Request Body:**
```json
{
  "description": "Updated description",
  "permissions": [
    "view_analytics",
    "view_bids",
    "manage_auctions"
  ],
  "isActive": true
}
```

**Response (200 OK):** Updated Role object

**Permissions Required:**
- `manage_roles`

**Business Rules:**
- Cannot change name of existing role
- Cannot remove "Admin" role
- Deactivating role removes access for all assigned staff

---

### 5. Delete Role
**Endpoint:** `DELETE /api/v1/roles/:id`

**Response (204 No Content)**

**Permissions Required:**
- `manage_roles`

**Constraints:**
- Cannot delete "Admin" or "Moderator" built-in roles
- Cannot delete role with active staff assignments
- Must reassign staff to other roles first

---

### 6. Get All Permissions
**Endpoint:** `GET /api/v1/roles/permissions/all`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "p-1",
      "name": "manage_auctions",
      "description": "Create, edit, delete auctions",
      "resource": "auctions",
      "action": "write"
    },
    {
      "id": "p-2",
      "name": "view_auctions",
      "description": "View auction details",
      "resource": "auctions",
      "action": "read"
    }
  ]
}
```

---

### 7. Assign Role to Staff
**Endpoint:** `POST /api/v1/roles/:id/assign`

**Request Body:**
```json
{
  "staffId": "staff-1"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "staffId": "staff-1",
    "roleId": "1",
    "roleName": "Admin",
    "assignedAt": "2026-01-18T10:30:00Z",
    "assignedBy": "admin-1"
  }
}
```

**Permissions Required:**
- `manage_staff` or `manage_roles`

---

### 8. Remove Role from Staff
**Endpoint:** `DELETE /api/v1/roles/:id/unassign`

**Query Parameters:**
| Parameter | Type |
|-----------|------|
| staffId | string |

**Response (204 No Content)**

**Permissions Required:**
- `manage_staff` or `manage_roles`

---

## Permission List

### Auction Permissions
```
manage_auctions - Create, edit, delete, publish auctions
view_auctions - View all auction details
```

### Bid Permissions
```
view_bids - View bid history and activity
manage_bids - Accept/reject bids
```

### Staff Permissions
```
manage_staff - Create, edit, delete staff members
view_staff - View staff details
```

### Role Permissions
```
manage_roles - Create, edit, delete roles
view_roles - View role assignments
```

### Organization Permissions
```
manage_organization - Edit organization settings
view_settings - View organization settings
```

### Analytics Permissions
```
view_analytics - View statistics and analytics
```

---

## Database Schema

```sql
CREATE TABLE roles (
  id VARCHAR(36) PRIMARY KEY,
  organization_code VARCHAR(50) NOT NULL,
  name VARCHAR(50) NOT NULL,
  description TEXT,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (organization_code) REFERENCES organizations(code),
  UNIQUE KEY unique_role_per_org (organization_code, name),
  INDEX idx_organization_code (organization_code)
);

CREATE TABLE permissions (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(100) UNIQUE,
  description TEXT,
  resource VARCHAR(50),
  action VARCHAR(50)
);

CREATE TABLE role_permissions (
  id VARCHAR(36) PRIMARY KEY,
  role_id VARCHAR(36) NOT NULL,
  permission_id VARCHAR(36) NOT NULL,
  
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (permission_id) REFERENCES permissions(id),
  UNIQUE KEY unique_role_permission (role_id, permission_id)
);

CREATE TABLE staff_roles (
  id VARCHAR(36) PRIMARY KEY,
  staff_id VARCHAR(36) NOT NULL,
  role_id VARCHAR(36) NOT NULL,
  organization_code VARCHAR(50) NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  assigned_by VARCHAR(36),
  
  FOREIGN KEY (staff_id) REFERENCES staff(id),
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (organization_code) REFERENCES organizations(code),
  FOREIGN KEY (assigned_by) REFERENCES staff(id),
  UNIQUE KEY unique_staff_role (staff_id, role_id)
);

CREATE TABLE role_audit_logs (
  id VARCHAR(36) PRIMARY KEY,
  role_id VARCHAR(36),
  staff_id VARCHAR(36),
  action VARCHAR(50),
  changes JSON,
  performed_by VARCHAR(36),
  performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (staff_id) REFERENCES staff(id),
  INDEX idx_performed_at (performed_at)
);
```

---

## Error Codes

```
ROLE_NOT_FOUND - Role doesn't exist
PERMISSION_NOT_FOUND - Permission doesn't exist
ROLE_IN_USE - Cannot delete role with active assignments
BUILTIN_ROLE - Cannot modify built-in roles
PERMISSION_DENIED - Insufficient permissions
DUPLICATE_ROLE - Role name already exists
```

---

## Testing Checklist

- [ ] Get all roles
- [ ] Get role by ID
- [ ] Create new role
- [ ] Update role details
- [ ] Add/remove permissions from role
- [ ] Delete role (with no assignments)
- [ ] Prevent deletion of built-in roles
- [ ] Assign role to staff
- [ ] Remove role from staff
- [ ] Verify permission inheritance
- [ ] Multi-tenant isolation
- [ ] Audit logging on role changes

---

## Next APIs

1. [API_05_AUCTIONS.md](API_05_AUCTIONS.md) - Auctions (Phase 3)
2. [API_06_BID_ACTIVITY.md](API_06_BID_ACTIVITY.md) - Bid Activity (Phase 3)
