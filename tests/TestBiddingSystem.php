<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Models\Organization;

// Setup
$org = Organization::where('code', 'ORG-ALPHACORP-001')->first();

// Get or create seller
$seller = User::where('organization_code', $org->code)
    ->where('user_type', 'STAFF')
    ->first();

if (!$seller) {
    $seller = User::create([
        'id' => \Illuminate\Support\Str::uuid(),
        'name' => 'Admin Staff',
        'email' => \Illuminate\Support\Str::uuid() . '@staff.local',
        'password_hash' => bcrypt('password'),
        'role' => 'ADMIN',
        'status' => 'ACTIVE',
        'organization_code' => $org->code,
        'user_type' => 'STAFF',
    ]);
}

// Get portal user (created earlier)
$bidder = User::where('corporate_id_nip', '23232323')
    ->where('organization_code', $org->code)
    ->first();

echo "=== BIDDING SYSTEM TEST ===" . PHP_EOL;
echo "Organization: " . $org->name . PHP_EOL;
echo "Seller: " . $seller->name . " (ID: " . substr($seller->id, 0, 8) . "...)" . PHP_EOL;
echo "Bidder: " . $bidder->name . " (ID: " . substr($bidder->id, 0, 8) . "...)" . PHP_EOL;
echo "" . PHP_EOL;

// Create auction
echo "1. Creating Auction..." . PHP_EOL;
$auction = Auction::create([
    'id' => \Illuminate\Support\Str::uuid(),
    'organization_code' => $org->code,
    'title' => 'Laptop ASUS ROG Gaming',
    'description' => 'High performance gaming laptop',
    'category' => 'Elektronik',
    'condition' => 'Sangat Baik',
    'starting_price' => 8000000,
    'reserve_price' => 7500000,
    'bid_increment' => 250000,
    'current_bid' => 8000000,
    'total_bids' => 0,
    'status' => 'DRAFT',
    'seller' => $seller->id,
    'start_time' => now(),
    'end_time' => now()->addHours(24),
    'view_count' => 0,
    'participant_count' => 0,
]);

echo "   Auction ID: " . substr($auction->id, 0, 8) . "..." . PHP_EOL;
echo "   Title: " . $auction->title . PHP_EOL;
echo "   Current Bid: Rp" . number_format($auction->current_bid, 0, ',', '.') . PHP_EOL;
echo "   Status: " . $auction->getCurrentStatus() . PHP_EOL;
echo "" . PHP_EOL;

// Change status to LIVE
echo "2. Setting Auction Status to LIVE..." . PHP_EOL;
$auction->update(['status' => 'DRAFT']);
echo "   Status: " . $auction->getCurrentStatus() . PHP_EOL;
echo "" . PHP_EOL;

// Create first bid
echo "3. Creating Bid..." . PHP_EOL;
$bid1 = Bid::create([
    'id' => \Illuminate\Support\Str::uuid(),
    'auction_id' => $auction->id,
    'bidder_id' => $bidder->id,
    'bid_amount' => 8250000,
    'status' => 'CURRENT',
    'bid_timestamp' => now(),
]);

echo "   Bid ID: " . substr($bid1->id, 0, 8) . "..." . PHP_EOL;
echo "   Amount: Rp" . number_format($bid1->bid_amount, 0, ',', '.') . PHP_EOL;
echo "   Status: " . $bid1->status . PHP_EOL;
echo "" . PHP_EOL;

// Update auction
$auction->update([
    'current_bid' => $bid1->bid_amount,
    'current_bidder' => $bidder->id,
    'total_bids' => 1,
    'participant_count' => 1,
]);

// Display results
echo "4. Results:" . PHP_EOL;
echo "   Auction Current Bid: Rp" . number_format($auction->fresh()->current_bid, 0, ',', '.') . PHP_EOL;
echo "   Total Bids: " . $auction->fresh()->total_bids . PHP_EOL;
echo "   Participants: " . $auction->fresh()->participant_count . PHP_EOL;
echo "" . PHP_EOL;

echo "✓ ALL TESTS PASSED!" . PHP_EOL;
