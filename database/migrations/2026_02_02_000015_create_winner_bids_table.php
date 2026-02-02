<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('winner_bids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('auction_id');
            $table->string('auction_title');
            $table->string('serial_number')->nullable();
            $table->string('category')->nullable();
            $table->uuid('bidder_id');
            $table->string('full_name');
            $table->string('corporate_id_nip')->nullable();
            $table->string('directorate')->nullable();
            $table->string('organization_code');
            $table->decimal('winning_bid', 15, 2);
            $table->integer('total_participants')->nullable();
            $table->dateTime('auction_end_time')->nullable();
            $table->enum('status', ['PAYMENT_PENDING', 'PAID', 'SHIPPED', 'COMPLETED', 'CANCELLED'])
                  ->default('PAYMENT_PENDING');
            $table->dateTime('payment_due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('auction_id')->references('id')->on('auctions')->onDelete('cascade');
            $table->foreign('bidder_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('organization_code')->references('code')->on('organizations')->onDelete('cascade');

            // Indexes
            $table->index('status');
            $table->index('auction_id');
            $table->index('bidder_id');
            $table->index('organization_code');
            $table->index('payment_due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('winner_bids');
    }
};
