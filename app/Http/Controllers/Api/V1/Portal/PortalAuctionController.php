<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Models\Auction;
use App\Models\Organization;
use App\Events\AuctionUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PortalAuctionController extends Controller
{
    /**
     * Get organization code from invitation code
     * 
     * @param string $invitationCode
     * @return string|null
     */
    private function getOrgCodeFromInvitation(string $invitationCode): ?string
    {
        $org = Organization::where('portal_invitation_code', $invitationCode)
            ->where('portal_invitation_active', true)
            ->first();
        
        return $org?->code;
    }

    /**
     * Search LIVE auctions (public)
     * GET /api/v1/auctions/search?invitation_code=PORT-XXXXX
     */
    public function search(Request $request): JsonResponse
    {
        // Get org from invitation code or auth user
        $invitationCode = $request->get('invitation_code');
        $orgCode = $invitationCode 
            ? $this->getOrgCodeFromInvitation($invitationCode)
            : auth()->user()?->organization_code;

        if (!$orgCode) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
                'code' => 'ORG_NOT_FOUND'
            ], 400);
        }

        // Filter: only auctions that are currently LIVE
        $now = now();
        $query = Auction::where('organization_code', $orgCode)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now);

        // Search by query (title, description)
        if ($request->has('query') && !empty($request->get('query'))) {
            $searchTerm = '%' . $request->get('query') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                  ->orWhere('description', 'like', $searchTerm);
            });
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->get('category'));
        }

        // Pagination
        $page = (int) $request->get('page', 1);
        $limit = min((int) $request->get('limit', 10), 50);
        $offset = ($page - 1) * $limit;

        $total = $query->count();
        $auctions = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $auctions->map(fn ($auction) => $auction->toPortalArray()),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Get auctions by category (public)
     * GET /api/v1/auctions/category/{category}?invitation_code=PORT-XXXXX
     */
    public function getByCategory(string $category, Request $request): JsonResponse
    {
        // Get org from invitation code or auth user
        $invitationCode = $request->get('invitation_code');
        $orgCode = $invitationCode 
            ? $this->getOrgCodeFromInvitation($invitationCode)
            : auth()->user()?->organization_code;

        if (!$orgCode) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
                'code' => 'ORG_NOT_FOUND'
            ], 400);
        }

        // Filter: only auctions that are currently LIVE
        $now = now();
        $query = Auction::where('organization_code', $orgCode)
            ->where('category', $category)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now);

        // Pagination
        $page = (int) $request->get('page', 1);
        $limit = min((int) $request->get('limit', 10), 50);
        $offset = ($page - 1) * $limit;

        $total = $query->count();
        $auctions = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $auctions->map(fn ($auction) => $auction->toPortalArray()),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Get single LIVE auction (public)
     * GET /api/v1/auctions/{id}?invitation_code=PORT-XXXXX
     * Shows LIVE auctions and ENDED auctions (with winner info)
     */
    public function show(string $id, Request $request): JsonResponse
    {
        // Get org from invitation code or auth user (optional for show)
        $invitationCode = $request->get('invitation_code');
        $orgCode = $invitationCode 
            ? $this->getOrgCodeFromInvitation($invitationCode)
            : auth()->user()?->organization_code;

        $now = now();
        $auction = Auction::where('id', $id)
            ->when($orgCode, function ($q) use ($orgCode) {
                return $q->where('organization_code', $orgCode);
            })
            ->where(function ($q) use ($now) {
                // LIVE auctions: start_time <= now <= end_time
                $q->where(function ($subQ) use ($now) {
                    $subQ->whereNotNull('start_time')
                         ->whereNotNull('end_time')
                         ->where('start_time', '<=', $now)
                         ->where('end_time', '>=', $now);
                })
                // OR ENDED auctions
                ->orWhere('status', 'ENDED');
            })
            ->first();

        if (!$auction) {
            return response()->json([
                'success' => false,
                'message' => 'AUCTION_NOT_FOUND',
                'code' => 'AUCTION_NOT_FOUND'
            ], 404);
        }

        // Get response based on auction status
        $response = $auction->toPortalArray();
        
        // If auction is ENDED, include winner info
        if ($auction->status === 'ENDED') {
            // Auto-create winner if not exists yet
            $winner = $auction->winnerBid()->first();
            if (!$winner) {
                $winner = $auction->autoCreateWinner();
            }
            
            if ($winner) {
                $response['winner'] = [
                    'id' => $winner->id,
                    'fullName' => $winner->full_name,
                    'winningBid' => (float) $winner->winning_bid,
                    'status' => $winner->status,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $response
        ]);
    }

    /**
     * Record view on auction (public)
     * POST /api/v1/auctions/{id}/view
     */
    public function recordView(string $id): JsonResponse
    {
        $auction = Auction::find($id);

        if (!$auction) {
            return response()->json([
                'success' => false,
                'error' => 'Auction not found',
                'code' => 'AUCTION_NOT_FOUND'
            ], 404);
        }

        $auction->increment('view_count');

        // Broadcast view count update via WebSocket
        broadcast(new AuctionUpdated($auction));

        return response()->json([
            'success' => true,
            'data' => [
                'auctionId' => $auction->id,
                'viewCount' => $auction->view_count
            ]
        ]);
    }

    /**
     * Get all LIVE auctions for portal (public)
     * GET /api/v1/auctions?invitation_code=PORT-XXXXX
     */
    public function list(Request $request): JsonResponse
    {
        // Get org from invitation code or auth user
        $invitationCode = $request->get('invitation_code');
        $orgCode = $invitationCode 
            ? $this->getOrgCodeFromInvitation($invitationCode)
            : auth()->user()?->organization_code;
        
        if (!$orgCode) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
                'code' => 'ORG_NOT_FOUND'
            ], 400);
        }

        // Filter: Only LIVE auctions
        $now = now();
        $query = Auction::where('organization_code', $orgCode)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now);

        // Apply filters
        if ($request->has('category')) {
            $query->where('category', $request->get('category'));
        }

        // Sorting
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');
        
        if (in_array($sort, ['created_at', 'current_bid', 'end_time'])) {
            $query->orderBy($sort, $order);
        }

        // Pagination
        $page = (int) $request->get('page', 1);
        $limit = min((int) $request->get('limit', 10), 50);
        $offset = ($page - 1) * $limit;

        $total = $query->count();
        $auctions = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $auctions->map(fn ($auction) => $auction->toPortalArray()),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    }
}
