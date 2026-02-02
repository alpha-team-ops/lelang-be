<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WinnerStatusHistory extends Model
{
    use HasUuids;

    protected $table = 'winner_status_history';

    protected $fillable = [
        'winner_bid_id',
        'from_status',
        'to_status',
        'changed_by',
        'notes',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    const UPDATED_AT = null;

    /**
     * Relationships
     */
    public function winnerBid(): BelongsTo
    {
        return $this->belongsTo(WinnerBid::class, 'winner_bid_id', 'id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by', 'id');
    }

    /**
     * Transform to array for API response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'winnerBidId' => $this->winner_bid_id,
            'fromStatus' => $this->from_status,
            'toStatus' => $this->to_status,
            'changedBy' => $this->changed_by,
            'notes' => $this->notes,
            'changedAt' => $this->changed_at?->toIso8601String(),
        ];
    }
}
