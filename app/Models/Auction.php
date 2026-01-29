<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auction extends Model
{
    use HasFactory;

    protected $table = 'auctions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'organization_code',
        'title',
        'description',
        'category',
        'condition',
        'serial_number',
        'item_location',
        'purchase_year',
        'starting_price',
        'reserve_price',
        'bid_increment',
        'current_bid',
        'total_bids',
        'status',
        'start_time',
        'end_time',
        'seller',
        'current_bidder',
        'image',
        'view_count',
        'participant_count',
    ];

    protected $casts = [
        'starting_price' => 'decimal:2',
        'reserve_price' => 'decimal:2',
        'bid_increment' => 'decimal:2',
        'current_bid' => 'decimal:2',
        'total_bids' => 'integer',
        'purchase_year' => 'integer',
        'view_count' => 'integer',
        'participant_count' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the organization that owns this auction
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_code', 'code');
    }

    /**
     * Get the images for this auction
     */
    public function images(): HasMany
    {
        return $this->hasMany(AuctionImage::class, 'auction_id');
    }

    /**
     * Calculate auction status based on current datetime
     * DRAFT: Before start_time
     * LIVE: Between start_time and end_time
     * ENDED: After end_time
     */
    public function calculateStatus(): string
    {
        $now = now();
        
        if ($now < $this->start_time) {
            return 'DRAFT';
        } elseif ($now <= $this->end_time) {
            return 'LIVE';
        } else {
            return 'ENDED';
        }
    }

    /**
     * Get the current status (calculated from datetime)
     */
    public function getCurrentStatus(): string
    {
        return $this->calculateStatus();
    }

    /**
     * Get formatted response for admin view
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'condition' => $this->condition,
            'serialNumber' => $this->serial_number,
            'itemLocation' => $this->item_location,
            'purchaseYear' => $this->purchase_year,
            'startingPrice' => (float) $this->starting_price,
            'reservePrice' => (float) $this->reserve_price,
            'bidIncrement' => (float) $this->bid_increment,
            'currentBid' => (float) $this->current_bid,
            'totalBids' => $this->total_bids,
            'status' => $this->getCurrentStatus(),
            'startTime' => $this->start_time->toIso8601String(),
            'endTime' => $this->end_time->toIso8601String(),
            'seller' => $this->seller,
            'currentBidder' => $this->current_bidder,
            'image' => $this->image,
            'images' => $this->images->pluck('image_url')->toArray(),
            'viewCount' => $this->view_count,
            'participantCount' => $this->participant_count,
            'organizationCode' => $this->organization_code,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Get formatted response for portal view (public)
     */
    public function toPortalArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'condition' => $this->condition,
            'currentBid' => (float) $this->current_bid,
            'reservePrice' => (float) $this->reserve_price,
            'status' => $this->getCurrentStatus(),
            'endTime' => $this->end_time->toIso8601String(),
            'participantCount' => $this->participant_count,
            'images' => $this->images->pluck('image_url')->toArray(),
            'organizationCode' => $this->organization_code,
        ];
    }
}
