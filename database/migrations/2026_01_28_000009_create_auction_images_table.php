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
        Schema::create('auction_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('auction_id');
            $table->string('image_url', 255);
            $table->integer('order_num')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('auction_id')->references('id')->on('auctions')->onDelete('cascade');
            $table->index('auction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auction_images');
    }
};
