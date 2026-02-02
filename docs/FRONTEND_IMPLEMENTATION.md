# Frontend Implementation Checklist

**Purpose:** Quick reference for FE developer  
**Status:** Phase 2 - WebSocket Real-time Ready!

---

## 🚀 MAJOR UPDATE: WebSocket Available! (Optional)

```
Option 1: Use WebSocket (recommended)
   ✅ Instant real-time updates
   ✅ No polling needed
   ✅ Lower network load
   ✅ Better UX
   → Refer to app/websocket-example.js

Option 2: Keep polling (still works)
   ✅ Use existing code
   ✅ No changes needed
   ✅ Works perfectly
   → Same as Phase 1
```

---

## 📋 Feature Checklist

### 1. Authentication
- [ ] Login form with corporateId & name fields
- [ ] POST /api/v1/auth/portal-login
- [ ] Store token in localStorage
- [ ] Verify token on page load
- [ ] Redirect to login if no token

**Reference:** API_QUICK_REFERENCE.md → "Login Portal User"

---

### 2. Auction List Page
- [ ] Fetch all LIVE auctions: GET /api/v1/auctions/portal/list
- [ ] Display auction cards (title, current bid, end time)
- [ ] **Subscribe to WebSocket channel.auction.{id}** (NEW - optional)
- [ ] Update currentBid in real-time (WebSocket or polling)
- [ ] Show participantCount
- [ ] Handle image display
- [ ] Click card → go to auction detail page

**Reference:** API_PATTERNS_EXAMPLES.md → "React Hook Pattern"  
**NEW:** See app/websocket-example.js for WebSocket implementation

---

### 3. Auction Detail Page
- [ ] Fetch auction details: GET /api/v1/auctions/portal/:id
- [ ] Display full auction info (title, description, images)
- [ ] Show current bid & reserve price
- [ ] **Listen to WebSocket events** (NEW - optional)
- [ ] Update currentBid (WebSocket or poll every 500ms)
- [ ] Update participantCount (WebSocket or poll)
- [ ] Update status (WebSocket or poll)
- [ ] **Listen to auction.ended event** (NEW - optional)

**Reference:** AUCTION_STATUS_DISPLAY.md → "Frontend Implementation"  
**IMPROVEMENT:** Real-time updates without polling!

---

### 4. Countdown Timer
- [ ] Calculate time remaining: endTime - now
- [ ] Update timer every 1 second
- [ ] Format as "HH:MM:SS"
- [ ] Show "Ends in" for LIVE
- [ ] Show "Starts in" for DRAFT
- [ ] Stop at 0:00:00 for ENDED
- [ ] Change color to RED when < 5 minutes remaining

**Reference:** AUCTION_STATUS_DISPLAY.md → "React Example"

---

### 5. Bid Button Logic

- [ ] **DRAFT status** → Show "Coming Soon", disabled button
- [ ] **LIVE status** → Show "Place Bid", enabled button
- [ ] **ENDING status** (< 5 min) → Show "Bid Now!", red button
- [ ] **ENDED status** → Show "Auction Ended", disabled button
- [ ] Show minimum bid amount
- [ ] Input validation: bidAmount >= currentBid + bidIncrement
- [ ] Real-time validation feedback

**Reference:** AUCTION_STATUS_DISPLAY.md → "CSS Styling Guide"

---

### 6. Bid Placement Form
- [ ] Input field with min value validation
- [ ] Show minimum required bid
- [ ] Show bid increment
- [ ] Handle decimal values (Rupiah currency)
- [ ] POST /api/v1/bids/place with token
- [ ] Show loading state during submit
- [ ] Show success toast notification
- [ ] Show error message if bid fails
- [ ] Clear form after success
- [ ] Auto-disable if auction not LIVE

**Reference:** API_QUICK_REFERENCE.md → "Place Bid"

---

### 7. Bid Activity Feed
- [ ] Fetch bid history: GET /api/v1/bids/activity?auctionId=:id
- [ ] Display as list (newest first)
- [ ] Show: bidder name, amount, time, status
- [ ] Poll for updates every 1 second
- [ ] Add new bids to top of list
- [ ] Show "CURRENT" status for highest bid
- [ ] Show "OUTBID" status for previous bids
- [ ] Format timestamps (relative or absolute)
- [ ] Pagination (if > 20 bids)

**Reference:** REALTIME_BID_SYSTEM.md → "Pattern 4"

