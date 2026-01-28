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
        Schema::create('auctions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('organization_code', 50);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('condition', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('item_location', 100)->nullable();
            $table->integer('purchase_year')->nullable();
            $table->decimal('starting_price', 15, 2);
            $table->decimal('reserve_price', 15, 2);
            $table->decimal('bid_increment', 15, 2);
            $table->decimal('current_bid', 15, 2)->nullable();
            $table->integer('total_bids')->default(0);
            $table->enum('status', ['DRAFT', 'SCHEDULED', 'LIVE', 'ENDING', 'ENDED', 'CANCELLED'])->default('DRAFT');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('seller', 100)->nullable();
            $table->string('current_bidder', 100)->nullable();
            $table->string('image', 255)->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('participant_count')->default(0);
            $table->timestamps();

            $table->foreign('organization_code')->references('code')->on('organizations');
            $table->unique(['organization_code', 'serial_number']);
            $table->index('organization_code');
            $table->index('status');
            $table->index('category');
            $table->index('end_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
