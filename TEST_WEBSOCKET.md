# 🧪 WebSocket Testing Guide

## Setup & Run

### Terminal 1: Start Reverb WebSocket Server
```bash
cd /home/FP8930/Developments/lelang-be-dev
php artisan reverb:start
```

Expected output:
```
INFO  Starting server on 0.0.0.0:8080 (localhost).
```

### Terminal 2: Start Laravel API Server
```bash
cd /home/FP8930/Developments/lelang-be-dev
php artisan serve --port=8000
```

Expected output:
```
Starting Laravel development server: http://127.0.0.1:8000
```

### Terminal 3: Run Tests

---

## Test 1: Check Servers Running

```bash
# Check Reverb on port 8080
curl -i http://localhost:8080

# Check Laravel on port 8000
curl -i http://localhost:8000
```

---

## Test 2: Create Test Database & Data

```bash
# Migrate database (if not done yet)
php artisan migrate:fresh --seed

# Or seed only
php artisan db:seed
```

Check auctions exist:
```bash
php artisan tinker
>>> Auction::count();
>>> Auction::first();
```

---

## Test 3: Verify Broadcasting Events Code

```bash
# Check Events exist
ls -la app/Events/

# Expected:
# - BidPlaced.php ✓
# - AuctionUpdated.php ✓
# - AuctionEnded.php ✓

# Check if Events are imported in BidController
grep -n "use App\Events" app/Http/Controllers/Api/V1/BidController.php

# Check broadcasting in place() method
grep -n "broadcast(new" app/Http/Controllers/Api/V1/BidController.php
```

---

## Test 4: WebSocket Client Test (HTML)

Save as `websocket-test.html`:

```html
<!DOCTYPE html>
<html>
<head>
    <title>WebSocket Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        #log { border: 1px solid #ccc; padding: 10px; height: 400px; overflow-y: auto; background: #f5f5f5; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        button { padding: 10px 20px; margin: 10px 5px 0 0; }
    </style>
</head>
<body>
    <h1>WebSocket Real-time Test</h1>
    
    <div>
        <button onclick="testConnection()">1. Test Connection</button>
        <button onclick="subscribeToChannel()">2. Subscribe to Auction</button>
        <button onclick="testBidPlacement()">3. Place Test Bid</button>
        <button onclick="clearLog()">Clear Log</button>
    </div>

    <h3>Events Log:</h3>
    <div id="log"></div>

    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.x/dist/echo.iife.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.x/dist/web/pusher.min.js"></script>

    <script>
        const log = document.getElementById('log');
        let echo = null;
        let auctionId = null;

        function addLog(msg, type = 'info') {
            const time = new Date().toLocaleTimeString();
            const className = type === 'success' ? 'success' : type === 'error' ? 'error' : 'info';
            const html = `<p><span class="${className}">[${time}] ${msg}</span></p>`;
            log.innerHTML += html;
            log.scrollTop = log.scrollHeight;
        }

        function clearLog() {
            log.innerHTML = '';
            addLog('Log cleared');
        }

        function testConnection() {
            addLog('Initializing Echo client...');
            
            try {
                echo = new Echo({
                    broadcaster: 'reverb',
                    key: '4l015glwhsub2cclqsxd',
                    wsHost: 'localhost',
                    wsPort: 8080,
                    wssPort: 8080,
                    forceTLS: false,
                    enabledTransports: ['ws', 'wss'],
                });

                addLog('✅ Echo initialized successfully!', 'success');
                addLog('Ready to subscribe to channels');
            } catch (error) {
                addLog(`❌ Connection failed: ${error.message}`, 'error');
            }
        }

        function subscribeToChannel() {
            if (!echo) {
                addLog('❌ Echo not initialized. Click "Test Connection" first.', 'error');
                return;
            }

            // Use auction ID 1 (adjust if needed)
            auctionId = '550e8400-e29b-41d4-a716-446655440001'; // Replace with real UUID
            addLog(`Subscribing to auction.${auctionId}...`);

            try {
                echo.channel(`auction.${auctionId}`)
                    .listen('bid.placed', (data) => {
                        addLog(`🎯 BID PLACED: Bidder=${data.bidder_name}, Amount=${data.bid_amount}`, 'success');
                    })
                    .listen('auction.updated', (data) => {
                        addLog(`📊 AUCTION UPDATED: Current Bid=${data.current_bid}, Participants=${data.participant_count}`, 'success');
                    })
                    .listen('auction.ended', (data) => {
                        addLog(`🏁 AUCTION ENDED: Winner=${data.winner_name}, Final Bid=${data.final_bid}`, 'success');
                    });

                addLog(`✅ Subscribed to auction.${auctionId}`, 'success');
                addLog('Waiting for real-time events...');
            } catch (error) {
                addLog(`❌ Subscription failed: ${error.message}`, 'error');
            }
        }

        function testBidPlacement() {
            if (!auctionId) {
                addLog('❌ First subscribe to a channel!', 'error');
                return;
            }

            addLog('Placing test bid via API...');
            
            fetch('http://localhost:8000/api/v1/bids/place', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer YOUR_AUTH_TOKEN_HERE'
                },
                body: JSON.stringify({
                    auctionId: auctionId,
                    bidAmount: 150000
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    addLog(`✅ Bid placed successfully!`, 'success');
                    addLog(`📍 Check WebSocket events above (real-time events should appear)`, 'info');
                } else {
                    addLog(`❌ Bid failed: ${data.error}`, 'error');
                }
            })
            .catch(e => {
                addLog(`❌ API error: ${e.message}`, 'error');
            });
        }

        // Auto-run test
        document.addEventListener('DOMContentLoaded', () => {
            addLog('WebSocket Test Client Ready');
            addLog('Follow steps: 1 → 2 → 3');
        });
    </script>
</body>
</html>
```

Open in browser: `file:///home/FP8930/Developments/lelang-be-dev/websocket-test.html`

---

## Test 5: Check Broadcasting Configuration

```bash
# Check if BROADCAST_CONNECTION is set to reverb
grep BROADCAST_CONNECTION .env

# Check REVERB config
grep REVERB .env

# Check if reverb config exists
ls -la config/reverb.php
```

---

## Expected Results

✅ **All Tests Passed When:**
1. Reverb server starts without errors on port 8080
2. Laravel server starts without errors on port 8000
3. WebSocket client connects successfully
4. Channel subscription succeeds
5. When bid is placed → real-time events appear instantly!

---

## Troubleshooting

### Reverb won't start
```bash
# Check if port 8080 is free
lsof -i :8080

# Kill process if needed
kill -9 <PID>

# Reinstall Reverb
php artisan reverb:install
```

### Events not broadcasting
```bash
# Check Events file syntax
php artisan tinker
>>> include 'app/Events/BidPlaced.php';

# Check BidController has imports
grep -n "use App\\Events" app/Http/Controllers/Api/V1/BidController.php
```

### WebSocket connection refused
```bash
# Verify Reverb is listening
netstat -tuln | grep 8080

# Or using ss
ss -tuln | grep 8080
```

---

**Status:** ✅ Ready for testing!
