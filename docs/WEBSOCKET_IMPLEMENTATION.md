## WebSocket Implementation untuk FE - Admin vs Portal

### 1. **Setup Echo Connection (Global)**

```javascript
// lib/websocket.js atau hooks/useEcho.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export const createEchoInstance = (authToken = null) => {
    return new Echo({
        broadcaster: 'pusher',
        key: process.env.REACT_APP_PUSHER_KEY,
        wsHost: process.env.REACT_APP_WS_HOST || 'localhost',  // bukan 0.0.0.0
        wsPort: process.env.REACT_APP_WS_PORT || 8080,
        wssPort: 443,
        forceTLS: false,
        encrypted: false,  // true jika production dengan SSL
        enabledTransports: ['ws', 'wss'],
        auth: authToken ? {
            headers: {
                Authorization: `Bearer ${authToken}`
            }
        } : null
    });
};
```

---

## 🔐 ADMIN WEBSOCKET (Dashboard Admin)

### Fetch Data + WebSocket Setup
```javascript
// components/AdminAuctionDetail.jsx
import { useEffect, useState } from 'react';
import { createEchoInstance } from '../lib/websocket';

function AdminAuctionDetail({ auctionId, adminToken }) {
    const [auction, setAuction] = useState(null);
    const [bidData, setBidData] = useState(null);

    useEffect(() => {
        // 1. Fetch initial data dari ADMIN endpoint
        fetch(`http://127.0.0.1:8000/api/v1/admin/auctions/${auctionId}`, {
            headers: {
                Authorization: `Bearer ${adminToken}`,
                'Content-Type': 'application/json'
            }
        })
            .then(res => res.json())
            .then(data => setAuction(data.data))
            .catch(err => console.error('Fetch error:', err));

        // 2. Setup WebSocket dengan Bearer token
        const echo = createEchoInstance(adminToken);
        const channel = echo.channel(`auction.${auctionId}`);

        channel
            .listen('auction.updated', (data) => {
                console.log('[ADMIN] Auction updated:', data);
                setAuction(prev => ({
                    ...prev,
                    currentBid: data.currentBid,
                    status: data.status,
                    viewCount: data.viewCount
                }));
            })
            .listen('auction.ended', (data) => {
                console.log('[ADMIN] Auction ended:', data);
                setAuction(prev => ({
                    ...prev,
                    status: 'ENDED',
                    winner: data.winner
                }));
            })
            .listen('bid.placed', (data) => {
                console.log('[ADMIN] New bid:', data);
                setBidData(data);
                setAuction(prev => ({
                    ...prev,
                    currentBid: data.currentBid
                }));
            });

        return () => {
            echo.leaveChannel(`auction.${auctionId}`);
        };
    }, [auctionId, adminToken]);

    return (
        <div className="admin-auction">
            <h2>Admin Dashboard - {auction?.title}</h2>
            <div className="pricing">
                <p>Starting Price: Rp {auction?.startingPrice?.toLocaleString()}</p>
                <p>Current Bid: Rp {auction?.currentBid?.toLocaleString()}</p>
                <p>Bid Increment: Rp {auction?.bidIncrement?.toLocaleString()}</p>
            </div>
            <p>Status: <strong>{auction?.status}</strong></p>
            <p>Views: {auction?.viewCount}</p>

            {bidData && (
                <div className="bid-notification">
                    ✓ New bid from {bidData.bidderName}: Rp {bidData.currentBid?.toLocaleString()}
                </div>
            )}

            {auction?.status === 'ENDED' && auction?.winner && (
                <div className="winner-box">
                    <h3>✓ Auction Ended</h3>
                    <p>Winner: {auction.winner.fullName}</p>
                    <p>Winning Bid: Rp {auction.winner.winningBid?.toLocaleString()}</p>
                    <p>Payment Status: {auction.winner.status}</p>
                </div>
            )}
        </div>
    );
}

export default AdminAuctionDetail;
```

---

## 🌐 PORTAL WEBSOCKET (Public/Portal View)

### Fetch Data + WebSocket Setup
```javascript
// components/PortalAuctionDetail.jsx
import { useEffect, useState } from 'react';
import { createEchoInstance } from '../lib/websocket';

