<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WinnerBid extends Model
{
    use HasUuids;

    protected $table = 'winner_bids';

    protected $fillable = [
        'auction_id',
        'auction_title',
        'serial_number',
        'category',
        'bidder_id',
        'full_name',
        'corporate_id_nip',
        'directorate',
        'organization_code',
        'winning_bid',
        'total_participants',
        'auction_end_time',
        'status',
        'payment_due_date',
        'notes',
    ];

    protected $casts = [
        'auction_end_time' => 'datetime',
        'payment_due_date' => 'datetime',
        'winning_bid' => 'float',
        'total_participants' => 'integer',
    ];

    /**
     * Status constants
     */
    const STATUS_PAYMENT_PENDING = 'PAYMENT_PENDING';
    const STATUS_PAID = 'PAID';
    const STATUS_SHIPPED = 'SHIPPED';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CANCELLED = 'CANCELLED';

    /**
     * Valid status transitions
     */
    public static function validTransitions(): array
    {
        return [
            self::STATUS_PAYMENT_PENDING => [self::STATUS_PAID, self::STATUS_CANCELLED],
            self::STATUS_PAID => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPED => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];
    }

    /**
     * Check if status transition is valid
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $validTransitions = self::validTransitions();
        $currentStatus = $this->status;

        return isset($validTransitions[$currentStatus]) &&
               in_array($newStatus, $validTransitions[$currentStatus]);
    }

    /**
     * Relationships
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class, 'auction_id', 'id');
    }

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bidder_id', 'id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(WinnerStatusHistory::class, 'winner_bid_id', 'id');
    }

    /**
     * Transform to array for API response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'auctionId' => $this->auction_id,
            'auctionTitle' => $this->auction_title,
            'serialNumber' => $this->serial_number,
            'category' => $this->category,
            'fullName' => $this->full_name,
            'corporateIdNip' => $this->corporate_id_nip,
            'directorate' => $this->directorate,
            'organizationCode' => $this->organization_code,
            'winningBid' => $this->winning_bid,
            'totalParticipants' => $this->total_participants,
            'auctionEndTime' => $this->auction_end_time?->toIso8601String(),
            'status' => $this->status,
            'paymentDueDate' => $this->payment_due_date?->toIso8601String(),
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Scopes
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByAuction($query, string $auctionId)
    {
        return $query->where('auction_id', $auctionId);
    }

    public function scopeByOrganization($query, string $organizationCode)
    {
        return $query->where('organization_code', $organizationCode);
    }

    public function scopeOverduePayments($query)
    {
        return $query->where('status', self::STATUS_PAYMENT_PENDING)
                     ->whereDate('payment_due_date', '<', now()->toDateString());
    }
}
