<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bid extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bids';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'auction_id',
        'bidder_id',
        'bid_amount',
        'status',
        'bid_timestamp',
    ];

    protected $casts = [
        'bid_amount' => 'decimal:2',
        'bid_timestamp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the auction this bid belongs to
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class, 'auction_id');
    }

    /**
     * Get the bidder (portal user) who placed this bid
     */
    public function bidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bidder_id');
    }

    /**
     * Get notifications for this bid
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(BidNotification::class, 'bid_id');
    }

    /**
     * Format bid response for API
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'auctionId' => $this->auction_id,
            'auctionTitle' => $this->auction?->title,
            'bidder' => $this->bidder?->name,
            'bidAmount' => (float) $this->bid_amount,
            'timestamp' => $this->bid_timestamp?->toIso8601String(),
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format bid for activity list response
     */
    public function toActivityArray(): array
    {
        return [
            'id' => $this->id,
            'auctionId' => $this->auction_id,
            'auctionTitle' => $this->auction?->title ?? 'Unknown Auction',
            'bidderId' => $this->bidder_id,
            'bidderName' => $this->bidder?->name ?? 'Anonymous',
            'bidAmount' => (float) $this->bid_amount,
            'status' => $this->status,
            'timestamp' => $this->bid_timestamp?->toIso8601String(),
        ];
    }
}
