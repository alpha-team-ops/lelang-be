<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\BidPlaced;
use App\Events\AuctionUpdated;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\BidNotification;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BidController extends Controller
{
    /**
     * Get all bid activity with optional filters
     * GET /api/v1/bids/activity
     */
    public function activity(Request $request)
    {
        $validated = $request->validate([
            'auctionId' => 'nullable|uuid',
            'limit' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|in:timestamp,amount',
        ]);

        $limit = $validated['limit'] ?? 20;
        $sort = $validated['sort'] ?? 'timestamp';

        $query = Bid::with(['auction', 'bidder']);

        if ($validated['auctionId'] ?? null) {
            $query->where('auction_id', $validated['auctionId']);
        }

        if ($sort === 'amount') {
            $query->orderByDesc('bid_amount');
        } else {
            $query->orderByDesc('bid_timestamp');
        }

        $bids = $query->paginate($limit);

        // Transform bids using model method
        $transformedBids = array_map(fn($bid) => $bid->toActivityArray(), $bids->items());

        return response()->json([
            'success' => true,
            'data' => $transformedBids,
            'pagination' => [
                'current_page' => $bids->currentPage(),
                'per_page' => $bids->perPage(),
                'total' => $bids->total(),
                'last_page' => $bids->lastPage(),
            ]
        ]);
    }

    /**
     * Get all bids for specific auction
     * GET /api/v1/bids/auction/:auctionId
     */
    public function getAuctionBids($auctionId)
    {
        $auction = Auction::find($auctionId);

        if (!$auction) {
            return response()->json([
                'success' => false,
                'error' => 'Auction not found',
                'code' => 'AUCTION_NOT_FOUND'
            ], 404);
        }

        $bids = $auction->bids()
            ->with('bidder')
            ->orderByDesc('bid_timestamp')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $bids->map(fn($bid) => $bid->toActivityArray())
        ]);
    }

    /**
     * Place a bid
     * POST /api/v1/bids/place
     * Requires: Bearer token (portal user)
     */
    public function place(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'auctionId' => 'required|uuid',
                'bidAmount' => 'required|numeric|min:1',
            ]);

            // Get authenticated portal user from middleware
            $bidder = $request->user();
            if (!$bidder) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'code' => 'UNAUTHORIZED'
                ], 401);
            }

            $bidderId = $bidder->id;
            if (!$bidder || !$bidder->isPortalUser()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Only portal users can place bids',
                    'code' => 'INVALID_USER_TYPE'
                ], 403);
            }

            // Check if bidder account is active
            if ($bidder->status !== 'ACTIVE') {
                return response()->json([
                    'success' => false,
                    'error' => 'Your account is not active',
                    'code' => 'ACCOUNT_INACTIVE'
                ], 403);
            }

            // Get auction
            $auction = Auction::find($validated['auctionId']);
            if (!$auction) {
                return response()->json([
                    'success' => false,
                    'error' => 'Auction not found',
                    'code' => 'AUCTION_NOT_FOUND'
                ], 404);
            }

            // Use database transaction for atomicity
            return DB::transaction(function () use ($validated, $bidder, $auction) {
                // Check auction status
                $auctionStatus = $auction->getCurrentStatus();
                if (!in_array($auctionStatus, ['LIVE', 'ENDING'])) {
                    throw ValidationException::withMessages([
                        'auctionId' => 'Cannot bid on non-LIVE auction'
                    ]);
                }

                // Check if bidder is the seller
                if ($auction->seller === $bidder->id) {
                    throw ValidationException::withMessages([
                        'auctionId' => 'You cannot bid on your own auction'
                    ]);
                }

                // Validate bid amount
                $minimumBid = (float) $auction->current_bid + (float) $auction->bid_increment;
                $bidIncrement = (float) $auction->bid_increment;
                
                if ((float) $validated['bidAmount'] < $minimumBid) {
                    throw ValidationException::withMessages([
                        'bidAmount' => "Bid amount must be at least $minimumBid"
                    ]);
                }

                // Validate bid amount is a multiple of bid increment
                $bidDifference = (float) $validated['bidAmount'] - (float) $auction->current_bid;
                
                // Check if the difference is divisible by bid increment (with floating point tolerance)
                $remainder = fmod($bidDifference, $bidIncrement);
                if ($remainder > 0.01 && $remainder < ($bidIncrement - 0.01)) {
                    throw ValidationException::withMessages([
                        'bidAmount' => "Bid amount must be in increments of $bidIncrement from current bid"
                    ]);
                }

                // Get previous highest bid
                $previousBid = $auction->bids()
                    ->where('status', 'CURRENT')
                    ->first();

                // Mark previous bid as OUTBID
                if ($previousBid) {
                    $previousBid->update(['status' => 'OUTBID']);

                    // Notify previous bidder (outbid)
                    BidNotification::create([
                        'bid_id' => $previousBid->id,
                        'user_id' => $previousBid->bidder_id,
                        'notification_type' => BidNotification::NOTIFICATION_OUTBID,
                        'sent_at' => now(),
                    ]);
                }

                // Create new bid
                $newBid = Bid::create([
                    'auction_id' => $auction->id,
                    'bidder_id' => $bidder->id,
                    'bid_amount' => $validated['bidAmount'],
                    'status' => 'CURRENT',
                    'bid_timestamp' => now(),
                ]);

                // Update auction
                $auction->update([
                    'current_bid' => $validated['bidAmount'],
                    'current_bidder' => $bidder->id,
                    'total_bids' => ($auction->total_bids ?? 0) + 1,
                ]);

                // Update participant count if this is a new bidder
                $bidderCount = $auction->bids()->distinct('bidder_id')->count();
                $auction->update(['participant_count' => $bidderCount]);

                // 🎯 BROADCAST: Real-time updates via WebSocket
                broadcast(new BidPlaced($newBid));
                broadcast(new AuctionUpdated($auction));

                // Notify seller about new bid
                if ($auction->seller) {
                    // Get seller user ID from the seller name
                    $sellerUser = User::where('name', $auction->seller)->first();
                    if ($sellerUser) {
                        BidNotification::create([
                            'bid_id' => $newBid->id,
                            'user_id' => $sellerUser->id,
                            'notification_type' => BidNotification::NOTIFICATION_SELLER_NEW_BID,
                            'sent_at' => now(),
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $newBid->id,
                        'auctionId' => $newBid->auction_id,
                        'bidAmount' => (float) $newBid->bid_amount,
                        'status' => $newBid->status,
                        'timestamp' => $newBid->bid_timestamp->toIso8601String(),
                    ]
                ], 201);
            });
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $message = array_values($errors)[0][0] ?? 'Validation failed';
            
            return response()->json([
                'success' => false,
                'error' => $message,
                'code' => match ($message) {
                    str_contains($message, 'non-LIVE') => 'AUCTION_NOT_LIVE',
                    str_contains($message, 'own auction') => 'CANNOT_BID_OWN_AUCTION',
                    str_contains($message, 'must be at least') => 'BID_TOO_LOW',
                    default => 'VALIDATION_ERROR'
                }
            ], 400);
        }
    }

    /**
     * Get user's bid history
     * GET /api/v1/bids/user/:userId
     */
    public function userHistory($userId)
    {
        $bids = Bid::where('bidder_id', $userId)
            ->with(['auction', 'bidder'])
            ->orderByDesc('bid_timestamp')
            ->paginate(20);

        // Transform bids using model method
        $transformedBids = array_map(fn($bid) => $bid->toActivityArray(), $bids->items());

        return response()->json([
            'success' => true,
            'data' => $transformedBids,
            'pagination' => [
                'current_page' => $bids->currentPage(),
                'per_page' => $bids->perPage(),
                'total' => $bids->total(),
                'last_page' => $bids->lastPage(),
            ]
        ]);
    }
}
