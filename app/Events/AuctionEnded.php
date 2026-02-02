<?php

namespace App\Events;

use App\Models\Auction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class AuctionEnded implements ShouldBroadcast
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
        return 'auction.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->auction->id,
            'title' => $this->auction->title,
            'status' => 'ENDED',
            'winningBid' => (float) $this->auction->current_bid,
            'winner' => $this->auction->currentBidderUser?->name,
            'participantCount' => $this->auction->participant_count,
            'endedAt' => now()->toIso8601String(),
        ];
    }
}
