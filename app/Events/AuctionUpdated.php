<?php

namespace App\Events;

use App\Models\Auction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class AuctionUpdated implements ShouldBroadcast
{
    use InteractsWithBroadcasting, SerializesModels;

    public $auction;

    public function __construct(Auction $auction)
    {
        $this->auction = $auction;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('auction.' . $this->auction->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'auction.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->auction->id,
            'title' => $this->auction->title,
            'currentBid' => (float) $this->auction->current_bid,
            'participantCount' => $this->auction->participant_count,
            'status' => $this->auction->getCurrentStatus(),
            'endTime' => $this->auction->end_time?->toIso8601String(),
        ];
    }
}
