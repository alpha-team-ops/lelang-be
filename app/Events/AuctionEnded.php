<?php

namespace App\Events;

use App\Models\Auction;
use App\Models\WinnerBid;
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
        // Get winner bid jika sudah auto-created
        $winner = $this->auction->winnerBid()->first();
        
        return [
            'id' => $this->auction->id,
            'title' => $this->auction->title,
            'status' => 'ENDED',
            'winningBid' => (float) $this->auction->current_bid,
            'winner' => $winner ? [
                'id' => $winner->id,
                'fullName' => $winner->full_name,
                'winningBid' => (float) $winner->winning_bid,
                'totalParticipants' => $winner->total_participants,
                'status' => $winner->status,
            ] : null,
            'participantCount' => $this->auction->participant_count,
            'endedAt' => now()->toIso8601String(),
        ];
    }
}
