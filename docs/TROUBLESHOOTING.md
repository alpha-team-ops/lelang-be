# API Troubleshooting & Common Issues

**For:** Backend & Frontend Developers | **Purpose:** Quick problem solving

---

## 🔴 Common API Errors

### Error: "Bid amount must be at least X"
```
Code: BID_TOO_LOW
HTTP: 400

Cause: Bid < (currentBid + bidIncrement)

Example:
- currentBid: 8,500,000
- bidIncrement: 250,000
- minimumBid: 8,750,000
- user sent: 8,500,000 ❌

Solution (Frontend):
- Show input field with min value
- Display "Minimum bid: Rp 8,750,000"
- Disable submit button until amount meets minimum
- Show live validation feedback

<input 
  type="number" 
  min="8750000" 
  step="250000"
  placeholder="Minimum: 8,750,000"
/>
```

---

### Error: "Cannot bid on non-LIVE auction"
```
Code: AUCTION_NOT_LIVE
HTTP: 400

Cause: Auction status is DRAFT, ENDED, or CANCELLED

Possible Scenarios:
1. Auction hasn't started yet (status = DRAFT)
   - Show countdown to start
   - Disable bid button with "Coming soon"

2. Auction has ended (status = ENDED)
   - Show "Auction ended"
   - Disable bid button

3. Auction was cancelled (status = CANCELLED)
   - Show "Auction cancelled"
   - Hide bid section

Solution (Frontend):
- Check auction.status before showing bid form
- Only enable bid form if status === 'LIVE'
- Show status message explaining why bidding disabled

if (auction.status !== 'LIVE') {
  return <div>Bidding not available - {auction.status}</div>
}
```

---

### Error: "You cannot bid on your own auction"
```
Code: CANNOT_BID_OWN_AUCTION
HTTP: 400

Cause: bidder_id === seller_id (same user)

Why: Business rule - seller can't bid own items

Solution (Backend):
- Check during bid placement:
  if ($bid->bidder_id === $auction->seller_id) {
    throw ValidationException
  }

Solution (Frontend):
- Hide bid button if user is seller
- Show "You are the seller" message

const isSeller = currentUser.id === auction.seller_id
if (isSeller) {
  return <div>You listed this auction</div>
}
```

---

### Error: "Unauthorized" or "Invalid Token"
```
Code: UNAUTHORIZED
HTTP: 401

Cause: Missing or invalid Bearer token

Scenarios:
1. Token expired
2. Token malformed
3. Token missing from headers
4. User logged out

Solution (Frontend):
- Check localStorage for token
- If missing: redirect to login
- If expired: refresh token or re-login
- Always include header:
  'Authorization': `Bearer ${token}`

const token = localStorage.getItem('portal_token')
if (!token) {
  redirectToLogin()
}

const response = await fetch(url, {
  headers: {
    'Authorization': `Bearer ${token}`
  }
})

if (response.status === 401) {
  localStorage.removeItem('portal_token')
  redirectToLogin()
}
```

---

### Error: "Auction not found"
```
Code: AUCTION_NOT_FOUND
HTTP: 404

Cause: auctionId doesn't exist

Scenarios:
1. User typed wrong URL/ID
2. Auction was deleted
3. Auction belongs to different organization

Solution (Frontend):
- Validate auctionId is UUID format
- Show 404 page with "Auction not found"
- Provide link back to auction list
- Check if user has access to organization

if (!response.ok && response.status === 404) {
  return <NotFoundPage />
}
```

---

## ⚠️ Common Frontend Issues

### Issue: Bid Form Not Updating After Bid Placed
```
Symptom: User places bid, but currentBid doesn't update

Cause: Not refreshing auction data after bid submission

Solution:
// ❌ Wrong: Only update locally
setBidAmount('')  // Just clear form

// ✅ Right: Re-fetch from server
const response = await fetch('/api/v1/bids/place', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` },
  body: JSON.stringify({ auctionId, bidAmount })
})

if (response.ok) {
  // Immediately refresh auction data
  const auctionRes = await fetch(`/api/v1/auctions/portal/${auctionId}`)
  const updatedAuction = await auctionRes.json()
  setAuction(updatedAuction.data)
  
  // Also refresh bid history
  const bidsRes = await fetch(`/api/v1/bids/activity?auctionId=${auctionId}`)
  const updatedBids = await bidsRes.json()
  setBids(updatedBids.data)
}
```

---

### Issue: Polling Causes Memory Leak
```
Symptom: Browser tab uses more & more memory over time

