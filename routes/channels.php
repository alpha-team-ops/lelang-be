<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Auction real-time channels
Broadcast::channel('auction.{auctionId}', function ($user, $auctionId) {
    // Allow all users to listen to auction channels
    // Public auction broadcasts - no authentication required
    return true;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    // Only allow the authenticated user to listen to their own user channel
    // For private notifications
    return (int) $user?->id === (int) $userId;
});

Broadcast::channel('bidder.{bidderId}', function ($user, $bidderId) {
    // Allow bidder to receive outbid notifications
    return (int) $user?->id === (int) $bidderId;
});
