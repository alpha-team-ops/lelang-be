<?php

namespace App\Events;

use App\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class BidPlaced implements ShouldBroadcast
{
    use InteractsWithBroadcasting, SerializesModels;

    public $bid;

    public function __construct(Bid $bid)
    {
        $this->bid = $bid;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('auction.' . $this->bid->auction_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'bid.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->bid->id,
            'auctionId' => $this->bid->auction_id,
            'bidderName' => $this->bid->bidder?->name ?? 'Anonymous',
            'bidAmount' => (float) $this->bid->bid_amount,
            'status' => $this->bid->status,
            'timestamp' => $this->bid->bid_timestamp?->toIso8601String(),
        ];
    }
}