---

### 8. Error Handling
- [ ] Handle 400 "BID_TOO_LOW" → show minimum required
- [ ] Handle 400 "AUCTION_NOT_LIVE" → disable bidding
- [ ] Handle 400 "CANNOT_BID_OWN_AUCTION" → hide bid section
- [ ] Handle 401 "UNAUTHORIZED" → redirect to login
- [ ] Handle 404 "AUCTION_NOT_FOUND" → show 404 page
- [ ] Show user-friendly error messages
- [ ] Log errors to console for debugging

**Reference:** TROUBLESHOOTING.md → "Common API Errors"

---

### 9. Performance & Optimization
- [ ] Stop polling when component unmounts (memory leak prevention)
- [ ] Prevent multiple intervals being created
- [ ] Reduce poll frequency when tab is hidden
- [ ] Increase poll frequency when user focuses tab
- [ ] Cache auction list (30 seconds)
- [ ] Don't cache bid activity (always fresh)
- [ ] Debounce bid input validation

**Reference:** REALTIME_BID_SYSTEM.md → "Polling Recommended"

---

### 10. Mobile Responsiveness
- [ ] Auction cards stack on mobile
- [ ] Bid input fits on small screens
- [ ] Countdown timer readable on mobile
- [ ] Bid activity feed scrollable on mobile
- [ ] Button sizes appropriate for touch
- [ ] Images scale responsively

---

## 🎨 UI Components to Create

### Component: AuctionCard
```
Props:
- auction: Auction object
- onClick: handler

Display:
- Title
- Image
- Current bid
- Reserve price (if not met)
- End time countdown
- Participant count
- Bid button state
```

### Component: AuctionDetail
```
Props:
- auctionId: string

Display:
- Full auction info
- Carousel of images
- Current bid + reserve info
- Countdown timer
- Bid form
- Bid activity feed
- Status badge
```

### Component: BidForm
```
Props:
- minBid: number
- onBid: (amount: number) => void
- isLoading: boolean
- disabled: boolean

Display:
- Amount input
- Min/max validation
- Error messages
- Submit button
- Loading state
```

### Component: BidActivityFeed
```
Props:
- auctionId: string
- bids: Bid[]
- isLoading: boolean

Display:
- List of bids
- Bidder name, amount, time
- Status badge (CURRENT/OUTBID)
- Pagination or infinite scroll
```

---

## 🧪 Manual Testing Checklist

### Test Scenario 1: Browse & View
- [ ] Open app → login with test credentials
- [ ] See auction list with multiple auctions
- [ ] Click auction → go to detail page
- [ ] See full info, bid history, countdown
- [ ] Refresh → still shows same data
- [ ] Wait 1 min → confirm polling updates working

### Test Scenario 2: Place Bid (Success)
- [ ] Open LIVE auction
- [ ] Enter bid amount >= minimum
- [ ] Click "Place Bid"
- [ ] See success message
- [ ] See currentBid updated
- [ ] See own bid in activity feed
- [ ] See participantCount increased

### Test Scenario 3: Place Bid (Error - Too Low)
- [ ] Open LIVE auction
- [ ] Enter bid < minimum
- [ ] Click "Place Bid"
- [ ] See error message with minimum amount
- [ ] Form still filled
- [ ] Can retry with higher amount

### Test Scenario 4: Auction Ending
- [ ] Open auction with < 5 min remaining
- [ ] See countdown in RED
- [ ] See "Bid Now!" button in RED
- [ ] Try to bid → works normally
- [ ] Watch countdown reach 0
- [ ] See status change to ENDED
- [ ] Bid button becomes disabled

### Test Scenario 5: Auction Already Ended
- [ ] Open ENDED auction
- [ ] See "Auction Ended" message
- [ ] Bid button disabled
- [ ] Activity feed shows final winner
- [ ] No polling happening (stopped)

### Test Scenario 6: Token Expiry
- [ ] Login normally
- [ ] Manually clear localStorage token
- [ ] Try to place bid
- [ ] See 401 error
- [ ] Redirect to login page
- [ ] Login again → can bid

---

## 📊 Data Flow Diagram

