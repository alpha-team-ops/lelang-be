<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuctionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orgCode = 'ORG-ALPHACORP-001';

        // Create 5 sample auctions with different statuses
        $auctions = [
            [
                'id' => Str::uuid()->toString(),
                'organization_code' => $orgCode,
                'title' => 'Laptop ASUS ROG Gaming',
                'description' => 'High-performance gaming laptop with RTX 4060 graphics card. Excellent condition, minimal usage.',
                'category' => 'Elektronik',
                'condition' => 'Bekas - Sangat Baik',
                'serial_number' => 'ASU-ROG-2024-001',
                'item_location' => 'Jakarta',
                'purchase_year' => 2023,
                'starting_price' => 7500000,
                'reserve_price' => 8500000,
                'bid_increment' => 250000,
                'current_bid' => 8500000,
                'total_bids' => 12,
                'status' => 'LIVE',
                'start_time' => Carbon::now()->subDay(),
                'end_time' => Carbon::now()->addDays(5),
                'seller' => 'Admin',
                'current_bidder' => 'Pembeli_123',
                'image' => '💻',
                'view_count' => 342,
                'participant_count' => 12,
            ],
            [
                'id' => Str::uuid()->toString(),
                'organization_code' => $orgCode,
                'title' => 'Smartphone iPhone 14 Pro Max',
                'description' => 'Original iPhone 14 Pro Max 256GB. Includes original box, charger, and accessories. Screen protector applied.',
                'category' => 'Elektronik',
                'condition' => 'Bekas - Baik',
                'serial_number' => 'IPH-14PM-2024-001',
                'item_location' => 'Surabaya',
                'purchase_year' => 2023,
                'starting_price' => 8000000,
                'reserve_price' => 9000000,
                'bid_increment' => 300000,
                'current_bid' => 9500000,
                'total_bids' => 18,
                'status' => 'LIVE',
                'start_time' => Carbon::now()->subHours(6),
                'end_time' => Carbon::now()->addHours(18),
                'seller' => 'Admin',
                'current_bidder' => 'Pembeli_456',
                'image' => '📱',
                'view_count' => 567,
                'participant_count' => 18,
            ],
            [
                'id' => Str::uuid()->toString(),
                'organization_code' => $orgCode,
                'title' => 'Kamera Canon EOS R5',
                'description' => 'Professional mirrorless camera body. Full frame sensor. Excellent for photography and videography.',
                'category' => 'Peralatan Fotografi',
                'condition' => 'Bekas - Sangat Baik',
                'serial_number' => 'CAN-R5-2024-001',
                'item_location' => 'Bandung',
                'purchase_year' => 2022,
                'starting_price' => 15000000,
                'reserve_price' => 18000000,
                'bid_increment' => 500000,
                'current_bid' => 18500000,
                'total_bids' => 8,
                'status' => 'ENDING',
                'start_time' => Carbon::now()->subDays(4),
                'end_time' => Carbon::now()->addHours(2),
                'seller' => 'Admin',
                'current_bidder' => 'Pembeli_789',
                'image' => '📷',
                'view_count' => 234,
                'participant_count' => 8,
            ],
            [
                'id' => Str::uuid()->toString(),
                'organization_code' => $orgCode,
                'title' => 'Sofa Kulit Premium',
                'description' => 'Beautiful leather sofa in excellent condition. Comfortable and stylish for any living room.',
                'category' => 'Furniture',
                'condition' => 'Bekas - Sangat Baik',
                'serial_number' => 'SOF-KLT-2024-001',
                'item_location' => 'Medan',
                'purchase_year' => 2022,
                'starting_price' => 3000000,
                'reserve_price' => 4000000,
                'bid_increment' => 100000,
                'current_bid' => 3000000,
                'total_bids' => 0,
                'status' => 'DRAFT',
                'start_time' => Carbon::now()->addDay(),
                'end_time' => Carbon::now()->addDays(6),
                'seller' => 'Admin',
                'current_bidder' => null,
                'image' => '🛋️',
                'view_count' => 0,
                'participant_count' => 0,
            ],
            [
                'id' => Str::uuid()->toString(),
                'organization_code' => $orgCode,
                'title' => 'Mobil Honda Civic Turbo',
                'description' => 'Honda Civic 2020, Turbo version, Low mileage. Service record complete. Accident-free.',
                'category' => 'Otomotif',
                'condition' => 'Bekas - Sangat Baik',
                'serial_number' => 'HON-CIV-2024-001',
                'item_location' => 'Jakarta',
                'purchase_year' => 2020,
                'starting_price' => 200000000,
                'reserve_price' => 230000000,
                'bid_increment' => 5000000,
                'current_bid' => 200000000,
                'total_bids' => 0,
                'status' => 'SCHEDULED',
                'start_time' => Carbon::now()->addDays(2),
                'end_time' => Carbon::now()->addDays(7),
                'seller' => 'Admin',
                'current_bidder' => null,
                'image' => '🚗',
                'view_count' => 0,
                'participant_count' => 0,
            ],
        ];

        // Insert auctions
        foreach ($auctions as $auction) {
            DB::table('auctions')->insert([
                'id' => $auction['id'],
                'organization_code' => $auction['organization_code'],
                'title' => $auction['title'],
                'description' => $auction['description'],
                'category' => $auction['category'],
                'condition' => $auction['condition'],
                'serial_number' => $auction['serial_number'],
                'item_location' => $auction['item_location'],
                'purchase_year' => $auction['purchase_year'],
                'starting_price' => $auction['starting_price'],
                'reserve_price' => $auction['reserve_price'],
                'bid_increment' => $auction['bid_increment'],
                'current_bid' => $auction['current_bid'],
                'total_bids' => $auction['total_bids'],
                'status' => $auction['status'],
                'start_time' => $auction['start_time'],
                'end_time' => $auction['end_time'],
                'seller' => $auction['seller'],
                'current_bidder' => $auction['current_bidder'],
                'image' => $auction['image'],
                'view_count' => $auction['view_count'],
                'participant_count' => $auction['participant_count'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add sample images for LIVE and ENDING auctions
            if (in_array($auction['status'], ['LIVE', 'ENDING'])) {
                DB::table('auction_images')->insert([
                    'id' => Str::uuid()->toString(),
                    'auction_id' => $auction['id'],
                    'image_url' => 'https://via.placeholder.com/400x300?text=Item+Image+1',
                    'order_num' => 1,
                    'created_at' => now(),
                ]);

                DB::table('auction_images')->insert([
                    'id' => Str::uuid()->toString(),
                    'auction_id' => $auction['id'],
                    'image_url' => 'https://via.placeholder.com/400x300?text=Item+Image+2',
                    'order_num' => 2,
                    'created_at' => now(),
                ]);
            }
        }

        echo "✓ Auctions seeded successfully\n";
        echo "  - 5 sample auctions created\n";
        echo "  - 3 LIVE auctions with images\n";
        echo "  - 1 ENDING auction with images\n";
        echo "  - 1 DRAFT auction\n";
        echo "  - 1 SCHEDULED auction\n";
    }
}
