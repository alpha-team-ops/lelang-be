# Real-time Bid System - API Reference

**Status:** Current Implementation = Polling-based REST API  
**Last Updated:** Jan 30, 2026

---

## 🎯 Quick Overview

**Bid Update Flow (Frontend):**
```
1. User places bid → POST /api/v1/bids/place
2. Get updated auction → GET /api/v1/auctions/portal/:id
3. Get bid activity → GET /api/v1/bids/activity?auctionId=:id
4. Repeat step 2-3 every 1-2 seconds (polling)
```

---

## 📡 Critical Endpoints for Real-time

### 1. Place Bid
```http
POST /api/v1/bids/place
Authorization: Bearer <PORTAL_TOKEN>
Content-Type: application/json

{
  "auctionId": "uuid",
  "bidAmount": 9000000
}
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": "bid-uuid",
    "auctionId": "auction-uuid",
    "bidAmount": 9000000,
    "status": "CURRENT",
    "timestamp": "2026-01-30T10:30:00Z"
  }
}
```

**Errors:**
```json
{
  "success": false,
  "error": "string",
  "code": "BID_TOO_LOW|AUCTION_NOT_LIVE|CANNOT_BID_OWN_AUCTION"
}
```

---

### 2. Get Auction (For UI Updates)
```http
GET /api/v1/auctions/portal/:auctionId
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "title": "string",
    "description": "string",
    "category": "string",
    "condition": "string",
    "currentBid": 9000000,
    "reservePrice": 8000000,
    "status": "LIVE|ENDING|ENDED",
    "endTime": "2026-01-30T15:00:00Z",
    "participantCount": 12,
    "images": ["url1", "url2"]
  }
}
```

**Key Fields untuk Real-time:**
- `currentBid` - Update setelah bid diterima
- `participantCount` - Jumlah pembeli aktif
- `status` - LIVE/ENDING/ENDED (calculated dari endTime)
- `endTime` - ISO8601 untuk countdown

---

### 3. Get Bid Activity
```http
GET /api/v1/bids/activity?auctionId=:id&limit=20&sort=timestamp
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "bid-uuid",
      "auctionId": "auction-uuid",
      "auctionTitle": "string",
      "bidderName": "string",
      "bidAmount": 9000000,
      "status": "CURRENT|OUTBID|WINNING",
      "timestamp": "2026-01-30T10:30:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "last_page": 3
  }
}
```

**Query Params:**
- `auctionId` - Filter by auction (optional)
- `limit` - Max 100 (default 20)
- `sort` - `timestamp|amount` (default timestamp)

---

### 4. Get Bids for Single Auction
```http
GET /api/v1/bids/auction/:auctionId
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "bid-uuid",
      "auctionId": "auction-uuid",
      "auctionTitle": "string",
      "bidderName": "string",
      "bidAmount": 9000000,
      "status": "CURRENT|OUTBID|WINNING",
      "timestamp": "2026-01-30T10:30:00Z"
    }
  ]
}
```

---

## 🔄 Frontend Polling Strategy

### Recommended Intervals:
```javascript
// Active auction view (user watching)
GET /api/v1/auctions/portal/:id → Every 500ms
GET /api/v1/bids/activity?auctionId=:id → Every 1s

// Auction list view
GET /api/v1/auctions/portal/list → Every 2s

// Background polling
Reduce intervals when tab not focused
Increase when user idle > 30s
```

---

## 📊 Auction Status Logic

**Status Calculated Automatically:**
```
Now < startTime     → DRAFT
startTime ≤ Now ≤ endTime → LIVE
Now > endTime       → ENDED
```

**Bidding Allowed:**
- ✅ LIVE
- ✅ ENDING (last minutes)
- ❌ DRAFT, ENDED, CANCELLED

---

## 🔐 Authentication

**Portal User Token (for bidding):**
```http
POST /api/v1/auth/portal-login
Content-Type: application/json

{
  "corporateId": "12345",
  "name": "John Doe"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "jwt-token",
    "user": {
      "id": "user-uuid",
      "name": "John Doe",
      "organizationCode": "ORG-001"
    }
  }
}
```

**Use token in every request:**
```
Authorization: Bearer <token>
```

---

## ⚠️ Validation Rules (Bid Placement)

```
bidAmount > currentBid
bidAmount >= currentBid + bidIncrement
auctionStatus in ['LIVE', 'ENDING']
bidder !== seller
user.status = 'ACTIVE'
```

**Example:**
```
Current Bid: 8,500,000
Bid Increment: 250,000
Minimum Bid: 8,750,000
```

---

## 📋 Error Codes

```
BID_TOO_LOW
├─ Message: "Bid amount must be at least X"
├─ HTTP: 400

AUCTION_NOT_LIVE
├─ Message: "Cannot bid on non-LIVE auction"
├─ HTTP: 400

CANNOT_BID_OWN_AUCTION
├─ Message: "You cannot bid on your own auction"
├─ HTTP: 400

AUCTION_NOT_FOUND
├─ Message: "Auction not found"
├─ HTTP: 404

UNAUTHORIZED
├─ Message: "Invalid or missing token"
├─ HTTP: 401
```

---

## 🚦 Rate Limiting

```
Bid Placement: 10 bids/minute per user
API Calls: Standard Laravel rate limiting
```

---

## 📌 Data Models (Quick Ref)

**Auction:**
```typescript
{
  id: string (UUID)
  title: string
  description: string
  category: string
  condition: string
  startingPrice: number
  currentBid: number
  reservePrice: number
  bidIncrement: number
  status: 'DRAFT'|'LIVE'|'ENDING'|'ENDED'
  startTime: ISO8601
  endTime: ISO8601
  participantCount: number
  images: string[]
}
```

**Bid:**
```typescript
{
  id: string (UUID)
  auctionId: string
  auctionTitle: string
  bidderName: string
  bidAmount: number
  status: 'CURRENT'|'OUTBID'|'WINNING'
  timestamp: ISO8601
}
```

---

## 🔮 Future: WebSocket Support

**Plan:** Implement real-time with Socket.IO/Laravel Reverb
- **When:** After MVP stable
- **Impact:** Replace polling with event-based updates
- **Backward Compatible:** Yes, REST API remains

**What will change:**
```
Before: Poll every 500ms for updates
After: Instant updates via broadcast events
```

---

## 💡 Frontend Implementation Tips

### For Real-time Auction View:
```javascript
// Pseudocode
const auctionId = params.id
const POLL_INTERVAL = 500 // ms

// 1. Initial load
const auction = await GET(`/auctions/portal/${auctionId}`)
const bids = await GET(`/bids/activity?auctionId=${auctionId}`)

// 2. Setup polling
setInterval(async () => {
  const updated = await GET(`/auctions/portal/${auctionId}`)
  if (updated.currentBid !== auction.currentBid) {
    updateUI(updated)
  }
  const latestBids = await GET(`/bids/activity?auctionId=${auctionId}`)
  if (latestBids.length > bids.length) {
    addNewBidsToUI(latestBids)
  }
}, POLL_INTERVAL)

// 3. On place bid
const result = await POST(`/bids/place`, {
  auctionId,
  bidAmount
})
if (result.success) {
  // Immediately refresh data
  auction = await GET(`/auctions/portal/${auctionId}`)
  bids = await GET(`/bids/activity?auctionId=${auctionId}`)
  updateUI()
}
```

---

## 📞 Support Reference

**Auction Controller:** `app/Http/Controllers/Api/V1/AuctionController.php`  
**Bid Controller:** `app/Http/Controllers/Api/V1/BidController.php`  
**Models:** `app/Models/Auction.php`, `app/Models/Bid.php`
