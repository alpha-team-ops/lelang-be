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
     * Get all bids for this auction
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'auction_id')->orderBy('bid_timestamp', 'desc');
    }

    /**
     * Get the current bidder (user with highest bid)
     */
    public function currentBidderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_bidder', 'id');
    }

    /**
     * Get the current highest bid
     */
    public function currentHighestBid()
    {
        return $this->bids()->where('status', '!=', 'OUTBID')->first();
    }

    /**
     * Calculate auction status based on current datetime
     * DRAFT: No dates set OR before start_time
     * LIVE: Between start_time and end_time
     * ENDED: After end_time
     */
    public function calculateStatus(): string
    {
        // If no start_time or end_time, always DRAFT
        if (!$this->start_time || !$this->end_time) {
            return 'DRAFT';
        }
        
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
     * Get bidder name safely (handles invalid UUID in current_bidder field)
     */
    public function getBidderName(): ?string
    {
        // If no current_bidder, return null
        if (!$this->current_bidder) {
            return null;
        }
        
        // Check if current_bidder is valid UUID format (8-4-4-4-12)
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $this->current_bidder)) {
            // If not UUID, return as-is (fallback to display the value)
            return $this->current_bidder;
        }
        
        // If valid UUID, try to get user name
        return $this->currentBidderUser?->name ?? null;
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
            'startTime' => $this->start_time?->toIso8601String(),
            'endTime' => $this->end_time?->toIso8601String(),
            'seller' => $this->seller,
            'currentBidder' => $this->getBidderName(),
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
            'bidIncrement' => (float) $this->bid_increment,
            'status' => $this->getCurrentStatus(),
            'endTime' => $this->end_time?->toIso8601String(),
            'participantCount' => $this->participant_count,
            'viewCount' => $this->view_count,
            'totalBids' => $this->total_bids,
            'currentBidder' => $this->getBidderName(),
            'images' => $this->images->pluck('image_url')->toArray(),
        ];
    }
}
