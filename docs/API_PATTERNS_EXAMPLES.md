# Real-time Patterns & Examples

**For:** FE Implementation | **Version:** 1.0

---

## 📝 Request/Response Patterns

### Pattern 1: Login → Get Token
```
REQUEST:
POST /api/v1/auth/portal-login
Content-Type: application/json

{
  "corporateId": "EMP-123456",
  "name": "John Doe"
}

RESPONSE (200):
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
      "name": "John Doe",
      "organizationCode": "ORG-DERALY-001"
    }
  }
}

→ Save token to localStorage for subsequent requests
```

---

### Pattern 2: Fetch Live Auctions (List)
```
REQUEST:
GET /api/v1/auctions/portal/list

RESPONSE (200):
{
  "success": true,
  "data": [
    {
      "id": "71f3d1d1-3c3a-4629-8ec5-3f53667148fc",
      "title": "Samsung Galaxy S24 Ultra",
      "description": "Smartphone flagship terbaru dengan 5G support",
      "category": "Elektronik",
      "condition": "Baru",
      "currentBid": 12000000,
      "reservePrice": 11000000,
      "status": "LIVE",
      "endTime": "2026-01-31T10:00:00Z",
      "participantCount": 25,
      "images": [
        "https://minio.example.com/auctions/s24-1.jpg",
        "https://minio.example.com/auctions/s24-2.jpg"
      ]
    },
    {
      "id": "81f3d1d1-3c3a-4629-8ec5-3f53667148fd",
      "title": "Laptop ASUS ROG Gaming",
      "description": "High performance gaming laptop",
      "category": "Elektronik",
      "condition": "Sangat Baik",
      "currentBid": 8500000,
      "reservePrice": 7500000,
      "status": "LIVE",
      "endTime": "2026-01-30T15:30:00Z",
      "participantCount": 12,
      "images": ["https://minio.example.com/auctions/asus-rog.jpg"]
    }
  ]
}

→ Display auction cards, update every 2s in background
→ Click to view auction details
```

---

### Pattern 3: Get Auction Details (For Polling)
```
REQUEST:
GET /api/v1/auctions/portal/f47ac10b-58cc-4372-a567-0e02b2c3d479

RESPONSE (200):
{
  "success": true,
  "data": {
    "id": "71f3d1d1-3c3a-4629-8ec5-3f53667148fc",
    "title": "Samsung Galaxy S24 Ultra",
    "description": "Smartphone flagship terbaru dengan 5G support",
    "category": "Elektronik",
    "condition": "Baru",
    "currentBid": 12500000,              ← POLLING: Check if changed
    "reservePrice": 11000000,
    "bidIncrement": 250000,
    "status": "LIVE",
    "startTime": "2026-01-28T10:00:00Z",
    "endTime": "2026-01-31T10:00:00Z",
    "participantCount": 26,              ← POLLING: Real-time count
    "images": [
      "https://minio.example.com/auctions/s24-1.jpg",
      "https://minio.example.com/auctions/s24-2.jpg"
    ]
  }
}

→ Poll this endpoint every 500ms while user is viewing
→ Compare currentBid with previous to detect changes
→ Update UI if values changed
```

---

### Pattern 4: Get Bid Activity (For Bid Feed)
```
REQUEST:
GET /api/v1/bids/activity?auctionId=71f3d1d1-3c3a-4629-8ec5-3f53667148fc&limit=10&sort=timestamp

RESPONSE (200):
{
  "success": true,
  "data": [
    {
      "id": "bid-001",
      "auctionId": "71f3d1d1-3c3a-4629-8ec5-3f53667148fc",
      "auctionTitle": "Samsung Galaxy S24 Ultra",
      "bidderName": "Bambang Irawan",
      "bidAmount": 12500000,
      "status": "CURRENT",                ← Latest highest bid
      "timestamp": "2026-01-30T13:25:10Z"
    },
    {
      "id": "bid-002",
      "auctionId": "71f3d1d1-3c3a-4629-8ec5-3f53667148fc",
      "auctionTitle": "Samsung Galaxy S24 Ultra",
      "bidderName": "Siti Nurhaliza",
      "bidAmount": 12250000,
      "status": "OUTBID",                 ← Outbid by higher bid
      "timestamp": "2026-01-30T13:24:45Z"
    },
    {
      "id": "bid-003",
      "auctionId": "71f3d1d1-3c3a-4629-8ec5-3f53667148fc",
      "auctionTitle": "Samsung Galaxy S24 Ultra",
      "bidderName": "Ahmad Wijaya",
      "bidAmount": 12000000,
      "status": "OUTBID",
      "timestamp": "2026-01-30T13:24:10Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 25,
    "last_page": 3
  }
}

→ Poll this endpoint every 1s while user is viewing
→ Compare data.length with previous to detect new bids
→ Prepend new bids to feed (newest first)
```

