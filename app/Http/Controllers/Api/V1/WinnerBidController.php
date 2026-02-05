<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\WinnerBid;
use App\Models\WinnerStatusHistory;
use App\Models\Auction;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WinnerBidController extends Controller
{
    /**
     * Get all winner bids with optional filters
     * GET /api/v1/bids/winners
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:PAYMENT_PENDING,PAID,SHIPPED,COMPLETED,CANCELLED',
            'auctionId' => 'nullable|uuid',
            'organizationCode' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 10;

        $query = WinnerBid::query();

        // Apply filters
        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($auctionId = $validated['auctionId'] ?? null) {
            $query->where('auction_id', $auctionId);
        }

        if ($organizationCode = $validated['organizationCode'] ?? null) {
            $query->where('organization_code', $organizationCode);
        }

        $total = $query->count();
        $winners = $query->orderByDesc('created_at')
                        ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => array_map(fn($winner) => $winner->toArray(), $winners->items()),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ]);
    }

    /**
     * Get winner bid by ID
     * GET /api/v1/bids/winners/:id
     */
    public function show(string $id)
    {
        $winner = WinnerBid::find($id);

        if (!$winner) {
            return response()->json([
                'success' => false,
                'error' => 'Winner bid not found',
                'code' => 'WINNER_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $winner->toArray(),
        ]);
    }

    /**
     * Get winner bids by status
     * GET /api/v1/bids/winners/status/:status
     */
    public function byStatus(string $status, Request $request)
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if (!in_array($status, ['PAYMENT_PENDING', 'PAID', 'SHIPPED', 'COMPLETED', 'CANCELLED'])) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid status',
                'code' => 'INVALID_STATUS',
            ], 400);
        }

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 10;

        $total = WinnerBid::where('status', $status)->count();
        $winners = WinnerBid::where('status', $status)
                            ->orderByDesc('created_at')
                            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => array_map(fn($winner) => $winner->toArray(), $winners->items()),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ]);
    }

    /**
     * Update winner bid status
     * PUT /api/v1/bids/winners/:id/status
     */
    public function updateStatus(string $id, Request $request)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:PAYMENT_PENDING,PAID,SHIPPED,COMPLETED,CANCELLED',
                'notes' => 'nullable|string|max:500',
            ]);

            $winner = WinnerBid::find($id);

            if (!$winner) {
                return response()->json([
                    'success' => false,
                    'error' => 'Winner bid not found',
                    'code' => 'WINNER_NOT_FOUND',
                ], 404);
            }

            // Check if transition is valid
            if (!$winner->canTransitionTo($validated['status'])) {
                return response()->json([
                    'success' => false,
                    'error' => "Cannot transition from {$winner->status} to {$validated['status']}",
                    'code' => 'INVALID_STATUS_TRANSITION',
                ], 422);
            }

            return DB::transaction(function () use ($winner, $validated, $request) {
                $oldStatus = $winner->status;
                $newStatus = $validated['status'];

                // Update winner bid
                $winner->update([
                    'status' => $newStatus,
                    'notes' => $validated['notes'] ?? $winner->notes,
                ]);

                // Record status change in history
                WinnerStatusHistory::create([
                    'winner_bid_id' => $winner->id,
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                    'changed_by' => $request->user()?->id,
                    'notes' => $validated['notes'] ?? null,
                ]);

                // TODO: Send notification to winner
                // NotificationService::notifyWinnerStatusChange($winner, $oldStatus, $newStatus);

                return response()->json([
                    'success' => true,
                    'message' => "Status updated from {$oldStatus} to {$newStatus}",
                    'data' => $winner->toArray(),
                ]);
            });

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Create winner bid (internal - auto-triggered when auction ends)
     * POST /api/v1/bids/winners
     * 
     * IMPORTANT: This endpoint only creates winner bids for ENDED auctions.
     * A winner bid is essentially an AUCTION LOCK - it represents the final outcome.
     */
    public function create(Request $request)
    {
        try {
            $validated = $request->validate([
                'auctionId' => 'required|uuid|exists:auctions,id',
            ]);

            $auction = Auction::find($validated['auctionId']);

            // ============ CRITICAL VALIDATION: Auction must be ENDED ============
            if (!$auction->hasEnded()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot create winner bid for auction that has not ended',
                    'code' => 'AUCTION_NOT_ENDED',
                    'details' => [
                        'auctionStatus' => $auction->status,
                        'currentTime' => now()->toIso8601String(),
                        'auctionEndTime' => $auction->end_time?->toIso8601String(),
                    ]
                ], 422);
            }

            // Check if winner bid already exists (auction already locked)
            $existingWinner = WinnerBid::where('auction_id', $auction->id)->first();
            if ($existingWinner) {
                return response()->json([
                    'success' => false,
                    'error' => 'Winner bid already exists for this auction (auction is already locked)',
                    'code' => 'WINNER_ALREADY_EXISTS',
                    'data' => $existingWinner->toArray(),
                ], 409);
            }

            // Get highest bid for this auction
            $highestBid = $auction->bids()
                                  ->where('status', 'CURRENT')
                                  ->orderBy('bid_amount', 'desc')
                                  ->first();

            if (!$highestBid) {
                return response()->json([
                    'success' => false,
                    'error' => 'No valid bid found for this auction',
                    'code' => 'NO_VALID_BID',
                ], 400);
            }

            // Get bidder details
            $bidder = User::find($highestBid->bidder_id);

            if (!$bidder) {
                return response()->json([
                    'success' => false,
                    'error' => 'Bidder not found',
                    'code' => 'BIDDER_NOT_FOUND',
                ], 404);
            }

            // Calculate unique participant count
            $participantCount = $auction->bids()
                                       ->distinct('bidder_id')
                                       ->count();

            return DB::transaction(function () use ($auction, $highestBid, $bidder, $participantCount) {
                $winnerBid = WinnerBid::create([
                    'auction_id' => $auction->id,
                    'auction_title' => $auction->title,
                    'serial_number' => $auction->serial_number ?? null,
                    'category' => $auction->category ?? null,
                    'bidder_id' => $bidder->id,
                    'full_name' => $bidder->name,
                    'corporate_id_nip' => $bidder->corporate_id_nip ?? null,
                    'directorate' => $bidder->directorate ?? null,
                    'organization_code' => $auction->organization_code,
                    'winning_bid' => $highestBid->bid_amount,
                    'total_participants' => $participantCount,
                    'auction_end_time' => $auction->end_time,
                    'status' => WinnerBid::STATUS_PAYMENT_PENDING,
                    'payment_due_date' => now()->addHours(48),
                ]);

                // Record initial status in history
                WinnerStatusHistory::create([
                    'winner_bid_id' => $winnerBid->id,
                    'from_status' => null,
                    'to_status' => WinnerBid::STATUS_PAYMENT_PENDING,
                    'changed_by' => $request->user()?->id,
                    'notes' => 'Winner bid created automatically when auction ended',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Winner bid created and auction locked',
                    'data' => $winnerBid->toArray(),
                ], 201);
            });

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get overdue payments
     * GET /api/v1/bids/winners/overdue-payments
     */
    public function overduePayments(Request $request)
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 10;

        $total = WinnerBid::overduePayments()->count();
        $overdue = WinnerBid::overduePayments()
                           ->orderBy('payment_due_date', 'asc')
                           ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Overdue payments',
            'data' => array_map(fn($winner) => $winner->toArray(), $overdue->items()),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ]);
    }

    /**
     * Get status history for a winner bid
     * GET /api/v1/bids/winners/:id/history
     */
    public function statusHistory(string $id, Request $request)
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $winner = WinnerBid::find($id);

        if (!$winner) {
            return response()->json([
                'success' => false,
                'error' => 'Winner bid not found',
                'code' => 'WINNER_NOT_FOUND',
            ], 404);
        }

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 10;

        $total = $winner->statusHistory()->count();
        $history = $winner->statusHistory()
                         ->orderByDesc('changed_at')
                         ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => array_map(fn($h) => $h->toArray(), $history->items()),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ]);
    }

    /**
     * Update payment due date for a winner bid
     * PUT /api/v1/bids/winners/:id/payment-due-date
     */
    public function updatePaymentDueDate(string $id, Request $request)
    {
        try {
            // Get payment due date from either camelCase or snake_case
            $paymentDueDate = $request->input('paymentDueDate') ?? $request->input('payment_due_date');
            $notes = $request->input('notes');

            // Validate
            if (!$paymentDueDate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'errors' => ['paymentDueDate' => ['The payment due date field is required.']],
                ], 422);
            }

            // Validate date format
            try {
                $newDueDate = new \DateTime($paymentDueDate);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'errors' => ['paymentDueDate' => ['The payment due date must be a valid date format (Y-m-d H:i:s).']],
                ], 422);
            }

            // Check if date is in future
            if ($newDueDate <= new \DateTime('now')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'errors' => ['paymentDueDate' => ['The payment due date must be a date after now.']],
                ], 422);
            }

            if ($notes && strlen($notes) > 500) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'errors' => ['notes' => ['The notes field must not be greater than 500 characters.']],
                ], 422);
            }

            $winner = WinnerBid::find($id);

            if (!$winner) {
                return response()->json([
                    'success' => false,
                    'error' => 'Winner bid not found',
                    'code' => 'WINNER_NOT_FOUND',
                ], 404);
            }

            // Check if payment_due_date can be updated (not COMPLETED or CANCELLED)
            if (in_array($winner->status, [WinnerBid::STATUS_COMPLETED, WinnerBid::STATUS_CANCELLED])) {
                return response()->json([
                    'success' => false,
                    'error' => "Cannot update payment due date for {$winner->status} status",
                    'code' => 'INVALID_STATUS_FOR_UPDATE',
                ], 422);
            }

            $oldDueDate = $winner->payment_due_date;

            return DB::transaction(function () use ($winner, $newDueDate, $oldDueDate, $notes, $request) {
                // Update payment due date
                $winner->update([
                    'payment_due_date' => $newDueDate,
                    'notes' => $notes ?? $winner->notes,
                ]);

                // Record change in history if notes provided
                if ($notes) {
                    WinnerStatusHistory::create([
                        'winner_bid_id' => $winner->id,
                        'from_status' => $winner->status,
                        'to_status' => $winner->status,
                        'changed_by' => $request->user()?->id,
                        'notes' => "Payment due date updated from {$oldDueDate?->format('Y-m-d H:i:s')} to {$newDueDate->format('Y-m-d H:i:s')}. {$notes}",
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment due date updated successfully',
                    'data' => [
                        'id' => $winner->id,
                        'oldPaymentDueDate' => $oldDueDate?->toIso8601String(),
                        'newPaymentDueDate' => $newDueDate->toIso8601String(),
                        'updatedAt' => now()->toIso8601String(),
                    ],
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred',
                'code' => 'INTERNAL_ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
