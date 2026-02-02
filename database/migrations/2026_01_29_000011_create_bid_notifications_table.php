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
        Schema::create('bid_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bid_id')->comment('Foreign key to bids table');
            $table->uuid('user_id')->comment('Foreign key to users table (recipient)');
            $table->string('notification_type')->nullable()->comment('OUTBID, SELLER_NEW_BID, WINNING');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('bid_id')
                ->references('id')
                ->on('bids')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Indexes
            $table->index('user_id', 'idx_user_id');
            $table->index('bid_id', 'idx_bid_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bid_notifications');
    }
};