---

### Pattern 5: Place Bid (Success)
```
REQUEST:
POST /api/v1/bids/place
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
Content-Type: application/json

{
  "auctionId": "71f3d1d1-3c3a-4629-8ec5-3f53667148fc",
  "bidAmount": 12750000
}

RESPONSE (201):
{
  "success": true,
  "data": {
    "id": "bid-new-uuid",
    "auctionId": "71f3d1d1-3c3a-4629-8ec5-3f53667148fc",
    "bidAmount": 12750000,
    "status": "CURRENT",
    "timestamp": "2026-01-30T13:26:00Z"
  }
}

→ Show success toast/notification
→ Immediately refresh auction details & bid activity
```

---

### Pattern 6: Place Bid (Error - Too Low)
```
REQUEST:
POST /api/v1/bids/place
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
Content-Type: application/json

{
  "auctionId": "71f3d1d1-3c3a-4629-8ec5-3f53667148fc",
  "bidAmount": 12000000
}

RESPONSE (400):
{
  "success": false,
  "error": "Bid amount must be at least 12750000",
  "code": "BID_TOO_LOW"
}

→ Show error message with minimum bid
→ Clear bid input field
→ Keep bid button focused
```

---

### Pattern 7: Place Bid (Error - Not LIVE)
```
REQUEST:
POST /api/v1/bids/place
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
Content-Type: application/json

{
  "auctionId": "81f3d1d1-3c3a-4629-8ec5-3f53667148fd",
  "bidAmount": 9000000
}

RESPONSE (400):
{
  "success": false,
  "error": "Cannot bid on non-LIVE auction",
  "code": "AUCTION_NOT_LIVE"
}

→ Disable bid button
→ Show "Auction has ended" or "Auction not started yet" message
→ Show remaining time or status
```

---

### Pattern 8: Place Bid (Error - Unauthorized)
```
REQUEST:
POST /api/v1/bids/place
Authorization: Bearer invalid_token

RESPONSE (401):
{
  "success": false,
  "error": "Unauthorized",
  "code": "UNAUTHORIZED"
}

→ Redirect to login page
→ Clear localStorage token
```

---

## 🎯 Frontend Implementation Examples

### React Hook Pattern (Auction Detail)
```javascript
import { useState, useEffect } from 'react'

export function AuctionDetail({ auctionId }) {
  const [auction, setAuction] = useState(null)
  const [bids, setBids] = useState([])
  const [loading, setLoading] = useState(true)
  const token = localStorage.getItem('portal_token')

  // Initial load
  useEffect(() => {
    const loadData = async () => {
      const auctionRes = await fetch(
        `/api/v1/auctions/portal/${auctionId}`
      )
      const bidsRes = await fetch(
        `/api/v1/bids/activity?auctionId=${auctionId}`
      )
      
      setAuction(auctionRes.json())
      setBids(bidsRes.json())
      setLoading(false)
    }
    
    loadData()
  }, [auctionId])

  // Polling for updates
  useEffect(() => {
    const interval = setInterval(async () => {
      const res = await fetch(`/api/v1/auctions/portal/${auctionId}`)
      const updated = await res.json()
      setAuction(updated.data)

      const bidsRes = await fetch(
        `/api/v1/bids/activity?auctionId=${auctionId}`
      )
      const updatedBids = await bidsRes.json()
      setBids(updatedBids.data)
    }, 500)

    return () => clearInterval(interval)
  }, [auctionId])

  const handleBid = async (amount) => {
    const res = await fetch('/api/v1/bids/place', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        auctionId,
        bidAmount: amount
      })
    })

    const result = await res.json()
    
    if (result.success) {
      // Immediately refresh
      const auctionRes = await fetch(`/api/v1/auctions/portal/${auctionId}`)
      setAuction((await auctionRes.json()).data)
      
      const bidsRes = await fetch(
        `/api/v1/bids/activity?auctionId=${auctionId}`
      )
      setBids((await bidsRes.json()).data)
    } else {
      showError(result.error)
    }
  }

  if (loading) return <Spinner />

  return (
    <div>
      <h1>{auction.title}</h1>
      <div className="current-bid">
        Current Bid: Rp{auction.currentBid.toLocaleString('id-ID')}
      </div>
      <div className="participant-count">
        {auction.participantCount} people bidding
      </div>
      <BidForm 
        minBid={auction.currentBid + auction.bidIncrement}
        onBid={handleBid}
      />
      <BidFeed bids={bids} />
    </div>
  )
}
```