Cause: setInterval not being cleared when component unmounts

Solution (React):
// ❌ Wrong: Creates new interval every render
useEffect(() => {
  setInterval(fetch(...), 500)  // MEMORY LEAK!
})

// ✅ Right: Clear interval on unmount
useEffect(() => {
  const interval = setInterval(fetch(...), 500)
  
  return () => {
    clearInterval(interval)  // Clean up!
  }
}, [auctionId])  // Dependencies
```

---

### Issue: Bid Button Still Enabled After Auction Ended
```
Symptom: User can still click bid button even after auction ended

Cause: Not checking updated auction status

Solution:
// Fetch auction details every 500ms
useEffect(() => {
  const interval = setInterval(async () => {
    const res = await fetch(`/api/v1/auctions/portal/${auctionId}`)
    const updated = await res.json()
    setAuction(updated.data)  // Updates status!
  }, 500)
  
  return () => clearInterval(interval)
}, [auctionId])

// Button state depends on auction.status
const isBiddingActive = auction.status === 'LIVE'

<button disabled={!isBiddingActive}>Bid</button>
```

---

### Issue: Token Not Sent in API Call
```
Symptom: All API calls get 401 Unauthorized

Cause: Missing Authorization header

Solution:
// ❌ Wrong: No token
const response = await fetch('/api/v1/bids/place', {
  method: 'POST',
  body: JSON.stringify(...)
})

// ✅ Right: Include token
const token = localStorage.getItem('portal_token')
const response = await fetch('/api/v1/bids/place', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(...)
})

// ✅ Or create reusable function
async function apiCall(url, options = {}) {
  const token = localStorage.getItem('portal_token')
  return fetch(url, {
    ...options,
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      ...options.headers
    }
  })
}

// Use it
const response = await apiCall('/api/v1/bids/place', {
  method: 'POST',
  body: JSON.stringify({ auctionId, bidAmount })
})
```

---

### Issue: Countdown Timer Shows Negative Time
```
Symptom: Timer shows "-0:05:30" after auction ends

Cause: Not stopping timer when endTime is reached

Solution:
useEffect(() => {
  const interval = setInterval(() => {
    const now = new Date()
    const endTime = new Date(auction.endTime)
    let diff = endTime - now

    // ✅ Stop at zero
    if (diff < 0) {
      diff = 0
      // Optional: stop polling
      clearInterval(interval)
    }

    const hours = Math.floor(diff / 3600000)
    const minutes = Math.floor((diff % 3600000) / 60000)
    const seconds = Math.floor((diff % 60000) / 1000)

    setCountdown(`${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`)
  }, 1000)

  return () => clearInterval(interval)
}, [auction.endTime])
```

---

## 🔧 Backend Debugging

### How to Check if Bid Was Saved
```php
// In BidController::place()

$newBid = Bid::create([
    'id' => Str::uuid(),
    'auction_id' => $validated['auctionId'],
    'bidder_id' => $bidder->id,
    'bid_amount' => $validated['bidAmount'],
    'status' => 'CURRENT',
    'bid_timestamp' => now(),
]);

// Verify it exists
if ($newBid->wasRecentlyCreated) {
    Log::info('Bid created successfully', ['bid_id' => $newBid->id]);
} else {
    Log::error('Bid creation failed');
}

// Check DB directly
$bid = Bid::find($newBid->id);
if ($bid) {
    Log::info('Bid verified in database', $bid->toArray());
}
```

---

### How to Check Auction Status
```php
// Verify status calculation
$auction = Auction::find($auctionId);

$now = now();
$status = $auction->calculateStatus();

Log::debug('Auction Status Check', [
    'auction_id' => $auctionId,
    'start_time' => $auction->start_time,
    'end_time' => $auction->end_time,
    'current_time' => $now,
    'calculated_status' => $status,
    'stored_status' => $auction->status,
]);

// If mismatch, update
if ($status !== $auction->status) {
    $auction->update(['status' => $status]);
    Log::warning('Status mismatch fixed', [
        'was' => $auction->status,
        'now' => $status
    ]);
}
```

---

### How to Check Token
```php
// In middleware or controller

$user = $request->user();

if (!$user) {
    Log::error('User not authenticated', [
        'token' => $request->bearerToken() ? 'present' : 'missing'
    ]);
    return response()->json(['error' => 'Unauthorized'], 401);
}

