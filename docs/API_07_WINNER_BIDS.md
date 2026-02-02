# Winner Bids API Documentation

**Version:** 1.0.0  
**Priority:** Phase 4 - Post-Auction  
**Organization Code:** ORG-DERALY-001  
**Depends on:** API_01_AUTHENTICATION.md, API_03_ORGANIZATION_SETUP.md, API_06_BID_ACTIVITY.md

---

## Overview

Manages auction winners and payment tracking. Handles status workflow from payment pending to completed.

**Base URL:** `/api/v1/bids/winners`

---

## Data Model

```typescript
interface WinnerBid {
  id: string;
  auctionId: string;
  auctionTitle: string;
  serialNumber: string;
  category: string;
  fullName: string;
  corporateIdNip: string;
  directorate: string;
  organizationCode: string;
  winningBid: number;
  totalParticipants: number;
  auctionEndTime: Date;
  status: 'PAYMENT_PENDING' | 'PAID' | 'SHIPPED' | 'COMPLETED' | 'CANCELLED';
  paymentDueDate: Date;
  notes?: string;
}
```

---

## Endpoints

### 1. Get All Winner Bids
**Endpoint:** `GET /api/v1/bids/winners`

**Query Parameters:**
| Parameter | Type |
|-----------|------|
| status | string |
| page | number |
| limit | number |

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "wb-1",
      "auctionId": "1",
      "auctionTitle": "Laptop ASUS ROG Gaming",
      "serialNumber": "ASU-ROG-2024-001",
      "category": "Elektronik",
      "fullName": "Ahmad Rizki",
      "corporateIdNip": "CORP-2024-001",
      "directorate": "IT",
      "organizationCode": "ORG-DERALY-001",
      "winningBid": 8500000,
      "totalParticipants": 15,
      "auctionEndTime": "2026-01-18T18:30:00Z",
      "status": "PAYMENT_PENDING",
      "paymentDueDate": "2026-01-20T08:30:00Z",
      "notes": "Waiting for payment"
    }
  ],
  "pagination": {
    "total": 6,
    "page": 1,
    "limit": 10
  }
}
```

**Permissions Required:**
- `manage_auctions`

---

### 2. Get Winner Bid by ID
**Endpoint:** `GET /api/v1/bids/winners/:id`

**Response:** Single WinnerBid object

---

### 3. Get Winner Bids by Status
**Endpoint:** `GET /api/v1/bids/winners/status/:status`

**Path Parameters:**
- status: PAYMENT_PENDING | PAID | SHIPPED | COMPLETED

**Response:** Filtered WinnerBid list

---

### 4. Update Winner Bid Status
**Endpoint:** `PUT /api/v1/bids/winners/:id/status`

**Request Body:**
```json
{
  "status": "PAID",
  "notes": "Payment confirmed via BCA"
}
```

**Response (200 OK):** Updated WinnerBid

**Permissions Required:**
- `manage_auctions`

**Status Workflow:**
```
PAYMENT_PENDING
  ↓ payment received
PAID
  ↓ item shipped
SHIPPED
  ↓ buyer confirms receipt
COMPLETED

Any status
  ↓ cancellation
CANCELLED
```

**Business Rules:**
- Only allow valid transitions
- Update timestamp for status change
- Send notification to winner
- Log status change with user ID

---

### 5. Create Winner Bid (Auto - Internal)
**Endpoint:** `POST /api/v1/bids/winners` (Internal use)

**Auto-triggered:** When auction ends and highest bidder determined

**Logic:**
- Fetch highest bid from bids table
- Get winner staff details
- Get auction details
- Create WinnerBid record
- Set paymentDueDate = now + 48 hours
- Set status = PAYMENT_PENDING
- Send notification to winner

---

## Error Codes

```
WINNER_NOT_FOUND - Winner record doesn't exist
INVALID_STATUS_TRANSITION - Cannot transition to status
PERMISSION_DENIED - Insufficient permissions
AUCTION_NOT_FOUND - Related auction missing
```

---

## Database Schema

```sql
CREATE TABLE winner_bids (
  id VARCHAR(36) PRIMARY KEY,
  auction_id VARCHAR(36) NOT NULL,
  auction_title VARCHAR(255) NOT NULL,
  serial_number VARCHAR(100),
  category VARCHAR(100),
  bidder_id VARCHAR(36) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  corporate_id_nip VARCHAR(50),
  directorate VARCHAR(100),
  organization_code VARCHAR(50) NOT NULL,
  winning_bid DECIMAL(15,2) NOT NULL,
  total_participants INT,
  auction_end_time DATETIME,
  status ENUM('PAYMENT_PENDING','PAID','SHIPPED','COMPLETED','CANCELLED') DEFAULT 'PAYMENT_PENDING',
  payment_due_date DATETIME,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (auction_id) REFERENCES auctions(id),
  FOREIGN KEY (bidder_id) REFERENCES staff(id),
  FOREIGN KEY (organization_code) REFERENCES organizations(code),
  INDEX idx_status (status),
  INDEX idx_auction_id (auction_id),
  INDEX idx_bidder_id (bidder_id)
);

CREATE TABLE winner_status_history (
  id VARCHAR(36) PRIMARY KEY,
  winner_bid_id VARCHAR(36) NOT NULL,
  from_status VARCHAR(50),
  to_status VARCHAR(50),
  changed_by VARCHAR(36),
  notes TEXT,
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (winner_bid_id) REFERENCES winner_bids(id),
  FOREIGN KEY (changed_by) REFERENCES staff(id),
  INDEX idx_winner_bid_id (winner_bid_id)
);
```

---

## Testing Checklist

- [ ] Get all winners
- [ ] Filter by status
- [ ] Get winner by ID
- [ ] Update status PAYMENT_PENDING → PAID
- [ ] Update status PAID → SHIPPED
- [ ] Update status SHIPPED → COMPLETED
- [ ] Reject invalid status transition
- [ ] Create winner auto on auction end
- [ ] Payment due date calculation
- [ ] Notification on status change
- [ ] Status history tracking
- [ ] Multi-tenant isolation

---

## Next APIs

1. [API_08_STATISTICS.md](API_08_STATISTICS.md) - Dashboard Stats
