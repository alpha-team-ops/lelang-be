<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidNotification extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bid_notifications';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'bid_id',
        'user_id',
        'notification_type',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the bid this notification is for
     */
    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'bid_id');
    }

    /**
     * Get the user receiving this notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Notification types
     */
    public const NOTIFICATION_OUTBID = 'OUTBID';
    public const NOTIFICATION_SELLER_NEW_BID = 'SELLER_NEW_BID';
    public const NOTIFICATION_WINNING = 'WINNING';
}