Log::info('User authenticated', [
    'user_id' => $user->id,
    'name' => $user->name,
    'org_code' => $user->organization_code
]);
```

---

## 📊 Database Check Commands

### Check Recent Bids
```sql
SELECT 
    b.id, 
    b.auction_id, 
    u.name as bidder,
    b.bid_amount, 
    b.status,
    b.bid_timestamp
FROM bids b
JOIN users u ON b.bidder_id = u.id
ORDER BY b.created_at DESC
LIMIT 10;
```

### Check Auction Status
```sql
SELECT 
    id,
    title,
    status,
    start_time,
    end_time,
    current_bid,
    total_bids,
    participant_count
FROM auctions
WHERE id = 'YOUR_AUCTION_ID';
```

### Check User Token
```sql
SELECT 
    id,
    name,
    organization_code,
    created_at,
    updated_at
FROM users
WHERE id = 'YOUR_USER_ID';
```

---

## 🧪 Quick Test Script

```bash
#!/bin/bash

# Test variables
BASE_URL="http://localhost:8000/api/v1"
CORP_ID="EMP-12345"
USER_NAME="Test User"

echo "=== Portal Auction Test ==="

# 1. Login
echo "1. Logging in..."
LOGIN=$(curl -s -X POST "$BASE_URL/auth/portal-login" \
  -H "Content-Type: application/json" \
  -d "{\"corporateId\":\"$CORP_ID\",\"name\":\"$USER_NAME\"}")

TOKEN=$(echo $LOGIN | jq -r '.data.token')
USERID=$(echo $LOGIN | jq -r '.data.user.id')
echo "   Token: ${TOKEN:0:20}..."
echo "   User ID: $USERID"

# 2. Get auctions
echo "2. Getting auctions..."
AUCTIONS=$(curl -s "$BASE_URL/auctions/portal/list")
AUCTION_ID=$(echo $AUCTIONS | jq -r '.data[0].id')
echo "   Found auction: $AUCTION_ID"

# 3. Get auction details
echo "3. Getting auction details..."
AUCTION=$(curl -s "$BASE_URL/auctions/portal/$AUCTION_ID")
CURRENT_BID=$(echo $AUCTION | jq -r '.data.currentBid')
BID_INCREMENT=$(echo $AUCTION | jq -r '.data.bidIncrement')
STATUS=$(echo $AUCTION | jq -r '.data.status')
echo "   Status: $STATUS"
echo "   Current bid: Rp $CURRENT_BID"
echo "   Bid increment: Rp $BID_INCREMENT"

# 4. Place bid
if [ "$STATUS" = "LIVE" ]; then
  MIN_BID=$((CURRENT_BID + BID_INCREMENT))
  echo "4. Placing bid (minimum: Rp $MIN_BID)..."
  
  BID=$(curl -s -X POST "$BASE_URL/bids/place" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"auctionId\":\"$AUCTION_ID\",\"bidAmount\":$MIN_BID}")
  
  SUCCESS=$(echo $BID | jq -r '.success')
  if [ "$SUCCESS" = "true" ]; then
    echo "   ✅ Bid placed successfully!"
    echo $BID | jq '.data'
  else
    echo "   ❌ Bid failed:"
    echo $BID | jq '.'
  fi
else
  echo "4. Cannot place bid - auction status is $STATUS"
fi

echo ""
echo "=== Test Complete ==="
```

---

## 📞 Getting Help

### Debug Information to Provide

**For API errors, include:**
```
1. Full error message & code
2. Request URL & method
3. Request headers (without token)
4. Request body
5. Response status code
6. Response body
7. Timestamp (when error occurred)
8. User ID / Auction ID
```

**Example:**
```
Error placing bid:

Request:
POST /api/v1/bids/place
Authorization: Bearer [hidden]
Content-Type: application/json
{
  "auctionId": "71f3d1d1-3c3a-4629-8ec5-3f53667148fc",
  "bidAmount": 8000000
}

Response (400):
{
  "success": false,
  "error": "Bid amount must be at least 8750000",
  "code": "BID_TOO_LOW"
}

Time: 2026-01-30 13:25:10 UTC
Auction: 71f3d1d1-3c3a-4629-8ec5-3f53667148fc (Samsung S24)
```

---

**Last Updated:** January 30, 2026
