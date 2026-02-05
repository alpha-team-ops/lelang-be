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
        $orgCode = 'ORG-DERALY-001';

        // Create 8 sample auctions with different statuses including ENDED auctions
        $auctions = [
            // ========== ENDED AUCTIONS (untuk testing winner bid) ==========
            [
                'id' => Str::uuid()->toString(),
                'organization_code' => $orgCode,
                'title' => 'MacBook Pro 16" M3 Max',
                'description' => 'MacBook Pro 16-inch with M3 Max chip. 36GB unified memory. 1TB SSD. Like new condition.',
                'category' => 'Elektronik',
                'condition' => 'Bekas - Sangat Baik',
                'serial_number' => 'MAC-PRO-2024-M3',
                'item_location' => 'Jakarta',
                'purchase_year' => 2024,
                'starting_price' => 20000000,
                'reserve_price' => 25000000,
                'bid_increment' => 500000,
                'current_bid' => 28000000,
                'total_bids' => 15,
                'status' => 'ENDED',
                'start_time' => Carbon::now()->subDays(10),
                'end_time' => Carbon::now()->subDays(3),  // Ended 3 days ago
                'seller' => 'Admin',
                'current_bidder' => 'Pembeli_789',
                'image' => '💻',
                'view_count' => 524,
                'participant_count' => 15,
            ],
            [
                'id' => Str::uuid()->toString(),
                'organization_code' => $orgCode,
                'title' => 'Apple Watch Series 9 Ultra',
                'description' => 'Apple Watch Series 9 Ultra Titanium. 49mm display. All sports bands included. Perfect condition.',
                'category' => 'Elektronik',
                'condition' => 'Bekas - Sangat Baik',
                'serial_number' => 'AW-ULTRA-2024-001',
                'item_location' => 'Surabaya',
                'purchase_year' => 2024,
                'starting_price' => 5000000,
                'reserve_price' => 6500000,
                'bid_increment' => 150000,
                'current_bid' => 7200000,
                'total_bids' => 22,
                'status' => 'ENDED',
                'start_time' => Carbon::now()->subDays(7),
                'end_time' => Carbon::now()->subDays(2),  // Ended 2 days ago
                'seller' => 'Admin',
                'current_bidder' => 'Pembeli_321',
                'image' => '⌚',
                'view_count' => 456,
                'participant_count' => 22,
            ],
            [
                'id' => Str::uuid()->toString(),
                'organization_code' => $orgCode,
                'title' => 'iPad Pro 12.9" M2',
                'description' => 'iPad Pro 12.9-inch with M2 chip. 256GB storage. WiFi+Cellular. Includes Apple Pencil and keyboard case.',
                'category' => 'Elektronik',
                'condition' => 'Bekas - Baik',
                'serial_number' => 'IPD-PRO-2024-M2',
                'item_location' => 'Bandung',
                'purchase_year' => 2023,
                'starting_price' => 12000000,
                'reserve_price' => 14000000,
                'bid_increment' => 350000,
                'current_bid' => 15500000,
                'total_bids' => 10,
                'status' => 'ENDED',
                'start_time' => Carbon::now()->subDays(5),
                'end_time' => Carbon::now()->subDays(1),  // Ended 1 day ago
                'seller' => 'Admin',
                'current_bidder' => 'Pembeli_654',
                'image' => '📱',
                'view_count' => 312,
                'participant_count' => 10,
            ],
            // ========== LIVE AUCTIONS (existing) ==========
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

            // Add sample images for LIVE, ENDING, and ENDED auctions
            if (in_array($auction['status'], ['LIVE', 'ENDING', 'ENDED'])) {
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
        echo "  - 8 sample auctions created\n";
        echo "  - 3 ENDED auctions (ready for winner bid testing)\n";
        echo "  - 3 LIVE auctions with images\n";
        echo "  - 1 DRAFT auction\n";
        echo "  - 1 SCHEDULED auction\n";
    }
}
