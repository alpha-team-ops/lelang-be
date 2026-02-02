# API Quick Reference - Auction Portal

**For:** BE & FE Developers | **Updated:** Jan 30, 2026

---

## 🚀 API Endpoints Summary

### Authentication
```
POST /api/v1/auth/portal-login          │ Register/Login user
GET  /api/v1/auth/verify                │ Verify token validity
POST /api/v1/auth/logout                │ Logout (optional)
```

### Auctions (Public)
```
GET  /api/v1/auctions/portal/list       │ All LIVE auctions
GET  /api/v1/auctions/portal/:id        │ Auction details
GET  /api/v1/auctions/search            │ Search auctions
GET  /api/v1/auctions/category/:cat     │ Filter by category
POST /api/v1/auctions/:id/view          │ Record view analytics
```

### Bids (Activity Feed)
```
GET  /api/v1/bids/activity              │ All bids (paginated)
GET  /api/v1/bids/auction/:id           │ Bids for specific auction
GET  /api/v1/bids/user/:userId          │ User's bid history
POST /api/v1/bids/place                 │ Place new bid ⭐ (auth required)
```

---

## ⭐ Most Used Endpoints

### 1. Login Portal User
```bash
POST /api/v1/auth/portal-login
{
  "corporateId": "12345",
  "name": "John Doe"
}
→ Get token, save to localStorage
```

### 2. Get Active Auctions
```bash
GET /api/v1/auctions/portal/list
→ Display auction cards with current bid, end time
```

### 3. Watch Single Auction (Polling)
```bash
GET /api/v1/auctions/portal/:id
→ Every 500ms for real-time updates
→ Watch: currentBid, participantCount, status
```

### 4. Place Bid
```bash
POST /api/v1/bids/place
Authorization: Bearer <token>
{
  "auctionId": "uuid",
  "bidAmount": 9000000
}
→ Success: Show confirmation
→ Error: Show error message
```

### 5. Show Bid History
```bash
GET /api/v1/bids/activity?auctionId=:id
→ Every 1s for live bid feed
→ Show: bidder name, amount, time, status
```

---

## 🔑 Header Template

```http
Authorization: Bearer <PORTAL_TOKEN>
Content-Type: application/json
Accept: application/json
```

---

## 📊 Key Response Fields

### Auction Object
```json
{
  "id": "uuid",
  "title": "...",
  "currentBid": 9000000,      ← Update every 500ms
  "reservePrice": 8000000,
  "status": "LIVE",           ← Changes: LIVE→ENDING→ENDED
  "endTime": "2026-01-30T15:00:00Z",  ← For countdown
  "participantCount": 12,     ← Live participant count
  "images": ["url1", "url2"]
}
```

### Bid Object
```json
{
  "id": "uuid",
  "auctionId": "uuid",
  "bidderName": "John Doe",
  "bidAmount": 9000000,
  "status": "CURRENT",        ← CURRENT|OUTBID
  "timestamp": "2026-01-30T10:30:00Z"
}
```

---

## ⏱️ Polling Recommended

```
Auction Details (while watching): 500ms
Bid Activity (while watching):    1000ms
Auction List (background):        2000ms
After placing bid (refresh):      100ms

Reduce when tab hidden
Increase when idle > 30s
```

---

## ✅ Validation

**Bid Amount Rules:**
```
Min: currentBid + bidIncrement
Example: 8,500,000 + 250,000 = 8,750,000 minimum
```

**Auction Status for Bidding:**
```
✅ LIVE
✅ ENDING
❌ DRAFT, ENDED, CANCELLED
```

---

## 🔴 Common Errors

| Code | Meaning | Fix |
|------|---------|-----|
| `BID_TOO_LOW` | Bid < min required | Show min amount |
| `AUCTION_NOT_LIVE` | Can't bid now | Disable bid button |
| `CANNOT_BID_OWN_AUCTION` | You're seller | Hide bid button |
| `UNAUTHORIZED` | Invalid token | Redirect to login |
| `AUCTION_NOT_FOUND` | Wrong ID | Show 404 |

---

## 🎯 Typical Flow

```
1. User lands → GET /auctions/portal/list
2. Click auction → GET /auctions/portal/:id
3. Start polling every 500ms → GET /auctions/portal/:id
4. Click bid → POST /bids/place (auth required)
5. Refresh → GET /bids/activity?auctionId=:id
6. Leave → Stop polling
```

---

## 💾 Data to Cache

```javascript
// Cache for 30s:
- Auction list

// Cache for 10s:
- Auction details (but poll for updates)

// Don't cache:
- Current bid
- Bid history
- User data
```

---

## 🔐 Auth Token Management

```javascript
// After login
localStorage.setItem('portal_token', response.data.token)

// In every API call
headers: {
  'Authorization': `Bearer ${localStorage.getItem('portal_token')}`
}

// On 401 response
localStorage.removeItem('portal_token')
// Redirect to login
```

---

## 📱 Mobile Considerations

```
Polling Interval:
- WiFi: 500ms (auction details)
- 4G: 1000ms
- 3G: 2000ms

Reduce requests:
- Combine GET calls when possible
- Batch updates from polling
- Lower frequency when battery low
```

---

## 🧪 Quick Test Commands

```bash
# Login
curl -X POST http://localhost:8000/api/v1/auth/portal-login \
  -H "Content-Type: application/json" \
  -d '{"corporateId":"123","name":"Test"}'

# Get auctions
curl http://localhost:8000/api/v1/auctions/portal/list

# Place bid (replace token & IDs)
curl -X POST http://localhost:8000/api/v1/bids/place \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"auctionId":"UUID","bidAmount":9000000}'
```

---

## 📞 Reference Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/V1/AuctionController.php` | Auction logic |
| `app/Http/Controllers/Api/V1/BidController.php` | Bid logic |
| `app/Models/Auction.php` | Auction model |
| `app/Models/Bid.php` | Bid model |
| `routes/api.php` | Route definitions |

---

## ⚡ Performance Notes

- **Database:** Indexed on auction_id, bidder_id
- **Pagination:** Default 20, max 100
- **Rate Limit:** 10 bids/minute per user
- **Response Time:** <200ms for most endpoints
- **Concurrent Bids:** Handled via DB transactions

---

**Last Updated:** January 30, 2026  
**Status:** Active & Stable  
**Next:** WebSocket implementation planned (non-breaking)
