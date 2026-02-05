## Backend WebSocket Broadcasting - Debugging Checklist

Jika FE tidak menerima event apapun, backend team perlu check:

---

## 1. **recordView() Method Trigger Check**

### Location: `app/Http/Controllers/Api/V1/Portal/PortalAuctionController.php`

**Current Implementation:**
```php
public function recordView(string $id): JsonResponse
{
    $auction = Auction::find($id);

    if (!$auction) {
        return response()->json([
            'success' => false,
            'error' => 'Auction not found',
            'code' => 'AUCTION_NOT_FOUND'
        ], 404);
    }

    $auction->increment('view_count');

    // Broadcast view count update via WebSocket
    broadcast(new AuctionUpdated($auction));

    return response()->json([
        'success' => true,
        'data' => [
            'auctionId' => $auction->id,
            'viewCount' => $auction->view_count
        ]
    ]);
}
```

**Debug Steps:**
1. **Add logging** sebelum broadcast:
```php
broadcast(new AuctionUpdated($auction));
\Log::info('Broadcast sent for auction: ' . $auction->id);
```

2. **Test endpoint manually:**
```bash
curl -X POST http://127.0.0.1:8000/api/v1/auctions/{auctionId}/view \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

3. **Check response** - harus return 200 dengan viewCount yang bertambah

---

## 2. **Channel Authorization Check**

### Location: `app/Broadcasting/...` atau `routes/channels.php`

**Issue:** Portal users mungkin tidak authorized untuk subscribe ke channel

**What to Check:**

1. **Apakah ada `routes/channels.php`?**
```bash
ls -la app/Broadcasting/ routes/channels.php
```

2. **Jika tidak ada, buat:**
```php
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('auction.{auctionId}', function ($user, $auctionId) {
    // Public channel - anyone can subscribe
    return true;
});
```

3. **Jika ada, check isi-nya:**
   - Apakah `auction.{auctionId}` channel ada?
   - Apakah return `true` (public) atau ada validasi?
   - Untuk portal, sebaiknya `return true` (public access)

---

## 3. **Broadcast Connection Check**

### Location: `config/broadcasting.php`

**Verify:**
```php
'default' => env('BROADCAST_DRIVER', 'log'),
```

**Should be:**
```php
'default' => env('BROADCAST_DRIVER', 'reverb'),
```

Check di `.env`:
```
BROADCAST_CONNECTION=reverb  # NOT 'log'
```

---

## 4. **Reverb Server Status**

**Check running:**
```bash
ps aux | grep reverb
```

**Should show:**
```
php artisan reverb:start
```

**If not running:**
```bash
php artisan reverb:start
```

---

## 5. **Event Class Check**

### Location: `app/Events/AuctionUpdated.php`

**Must have:**
```php
class AuctionUpdated implements ShouldBroadcast
{
    use InteractsWithBroadcasting, SerializesModels;

    public function broadcastOn(): array
    {
        return [
            new Channel('auction.' . $this->auction->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'auction.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->auction->id,
            'currentBid' => (float) $this->auction->current_bid,
            'status' => $this->auction->getCurrentStatus(),
            'viewCount' => $this->auction->view_count,
        ];
    }
}
```

**Check:**
- ✅ `implements ShouldBroadcast`?
- ✅ `broadcastOn()` return correct channel?
- ✅ `broadcastAs()` return event name?
- ✅ `broadcastWith()` return data?

---

## 6. **BidController - Check bid.placed Event**

### Location: `app/Http/Controllers/Api/V1/BidController.php`

**Should broadcast when placing bid:**
```php
public function place(Request $request): JsonResponse
{
    // ... validation & save bid ...
    
    $auction = Auction::find($request->auction_id);
    broadcast(new BidPlaced($auction, $bid));
    
    return response()->json(['success' => true]);
}
```

**Check:**
- ✅ Is `BidPlaced` event imported?
- ✅ Is broadcast() called after bid created?
- ✅ Does `BidPlaced` event exist?

---

## 7. **AdminAuctionController - Check broadcasts**

### Location: `app/Http/Controllers/Api/V1/Admin/AdminAuctionController.php`

**Should broadcast on update:**
```php
public function update(Request $request, string $id): JsonResponse
{
    $auction = Auction::find($id);
    
    // ... update logic ...
    
    // Broadcast based on status change
    if ($auction->status === 'ENDED') {
        broadcast(new AuctionEnded($auction->fresh()))->toOthers();
    } else {
        broadcast(new AuctionUpdated($auction->fresh()))->toOthers();
    }
    
    return response()->json(['success' => true]);
}
```

**Check:**
- ✅ Events imported (`AuctionEnded`, `AuctionUpdated`)?
- ✅ broadcast() called?
- ✅ Using `->toOthers()` to avoid sending to admin who updated?

---

## 8. **Enable Debug Logging**

Add to `.env`:
```
BROADCAST_LOG=true
LOG_LEVEL=debug
```

Check logs:
```bash
tail -f storage/logs/laravel.log | grep -i broadcast
```

Should show:
```
[2026-02-05 13:40:15] Broadcasting event App\Events\AuctionUpdated on channels ['auction.09074c5e-7345-446d-b0b6-8df4e6833bc9']
```

---

## 9. **Test With Curl + WebSocket Monitor**

**Terminal 1 - Monitor broadcasts:**
```bash
# Watch the logs
tail -f storage/logs/laravel.log | grep broadcast
```

**Terminal 2 - Trigger event:**
```bash
curl -X POST http://127.0.0.1:8000/api/v1/auctions/09074c5e-7345-446d-b0b6-8df4e6833bc9/view \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Expected in Terminal 1:**
```
Broadcasting event App\Events\AuctionUpdated on channel auction.09074c5e-7345-446d-b0b6-8df4e6833bc9
```

---

## 10. **Common Issues**

| Issue | Solution |
|-------|----------|
| No broadcasts at all | Check `BROADCAST_CONNECTION=reverb` in `.env` |
| Reverb not listening | Run `php artisan reverb:start` |
| Portal users can't subscribe | Check `routes/channels.php` - should return true for auction channels |
| Events fire but FE doesn't receive | Check channel name matches: `auction.{id}` |
| Only admin sees updates | Check `.toOthers()` - don't exclude portal users |

---

## Summary: Checklist for Backend Team

- [ ] Verify `.env` has `BROADCAST_CONNECTION=reverb`
- [ ] Verify Reverb server running: `php artisan reverb:start`
- [ ] Verify `recordView()` in PortalAuctionController broadcasts
- [ ] Verify `routes/channels.php` exists and allows `auction.*` channels
- [ ] Verify `AuctionUpdated`, `BidPlaced`, `AuctionEnded` events exist
- [ ] Verify AdminAuctionController broadcasts on update
- [ ] Verify BidController broadcasts on place
- [ ] Enable debug logging and check logs
- [ ] Test manually with curl
- [ ] Confirm FE receives events on `auction.{id}` channel

If all above checked and still no events → Backend issue confirmed.
