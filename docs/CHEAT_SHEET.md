# ⚡ API Cheat Sheet (Print-Friendly)

**For Quick Reference While Coding**

---

## 🔑 Essential Endpoints

```
LOGIN
POST /api/v1/auth/portal-login
{"corporateId":"123","name":"John"}
→ Get token

LIST
GET /api/v1/auctions/portal/list
→ All LIVE auctions

DETAIL
GET /api/v1/auctions/portal/{auctionId}
→ Auction info

BIDS
GET /api/v1/bids/activity?auctionId={id}
→ Bid history

PLACE
POST /api/v1/bids/place
Auth required
{"auctionId":"uuid","bidAmount":9000000}
→ Place bid
```

---

## 📨 Request Template

```
URL: /api/v1/bids/place
Method: POST
Headers:
  Authorization: Bearer <TOKEN>
  Content-Type: application/json

Body:
{
  "auctionId": "uuid-here",
  "bidAmount": 9000000
}
```

---

## ✅ Bid Rules

```
Valid bid: bidAmount >= currentBid + bidIncrement

Example:
  Current: 8,500,000
  Increment: 250,000
  Minimum: 8,750,000 ✅

Auction must be: LIVE or ENDING
```

---

## 🔴 Error Codes

```
BID_TOO_LOW              → Show minimum amount
AUCTION_NOT_LIVE         → Disable bid button
CANNOT_BID_OWN_AUCTION   → Hide bid section
UNAUTHORIZED             → Redirect login
AUCTION_NOT_FOUND        → Show 404
```

---

## ⏱️ Status & Countdown

```
Status           Button State   Countdown
──────────────────────────────────────────
DRAFT            Disabled       "Starts in HH:MM:SS"
LIVE (normal)    Enabled        "Ends in HH:MM:SS"
LIVE (< 5 min)   Red/Urgent     "🔴 Ends in MM:SS"
ENDED            Disabled       "Ended at HH:MM"
```

---

## 📊 Polling Intervals

```
Auction details: 500ms
Bid activity:    1000ms
Auction list:    2000ms

Stop polling when:
- Tab hidden
- Component unmounted
- Auction ENDED
```

---

## 🔐 Token Management

```
Save after login:
localStorage.setItem('portal_token', response.data.token)

Use in requests:
headers: {
  'Authorization': `Bearer ${localStorage.getItem('portal_token')}`
}

On 401:
localStorage.removeItem('portal_token')
redirectToLogin()
```

---

## 💻 Response Templates

### Login Response
```json
{
  "success": true,
  "data": {
    "token": "jwt-token",
    "user": {
      "id": "uuid",
      "name": "John",
      "organizationCode": "ORG-001"
    }
  }
}
```

### Auction Response
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "title": "...",
    "currentBid": 9000000,
    "status": "LIVE",
    "endTime": "2026-01-30T15:00:00Z",
    "participantCount": 12
  }
}
```

### Bid Response
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

---

## 🎨 Display Formula

```javascript
// Countdown timer
remaining = endTime - now
hours = floor(remaining / 3600000)
minutes = floor((remaining % 3600000) / 60000)
seconds = floor((remaining % 60000) / 1000)
display = `${h}:${m.padStart(2,'0')}:${s.padStart(2,'0')}`

// Currency format (Indonesia)
formatted = amount.toLocaleString('id-ID', {
  style: 'currency',
  currency: 'IDR',
  minimumFractionDigits: 0
})
```

---

## 🔄 React Hook Template

```javascript
const [auction, setAuction] = useState(null)
const token = localStorage.getItem('portal_token')

useEffect(() => {
  const interval = setInterval(async () => {
    const res = await fetch(
      `/api/v1/auctions/portal/${auctionId}`
    )
    const data = await res.json()
    setAuction(data.data)
  }, 500)

  return () => clearInterval(interval)
}, [auctionId])
```

---

## 🚨 Common Mistakes

```
❌ setInterval in useEffect without cleanup
✅ Return cleanup function: return () => clearInterval(i)

❌ Missing Authorization header
✅ Always include: 'Authorization': `Bearer ${token}`

❌ Not disabling button while bidding
✅ Add disabled prop while loading

❌ Polling forever after unmount
✅ Clear interval on unmount

❌ Using alert() for errors
✅ Show toast/modal notifications
```

---

## 📱 Mobile Responsive

```
✅ Stack cards vertically
✅ Full-width inputs
✅ Touch-friendly buttons (44px min)
✅ Readable text (16px+)
✅ 1-2 columns max on mobile
```

---

## 🧪 Test with Curl

```bash
# Login
curl -X POST http://localhost:8000/api/v1/auth/portal-login \
  -H "Content-Type: application/json" \
  -d '{"corporateId":"123","name":"Test"}'

# Get auctions
curl http://localhost:8000/api/v1/auctions/portal/list

# Place bid (replace TOKEN and IDs)
curl -X POST http://localhost:8000/api/v1/bids/place \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"auctionId":"UUID","bidAmount":9000000}'
```

---

## 📞 Documentation Map

| Need | File |
|------|------|
| Start here | API_QUICK_REFERENCE.md |
| Examples | API_PATTERNS_EXAMPLES.md |
| Status logic | AUCTION_STATUS_DISPLAY.md |
| Errors | TROUBLESHOOTING.md |
| Full guide | REALTIME_BID_SYSTEM.md |
| FE checklist | FRONTEND_IMPLEMENTATION.md |
| All docs | README_REALTIME_API.md |

---

## 📝 Quick Notes

```
• API is REST + Polling (no WebSocket yet)
• Token in localStorage
• Check token on every request
• Handle 401 responses
• Always clear intervals on unmount
• Bid amount must include increment
• Status auto-calculated from times
• Use ISO8601 for all timestamps
• All IDs are UUID v4 format
```

---

**Print this sheet & keep at desk!**  
Last updated: Jan 30, 2026
