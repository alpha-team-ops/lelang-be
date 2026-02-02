<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('winner_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('winner_bid_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->uuid('changed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            // Foreign keys
            $table->foreign('winner_bid_id')->references('id')->on('winner_bids')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('winner_bid_id');
            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('winner_status_history');
    }
};
