<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BidSeeder extends Seeder
{
    public function run(): void
    {
        $orgCode = 'ORG-DERALY-001';

        // Get all users from the organization to use as bidders
        $bidders = User::where('organization_code', $orgCode)->get();
        
        if ($bidders->isEmpty()) {
            echo "⚠ No bidders found. Creating test bidders...\n";
            // Create some test bidders
            for ($j = 1; $j <= 5; $j++) {
                User::create([
                    'id' => (string) Str::uuid(),
                    'name' => "Test Bidder $j",
                    'email' => "bidder$j@deraly.id",
                    'password_hash' => bcrypt('password'),
                    'role' => 'USER',
                    'status' => 'ACTIVE',
                    'organization_code' => $orgCode,
                    'corporate_id_nip' => "NIP-" . str_pad($j, 5, '0', STR_PAD_LEFT),
                    'directorate' => 'Test Directorate',
                    'email_verified' => true,
                ]);
            }
            $bidders = User::where('organization_code', $orgCode)->get();
        }

        $bidderIds = $bidders->pluck('id')->toArray();

        // Get ENDED auctions
        $endedAuctions = DB::table('auctions')
            ->where('status', 'ENDED')
            ->where('organization_code', $orgCode)
            ->get();

        foreach ($endedAuctions as $auction) {
            $bidCount = rand(5, 10);
            $baseAmount = $auction->starting_price;

            for ($i = 1; $i <= $bidCount; $i++) {
                $bidAmount = $baseAmount + ($auction->bid_increment * $i);
                $isWinning = ($i === $bidCount);
                $bidStatus = $isWinning ? 'CURRENT' : 'OUTBID';
                $bidderId = $bidderIds[array_rand($bidderIds)];

                DB::table('bids')->insert([
                    'id' => Str::uuid()->toString(),
                    'auction_id' => $auction->id,
                    'bidder_id' => $bidderId,
                    'bid_amount' => $bidAmount,
                    'bid_timestamp' => Carbon::now()->subMinutes($bidCount - $i),
                    'status' => $bidStatus,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            echo "✓ Added $bidCount bids to ended auction: {$auction->title}\n";
        }

        // Add sample bids for LIVE auctions
        $liveAuctions = DB::table('auctions')
            ->where('status', 'LIVE')
            ->where('organization_code', $orgCode)
            ->limit(2)
            ->get();

        foreach ($liveAuctions as $auction) {
            $bidCount = rand(3, 8);
            $baseAmount = $auction->starting_price;

            for ($i = 1; $i <= $bidCount; $i++) {
                $bidAmount = $baseAmount + ($auction->bid_increment * $i);
                $isWinning = ($i === $bidCount);
                $bidStatus = $isWinning ? 'CURRENT' : 'OUTBID';
                $bidderId = $bidderIds[array_rand($bidderIds)];

                DB::table('bids')->insert([
                    'id' => Str::uuid()->toString(),
                    'auction_id' => $auction->id,
                    'bidder_id' => $bidderId,
                    'bid_amount' => $bidAmount,
                    'bid_timestamp' => Carbon::now()->subMinutes($bidCount - $i),
                    'status' => $bidStatus,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            echo "✓ Added $bidCount bids to LIVE auction: {$auction->title}\n";
        }

        echo "\n✓ Bids seeded successfully\n";
    }
}

