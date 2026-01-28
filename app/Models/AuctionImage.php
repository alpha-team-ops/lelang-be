<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionImage extends Model
{
    use HasFactory;

    protected $table = 'auction_images';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'auction_id',
        'image_url',
        'order_num',
        'created_at',
    ];

    protected $casts = [
        'order_num' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the auction that owns this image
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class, 'auction_id');
    }
}
