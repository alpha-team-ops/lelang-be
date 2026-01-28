# Auctions API Documentation

**Version:** 1.0.0  
**Priority:** Phase 3 - Core Business  
**Organization Code:** ORG-DERALY-001  
**Depends on:** API_01_AUTHENTICATION.md, API_03_ORGANIZATION_SETUP.md  
**Prerequisite:** User must belong to organization with appropriate permissions

---

## Overview

Core auction management API. Handles creation, editing, deletion, and status management of auctions. Supports both admin view (full details) and portal view (public-facing).

**Base URL:** `/api/v1/auctions`

---

## Data Models

### Auction (Admin View - Full)
```typescript
interface Auction {
  id: string;
  title: string;
  description: string;
  category: string;
  condition: string;
  serialNumber?: string;
  itemLocation?: string;
  purchaseYear?: number;
  startingPrice: number;
  reservePrice: number;
  bidIncrement: number;
  currentBid: number;
  totalBids: number;
  status: 'DRAFT' | 'SCHEDULED' | 'LIVE' | 'ENDING' | 'ENDED' | 'CANCELLED';
  startTime: Date;
  endTime: Date;
  seller: string;
  currentBidder?: string;
  image?: string;
  images?: string[];
  viewCount: number;
  participantCount: number;
  organizationCode: string;
}
```

### PortalAuction (Public View - Limited)
```typescript
interface PortalAuction {
  id: string;
  title: string;
  description: string;
  category: string;
  condition: string;
  currentBid: number;
  reservePrice: number;
  endTime: Date;
  participantCount: number;
  images?: string[];
  organizationCode: string;
}
```

---

## Endpoints

### Admin Auctions

#### 1. Get All Admin Auctions
**Endpoint:** `GET /api/v1/auctions`

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| status | string | DRAFT, SCHEDULED, LIVE, ENDING, ENDED |
| category | string | Filter by category |
| page | number | Page number |
| limit | number | Items per page |
| sort | string | Field: createdDate, currentBid, endTime |
| order | string | asc or desc |

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "1",
      "title": "Laptop ASUS ROG Gaming",
      "description": "...",
      "category": "Elektronik",
      "condition": "Bekas - Sangat Baik",
      "serialNumber": "ASU-ROG-2024-001",
      "itemLocation": "Jakarta",
      "purchaseYear": 2023,
      "startingPrice": 7500000,
      "reservePrice": 8500000,
      "bidIncrement": 250000,
      "currentBid": 8500000,
      "totalBids": 12,
      "status": "LIVE",
      "startTime": "2026-01-20T10:00:00Z",
      "endTime": "2026-01-25T18:00:00Z",
      "seller": "Admin",
      "currentBidder": "Pembeli_123",
      "image": "💻",
      "images": ["https://..."],
      "viewCount": 342,
      "participantCount": 12,
      "organizationCode": "ORG-DERALY-001"
    }
  ],
  "pagination": {
    "total": 48,
    "page": 1,
    "limit": 10,
    "totalPages": 5
  }
}
```

**Permissions Required:**
- `manage_auctions`

---

#### 2. Get Auction by ID
**Endpoint:** `GET /api/v1/auctions/:id`

**Response:** Full Auction object (admin view)

---

#### 3. Create Auction
**Endpoint:** `POST /api/v1/auctions`

**Request Body:**
```json
{
  "title": "New Item",
  "description": "Description...",
  "category": "Elektronik",
  "condition": "Bekas - Sangat Baik",
  "serialNumber": "SN-2024-001",
  "itemLocation": "Jakarta",
  "purchaseYear": 2024,
  "startingPrice": 5000000,
  "reservePrice": 6000000,
  "bidIncrement": 200000,
  "startTime": "2026-02-01T10:00:00Z",
  "endTime": "2026-02-05T18:00:00Z",
  "images": ["https://..."]
}
```

**Response (201 Created):** Full Auction object

**Permissions Required:**
- `manage_auctions`

**Validation:**
- startingPrice < reservePrice
- startTime < endTime
- All required fields present

**Auto-generated:**
- `id` (UUID)
- `totalBids` = 0
- `currentBid` = startingPrice
- `viewCount` = 0
- `status` = DRAFT
- `organizationCode` = from token

---

#### 4. Update Auction
**Endpoint:** `PUT /api/v1/auctions/:id`

**Request Body:** Partial Auction

**Response (200 OK):** Updated Auction

**Permissions Required:**
- `manage_auctions`

**Restrictions:**
- Cannot update LIVE auctions (except status)
- Cannot update ENDED auctions
- Cannot change organizationCode

---

#### 5. Delete Auction
**Endpoint:** `DELETE /api/v1/auctions/:id`

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Auction deleted successfully"
}
```