function PortalAuctionDetail({ auctionId, invitationCode, userToken = null }) {
    const [auction, setAuction] = useState(null);
    const [bidData, setBidData] = useState(null);

    useEffect(() => {
        // 1. Fetch initial data dari PORTAL endpoint
        let url = `http://127.0.0.1:8000/api/v1/auctions/${auctionId}`;
        const headers = { 'Content-Type': 'application/json' };

        // Gunakan invitation_code ATAU bearer token (bearer token lebih aman)
        if (userToken) {
            headers.Authorization = `Bearer ${userToken}`;
        } else {
            url += `?invitation_code=${invitationCode}`;
        }

        fetch(url, { headers })
            .then(res => res.json())
            .then(data => setAuction(data.data))
            .catch(err => console.error('Fetch error:', err));

        // 2. Setup WebSocket dengan Bearer token
        const echo = createEchoInstance(userToken);
        const channel = echo.channel(`auction.${auctionId}`);

        channel
            .listen('auction.updated', (data) => {
                console.log('[PORTAL] Auction updated:', data);
                setAuction(prev => ({
                    ...prev,
                    currentBid: data.currentBid,
                    status: data.status,
                    viewCount: data.viewCount
                }));
            })
            .listen('auction.ended', (data) => {
                console.log('[PORTAL] Auction ended:', data);
                setAuction(prev => ({
                    ...prev,
                    status: 'ENDED',
                    winner: data.winner
                }));
            })
            .listen('bid.placed', (data) => {
                console.log('[PORTAL] New bid:', data);
                setBidData(data);
                setAuction(prev => ({
                    ...prev,
                    currentBid: data.currentBid
                }));
            });

        return () => {
            echo.leaveChannel(`auction.${auctionId}`);
        };
    }, [auctionId, invitationCode, userToken]);

    return (
        <div className="portal-auction">
            <h2>{auction?.title}</h2>
            <div className="pricing">
                <p>Starting Price: Rp {auction?.startingPrice?.toLocaleString()}</p>
                <p>Current Bid: Rp {auction?.currentBid?.toLocaleString()}</p>
                <p>Bid Increment: Rp {auction?.bidIncrement?.toLocaleString()}</p>
            </div>
            <p>Status: <strong>{auction?.status}</strong></p>
            <p>Participants: {auction?.participantCount}</p>
            <p>Views: {auction?.viewCount}</p>

            {bidData && (
                <div className="bid-alert">
                    💰 New bid from {bidData.bidderName}: Rp {bidData.currentBid?.toLocaleString()}
                </div>
            )}

            {auction?.status === 'ENDED' && auction?.winner && (
                <div className="winner-info">
                    <h3>🎉 Auction Ended</h3>
                    <p>Winner: {auction.winner.fullName}</p>
                    <p>Winning Bid: Rp {auction.winner.winningBid?.toLocaleString()}</p>
                </div>
            )}
        </div>
    );
}

export default PortalAuctionDetail;
```

---

## 📋 Perbandingan Admin vs Portal

| Aspect | Admin | Portal |
|--------|-------|--------|
| **Fetch Endpoint** | `/api/v1/admin/auctions/{id}` | `/api/v1/auctions/{id}` |
| **Auth Method** | Bearer Token (required) | Bearer Token or invitation_code |
| **WebSocket Auth** | Bearer Token | Bearer Token (recommended) |
| **Channel** | `auction.{auctionId}` | `auction.{auctionId}` |
| **Events Received** | auction.updated, auction.ended, bid.placed | auction.updated, auction.ended, bid.placed |
| **Response Fields** | All fields (admin complete data) | Public-safe fields + winner |
| **Can Edit** | Yes (if manage_auctions permission) | No (read-only) |

---

## 🔑 Environment Variables (.env)
```env
REACT_APP_PUSHER_KEY=your_pusher_key
REACT_APP_WS_HOST=localhost
REACT_APP_WS_PORT=8080
```

---

## ✅ Best Practices

1. **Selalu gunakan Bearer Token untuk WebSocket** - lebih aman dan konsisten
2. **Pisahkan admin dan portal components** - logic dan endpoint berbeda
3. **Merge WebSocket data dengan local state** - untuk real-time updates
4. **Unsubscribe saat component unmount** - hindari memory leak
5. **Handle reconnection** - Echo handle otomatis, tapi tambah retry logic untuk fetch data

---

## 🚀 Quick Start

### Admin Dashboard
```javascript
<AdminAuctionDetail 
    auctionId="09074c5e-7345-446d-b0b6-8df4e6833bc9"
    adminToken="your_admin_bearer_token"
/>
```

### Portal View
```javascript
<PortalAuctionDetail 
    auctionId="09074c5e-7345-446d-b0b6-8df4e6833bc9"
    userToken="optional_user_bearer_token"
    invitationCode="PORTAL-DTAOJOIY"
/>
```

Done! Gampang tinggal copy-paste aja.