```
User Opens App
    ↓
Login Page
    ↓ [Login successful]
Store Token in localStorage
    ↓
Auction List Page
    ↓ [Click auction]
Auction Detail Page
    ├─ GET /auctions/portal/:id (initial)
    ├─ GET /bids/activity?auctionId=:id (initial)
    ├─ setInterval every 500ms → GET /auctions/portal/:id
    ├─ setInterval every 1s → GET /bids/activity
    └─ [User places bid]
        ├─ POST /bids/place (with token)
        ├─ [Success] → Refresh both endpoints
        └─ [Error] → Show error message
```

---

## 🚀 Implementation Order (Recommended)

**Week 1:**
1. Setup project & routing
2. Implement Login page
3. Create AuctionCard component
4. Create Auction List page

**Week 2:**
5. Create Auction Detail page
6. Implement polling logic (500ms)
7. Add countdown timer
8. Create Bid Form component

**Week 3:**
9. Implement bid placement
10. Add error handling
11. Create Bid Activity Feed
12. Polish & optimize

**Week 4:**
13. Testing & bug fixes
14. Mobile responsiveness
15. Performance optimization
16. Deployment

---

## 💡 Code Snippets Ready to Use

### Setup Polling Hook (React)
```javascript
// See API_PATTERNS_EXAMPLES.md → "React Hook Pattern"
```

### Currency Formatting (Indonesia)
```javascript
const formatted = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  minimumFractionDigits: 0
}).format(amount)
// Result: "Rp 9.000.000"
```

### Time Formatting
```javascript
const timeFormatter = new Intl.DateTimeFormat('id-ID', {
  year: 'numeric',
  month: 'long',
  day: 'numeric',
  hour: '2-digit',
  minute: '2-digit'
})
const formatted = timeFormatter.format(new Date())
```

### Countdown Calculator
```javascript
function formatCountdown(endTime) {
  const diff = new Date(endTime) - new Date()
  if (diff <= 0) return '0:00:00'
  
  const hours = Math.floor(diff / 3600000)
  const minutes = Math.floor((diff % 3600000) / 60000)
  const seconds = Math.floor((diff % 60000) / 1000)
  
  return `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
}
```

---

## 🐛 Common Gotchas

### Gotcha 1: Multiple Intervals
```javascript
// ❌ Wrong - creates new interval every render
useEffect(() => {
  setInterval(poll, 500)
})

// ✅ Right - cleanup interval on unmount
useEffect(() => {
  const i = setInterval(poll, 500)
  return () => clearInterval(i)
}, [])
```

### Gotcha 2: Missing Token
```javascript
// ❌ Wrong - no Authorization header
fetch('/api/v1/bids/place', { method: 'POST', ... })

// ✅ Right - include token
const token = localStorage.getItem('portal_token')
fetch('/api/v1/bids/place', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` },
  ...
})
```

### Gotcha 3: Race Condition
```javascript
// ❌ Wrong - multiple bid requests can overlap
onClick={() => placeBid()} // Can be clicked multiple times

// ✅ Right - disable while loading
<button disabled={isLoading} onClick={placeBid}>
  {isLoading ? 'Processing...' : 'Place Bid'}
</button>
```

---

## 📞 Reference Links

| Need | See |
|------|-----|
| API endpoints | [API_QUICK_REFERENCE.md](API_QUICK_REFERENCE.md) |
| Code examples | [API_PATTERNS_EXAMPLES.md](API_PATTERNS_EXAMPLES.md) |
| Status logic | [AUCTION_STATUS_DISPLAY.md](AUCTION_STATUS_DISPLAY.md) |
| Errors | [TROUBLESHOOTING.md](TROUBLESHOOTING.md) |
| Polling | [REALTIME_BID_SYSTEM.md](REALTIME_BID_SYSTEM.md) |
| Full index | [README_REALTIME_API.md](README_REALTIME_API.md) |

---

## ✅ Sign-off Checklist

Before going live:
- [x] All 10 features implemented (includes WebSocket + polling options)
- [ ] All manual tests passed
- [ ] Error handling works
- [ ] Mobile responsive
- [ ] Token refresh handled
- [ ] No memory leaks
- [ ] Performance acceptable
- [ ] Code reviewed
- [ ] Deployed to staging
- [ ] Tested on real database
- [x] WebSocket channel subscriptions configured
- [x] Real-time event listeners ready
- [ ] Fallback to polling tested
- [ ] Load testing completed (WebSocket + polling)

---

**Last Updated:** January 30, 2026  
**Status:** Phase 2 - WebSocket Real-time Ready + Polling Fallback
