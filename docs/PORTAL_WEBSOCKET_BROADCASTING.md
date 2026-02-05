## Portal WebSocket Broadcasting - Backend Triggers

### Current Implementation Status ✅

Backend **sudah kirim broadcast** ke channel `auction.{auctionId}` via:

#### 1. **recordView() endpoint** - When user views auction
```
POST /api/v1/auctions/{auctionId}/view
Headers: Authorization: Bearer {userToken}

Broadcast:
  Channel: auction.{auctionId}
  Event: auction.updated
  Data: {
    "id": "{auctionId}",
    "currentBid": 30000000,
    "status": "LIVE",
    "viewCount": 126
  }
```

#### 2. **Place Bid endpoint** - When bid is placed
```
POST /api/v1/bids/place
Headers: Authorization: Bearer {userToken}
Body: {
  "auction_id": "{auctionId}",
  "bid_amount": 31000000
}

Broadcast (from BidController):
  Channel: auction.{auctionId}
  Event: bid.placed
  Data: {
    "auctionId": "{auctionId}",
    "currentBid": 31000000,
    "bidderName": "Arif Permana",
    "timestamp": "2026-02-05T13:40:15Z"
  }
```

#### 3. **Admin Updates Auction** - When admin updates auction
```
PUT /api/v1/admin/auctions/{auctionId}
Headers: Authorization: Bearer {adminToken}
Body: { "starting_price": 50000000, ... }

Broadcast (from AdminAuctionController):
  Channel: auction.{auctionId}
  Event: auction.updated
  Data: {
    "id": "{auctionId}",
    "currentBid": 30000000,
    "status": "LIVE",
    "viewCount": 126
  }
```

#### 4. **Admin Changes Auction Status to ENDED**
```
PUT /api/v1/admin/auctions/{auctionId}
Headers: Authorization: Bearer {adminToken}
Body: { "status": "ENDED" }

Broadcast (from AdminAuctionController):
  Channel: auction.{auctionId}
  Event: auction.ended
  Data: {
    "id": "{auctionId}",
    "title": "Item Name",
    "status": "ENDED",
    "winningBid": 50000000,
    "winner": {
      "id": "winner-id",
      "fullName": "John Doe",
      "winningBid": 50000000,
      "totalParticipants": 15,
      "status": "PAYMENT_PENDING"
    }
  }
```

---

### What FE Need to Do

**1. Subscribe to Channel** (when component mounts)
```javascript
const echo = new Echo({
  broadcaster: 'pusher',
  key: process.env.REACT_APP_PUSHER_KEY,
  wsHost: 'localhost',
  wsPort: 8080,
  auth: {
    headers: {
      Authorization: `Bearer ${userToken}`
    }
  }
});

echo.channel(`auction.${auctionId}`)
  .listen('auction.updated', (data) => {
    // Update local state with new data
    setAuction(prev => ({
      ...prev,
      currentBid: data.currentBid,
      viewCount: data.viewCount,
      status: data.status
    }));
  })
  .listen('bid.placed', (data) => {
    // Update current bid and show notification
    setAuction(prev => ({
      ...prev,
      currentBid: data.currentBid
    }));
    showNotification(`New bid from ${data.bidderName}`);
  })
  .listen('auction.ended', (data) => {
    // Show end notification and winner info
    setAuction(prev => ({
      ...prev,
      status: 'ENDED',
      winner: data.winner
    }));
  });
```

**2. Call recordView() when auction loads/viewed**
```javascript
useEffect(() => {
  fetch(`http://127.0.0.1:8000/api/v1/auctions/${auctionId}/view`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${userToken}`,
      'Content-Type': 'application/json'
    }
  });
}, [auctionId]);
```

**3. Place Bid**
```javascript
const placeBid = async (bidAmount) => {
  const response = await fetch('http://127.0.0.1:8000/api/v1/bids/place', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${userToken}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      auction_id: auctionId,
      bid_amount: bidAmount
    })
  });
  // Broadcast bid.placed will be received via WebSocket
};
```

---

### Debugging Checklist

- ✅ Echo connected? Check browser DevTools Network → WebSocket
- ✅ Channel subscribed? Check console logs
- ✅ userToken valid? Try calling `/api/v1/auctions/{id}` with same token
- ✅ recordView() called? Check Network tab, should show 200 response
- ✅ Reverb running? Check `php artisan reverb:start` status
- ✅ REVERB_HOST=localhost in .env? Not 0.0.0.0

---

### Test Flow

1. **Admin**: Create/Update auction → FE should see `auction.updated` event
2. **User 1**: View auction → recordView() triggers broadcast, FE sees view count increase
3. **User 2**: Place bid → FE sees `bid.placed` event with new currentBid
4. **Admin**: Mark auction ENDED → FE sees `auction.ended` with winner info

All broadcasts send to **same channel**: `auction.{auctionId}`
