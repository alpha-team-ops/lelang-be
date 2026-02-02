<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('auction_id')->comment('Foreign key to auctions table');
            $table->uuid('bidder_id')->comment('Foreign key to users table (portal user)');
            $table->decimal('bid_amount', 15, 2);
            $table->enum('status', ['CURRENT', 'OUTBID', 'WINNING'])->default('CURRENT');
            $table->dateTime('bid_timestamp')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamps();

            // Foreign keys
            $table->foreign('auction_id')
                ->references('id')
                ->on('auctions')
                ->onDelete('cascade');

            $table->foreign('bidder_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Indexes
            $table->index('auction_id', 'idx_auction_id');
            $table->index('bidder_id', 'idx_bidder_id');
            $table->index('bid_timestamp', 'idx_bid_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