**Permissions Required:**
- `manage_auctions`

**Business Rules:**
- Can only delete DRAFT auctions
- Cannot delete LIVE or ENDED auctions

---

### Portal Auctions (Public)

#### 6. Get All Portal Auctions (LIVE)
**Endpoint:** `GET /api/v1/auctions/portal/list`

**Description:** Only returns LIVE auctions

**Query Parameters:**
| Parameter | Type |
|-----------|------|
| category | string |
| page | number |
| limit | number |
| sort | string |

**Response:** List of PortalAuction objects (LIVE only)

**Permissions Required:**
- None (public endpoint)

---

#### 7. Get Portal Auction by ID
**Endpoint:** `GET /api/v1/auctions/portal/:id`

**Response:** Single PortalAuction (if status = LIVE)

**Permissions Required:**
- None (public endpoint)

---

#### 8. Search Auctions
**Endpoint:** `GET /api/v1/auctions/search`

**Query Parameters:**
| Parameter | Type |
|-----------|------|
| query | string |
| category | string |

**Response:** Matching auction list (LIVE only)

**Permissions Required:**
- None (public endpoint)

---

#### 9. Get Auctions by Category
**Endpoint:** `GET /api/v1/auctions/category/:category`

**Response:** Auctions by category (LIVE only)

**Permissions Required:**
- None (public endpoint)

---

#### 10. Get Auctions by Status
**Endpoint:** `GET /api/v1/auctions/status/:status`

**Path Parameters:**
- status: DRAFT | SCHEDULED | LIVE | ENDING | ENDED

**Response:** Filtered auction list

**Permissions Required:**
- `manage_auctions`

---

## Auction Status Workflow

```
DRAFT (Initial)
  ↓ schedule
SCHEDULED
  ↓ startTime reached
LIVE
  ↓ endTime approaching (24h left)
ENDING
  ↓ endTime reached
ENDED
  
DRAFT/SCHEDULED/LIVE
  ↓ manual action
CANCELLED
```

---

## Error Codes

```
AUCTION_NOT_FOUND - Auction doesn't exist
INVALID_STATUS_TRANSITION - Cannot transition to status
CANNOT_UPDATE_LIVE - Cannot update LIVE auction
CANNOT_DELETE_LIVE - Cannot delete LIVE auction
INVALID_PRICE - startingPrice >= reservePrice
INVALID_TIME - startTime >= endTime
DUPLICATE_SERIAL - Serial number already exists
PERMISSION_DENIED - Insufficient permissions
```

---

## Database Schema

```sql
CREATE TABLE auctions (
  id VARCHAR(36) PRIMARY KEY,
  organization_code VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  category VARCHAR(100),
  condition VARCHAR(100),
  serial_number VARCHAR(100),
  item_location VARCHAR(100),
  purchase_year INT,
  starting_price DECIMAL(15,2) NOT NULL,
  reserve_price DECIMAL(15,2) NOT NULL,
  bid_increment DECIMAL(15,2) NOT NULL,
  current_bid DECIMAL(15,2),
  total_bids INT DEFAULT 0,
  status ENUM('DRAFT','SCHEDULED','LIVE','ENDING','ENDED','CANCELLED') DEFAULT 'DRAFT',
  start_time DATETIME NOT NULL,
  end_time DATETIME NOT NULL,
  seller VARCHAR(100),
  current_bidder VARCHAR(100),
  image VARCHAR(255),
  view_count INT DEFAULT 0,
  participant_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (organization_code) REFERENCES organizations(code),
  UNIQUE KEY unique_serial (organization_code, serial_number),
  INDEX idx_org_code (organization_code),
  INDEX idx_status (status),
  INDEX idx_category (category),
  INDEX idx_end_time (end_time)
);

CREATE TABLE auction_images (
  id VARCHAR(36) PRIMARY KEY,
  auction_id VARCHAR(36) NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  order_num INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
  INDEX idx_auction_id (auction_id)
);
```

---

## Testing Checklist

- [ ] Get all auctions with filters
- [ ] Create auction with valid data
- [ ] Reject invalid price range
- [ ] Reject invalid time range
- [ ] Get auction by ID
- [ ] Update DRAFT auction
- [ ] Reject update to LIVE auction
- [ ] Delete DRAFT auction
- [ ] Reject delete LIVE auction
- [ ] Search auctions by query
- [ ] Filter by category
- [ ] Get portal auctions (LIVE only)
- [ ] Status transitions
- [ ] Multi-tenant isolation
- [ ] Serial number uniqueness
- [ ] Permission checks

---

## Next APIs

1. [API_06_BID_ACTIVITY.md](API_06_BID_ACTIVITY.md) - Bidding