---

### Vue 3 Composition API Pattern
```vue
<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({ auctionId: String })
const auction = ref(null)
const bids = ref([])
const token = localStorage.getItem('portal_token')
let pollInterval

async function fetchAuction() {
  const res = await fetch(`/api/v1/auctions/portal/${props.auctionId}`)
  const data = await res.json()
  auction.value = data.data
}

async function fetchBids() {
  const res = await fetch(
    `/api/v1/bids/activity?auctionId=${props.auctionId}`
  )
  const data = await res.json()
  bids.value = data.data
}

async function placeBid(amount) {
  const res = await fetch('/api/v1/bids/place', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      auctionId: props.auctionId,
      bidAmount: amount
    })
  })

  const result = await res.json()
  if (result.success) {
    await fetchAuction()
    await fetchBids()
  }
}

onMounted(async () => {
  await fetchAuction()
  await fetchBids()
  
  // Start polling
  pollInterval = setInterval(async () => {
    await fetchAuction()
    await fetchBids()
  }, 500)
})

onUnmounted(() => {
  clearInterval(pollInterval)
})
</script>

<template>
  <div v-if="auction">
    <h1>{{ auction.title }}</h1>
    <div>Current: Rp{{ auction.currentBid }}</div>
    <div>{{ auction.participantCount }} bidders</div>
    <input v-model="bidAmount" type="number" />
    <button @click="placeBid(bidAmount)">Bid Now</button>
    <div v-for="bid in bids" :key="bid.id">
      {{ bid.bidderName }} - Rp{{ bid.bidAmount }}
    </div>
  </div>
</template>
```

---

## 🔍 Debugging Tips

```javascript
// Check current auction state
console.log('Auction:', auction)

// Check bid list
console.log('Bids:', bids)

// Check token validity
console.log('Token:', localStorage.getItem('portal_token'))

// Monitor polling requests
const originalFetch = window.fetch
window.fetch = function(...args) {
  console.log('API Call:', args[0])
  return originalFetch.apply(this, args)
}
```

---

## ⏱️ Timing Reference

```
Action                  Response Time    Update UI After
─────────────────────────────────────────────────────
Place Bid               ~200ms          Immediately (polling will catch)
Get Auction Details     ~100ms          Every 500ms
Get Bid Activity        ~150ms          Every 1000ms
List Auctions           ~200ms          Every 2000ms
Login                   ~300ms          Immediately
```

---

## 📊 Network Load Estimate

```
Scenario: 1 user watching 1 auction

Requests/second:
- GET /auctions/portal/:id    = 2 req/sec (500ms)
- GET /bids/activity          = 1 req/sec (1000ms)
- Total: 3 req/sec (watching)

Data transferred/sec:
- Auction: ~5KB × 2 = 10KB
- Bids: ~8KB × 1 = 8KB
- Total: ~18KB/sec

Daily estimate (8 hours watching):
- Requests: ~86,400 requests
- Data: ~518 MB
```

---

## 🚨 Common Mistakes

```javascript
// ❌ Wrong: Polling without clearing interval
useEffect(() => {
  setInterval(fetch(...), 500)  // Creates multiple intervals!
})

// ✅ Right: Clear interval
useEffect(() => {
  const interval = setInterval(fetch(...), 500)
  return () => clearInterval(interval)
}, [])

// ❌ Wrong: Not checking token before API call
const handleBid = async () => {
  const res = await fetch('/api/v1/bids/place', ...)  // No token!
}

// ✅ Right: Include token
const handleBid = async () => {
  const token = localStorage.getItem('portal_token')
  const res = await fetch('/api/v1/bids/place', {
    headers: { 'Authorization': `Bearer ${token}` }
  })
}

// ❌ Wrong: Not handling 401 responses
const res = await fetch(...)
const data = await res.json()  // Doesn't check status!

// ✅ Right: Check status
const res = await fetch(...)
if (res.status === 401) {
  localStorage.removeItem('portal_token')
  redirectToLogin()
}
const data = await res.json()
```

---

**Last Updated:** January 30, 2026
