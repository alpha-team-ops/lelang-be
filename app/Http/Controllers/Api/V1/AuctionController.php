<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Auction;
use App\Models\AuctionImage;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuctionController extends Controller
{
    /**
     * Get all admin auctions with filters
     * GET /api/v1/auctions
     */
    public function index(Request $request): JsonResponse
    {
        $orgCode = $request->user()?->organization_code;
        if (!$orgCode) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 400);
        }

        $query = Auction::where('organization_code', $orgCode);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

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
            'data' => $auctions->map(fn ($auction) => $auction->toAdminArray()),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Get specific auction by ID
     * GET /api/v1/auctions/:id
     */
    public function show(string $id): JsonResponse
    {
        $orgCode = auth()->user()?->organization_code;
        if (!$orgCode) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 400);
        }

        $auction = Auction::where('id', $id)
            ->where('organization_code', $orgCode)
            ->first();

        if (!$auction) {
            return response()->json([
                'success' => false,
                'message' => 'AUCTION_NOT_FOUND',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $auction->toAdminArray()
        ]);
    }

    /**
     * Create new auction
     * POST /api/v1/auctions
     */
    public function store(Request $request): JsonResponse
    {
        // Get user from request (set by middleware)
        $user = $request->attributes->get('user') ?: auth()->user();
        
        // Check permission
        if (!$this->hasPermission('manage_auctions', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'PERMISSION_DENIED'
            ], 403);
        }

        $orgCode = $user?->organization_code;
        if (!$orgCode) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 400);
        }

        // Validate request
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100|unique:auctions,serial_number,NULL,id,organization_code,' . $orgCode,
            'item_location' => 'nullable|string|max:100',
            'purchase_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'starting_price' => 'required|numeric|min:0',
            'reserve_price' => 'nullable|numeric|min:0',
            'bid_increment' => 'required|numeric|min:0',
            'start_time' => 'required|date_format:Y-m-d H:i:s|after:now',
            'end_time' => 'required|date_format:Y-m-d H:i:s|after:start_time',
            'image' => 'nullable|string|max:255',
            'images' => 'nullable|array|max:10',
            'images.*' => 'string|url|max:255',
        ], [
            'starting_price.required' => 'Starting price is required',
            'bid_increment.required' => 'Bid increment is required',
        ]);

        // Default reserve_price to starting_price if not provided
        if (empty($validated['reserve_price'])) {
            $validated['reserve_price'] = $validated['starting_price'];
        }

        // Create auction
        $auctionId = Str::uuid()->toString();
        
        // Calculate initial status based on start_time and end_time
        $now = now();
        $startTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $validated['start_time']);
        $endTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $validated['end_time']);
        
        if ($now < $startTime) {
            $initialStatus = 'DRAFT';
        } elseif ($now <= $endTime) {
            $initialStatus = 'LIVE';
        } else {
            $initialStatus = 'ENDED';
        }
        
        $auction = Auction::create([
            'id' => $auctionId,
            'organization_code' => $orgCode,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'condition' => $validated['condition'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'item_location' => $validated['item_location'] ?? null,
            'purchase_year' => $validated['purchase_year'] ?? null,
            'starting_price' => $validated['starting_price'],
            'reserve_price' => $validated['reserve_price'],
            'bid_increment' => $validated['bid_increment'],
            'current_bid' => $validated['starting_price'],
            'total_bids' => 0,
            'status' => $initialStatus,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'seller' => auth()->user()?->name ?? 'Admin',
            'view_count' => 0,
            'participant_count' => 0,
            'image' => $validated['image'] ?? null,
        ]);

        // Create auction images
        if (isset($validated['images'])) {
            foreach ($validated['images'] as $index => $imageUrl) {
                AuctionImage::create([
                    'id' => Str::uuid()->toString(),
                    'auction_id' => $auctionId,
                    'image_url' => $imageUrl,
                    'order_num' => $index + 1,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Auction created successfully',
            'data' => $auction->toAdminArray()
        ], 201);
    }

    /**
     * Update auction
     * PUT /api/v1/auctions/:id
     */
    public function update(string $id, Request $request): JsonResponse
    {
        // Get user from request (set by middleware)
        $user = $request->attributes->get('user') ?: auth()->user();
        
        // Check permission
        if (!$this->hasPermission('manage_auctions', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'PERMISSION_DENIED'
            ], 403);
        }

        $orgCode = $user?->organization_code;
        $auction = Auction::where('id', $id)->where('organization_code', $orgCode)->first();

        if (!$auction) {
            return response()->json([
                'success' => false,
                'message' => 'AUCTION_NOT_FOUND'
            ], 404);
        }

        // Cannot update LIVE or ENDED auctions (except status)
        if (in_array($auction->status, ['LIVE', 'ENDED']) && !$request->has('status')) {
            return response()->json([
                'success' => false,
                'message' => 'CANNOT_UPDATE_LIVE'
            ], 409);
        }

        // Validate request
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'item_location' => 'nullable|string|max:100',
            'purchase_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'starting_price' => 'nullable|numeric|min:0',
            'reserve_price' => 'nullable|numeric|min:0',
            'bid_increment' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:DRAFT,SCHEDULED,LIVE,ENDING,ENDED,CANCELLED',
            'start_time' => 'nullable|date_format:Y-m-d H:i:s',
            'end_time' => 'nullable|date_format:Y-m-d H:i:s',
            'image' => 'nullable|string|max:255',
            'images' => 'nullable|array|max:10',
            'images.*' => 'string|url|max:255',
        ]);

        // Check price validation if both are being updated
        $startingPrice = $validated['starting_price'] ?? $auction->starting_price;
        $reservePrice = $validated['reserve_price'] ?? $auction->reserve_price;

        if ((float) $startingPrice >= (float) $reservePrice) {
            return response()->json([
                'success' => false,
                'message' => 'INVALID_PRICE',
                'errors' => ['Starting price must be less than reserve price']
            ], 400);
        }

        // Update auction fields
        $auction->update(array_filter([
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'condition' => $validated['condition'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'item_location' => $validated['item_location'] ?? null,
            'purchase_year' => $validated['purchase_year'] ?? null,
            'starting_price' => $validated['starting_price'] ?? null,
            'reserve_price' => $validated['reserve_price'] ?? null,
            'bid_increment' => $validated['bid_increment'] ?? null,
            'status' => $validated['status'] ?? null,
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'image' => $validated['image'] ?? null,
        ], function ($value) {
            return $value !== null;
        }));

        // Update images if provided
        if (isset($validated['images'])) {
            AuctionImage::where('auction_id', $id)->delete();
            foreach ($validated['images'] as $index => $imageUrl) {
                AuctionImage::create([
                    'id' => Str::uuid()->toString(),
                    'auction_id' => $id,
                    'image_url' => $imageUrl,
                    'order_num' => $index + 1,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Auction updated successfully',
            'data' => $auction->fresh()->toAdminArray()
        ]);
    }

    /**
     * Delete auction
     * DELETE /api/v1/auctions/:id
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        // Get user from request (set by middleware)
        $user = $request->attributes->get('user') ?: auth()->user();
        
        // Check permission
        if (!$this->hasPermission('manage_auctions', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'PERMISSION_DENIED'
            ], 403);
        }

        $orgCode = $user?->organization_code;
        $auction = Auction::where('id', $id)->where('organization_code', $orgCode)->first();

        if (!$auction) {
            return response()->json([
                'success' => false,
                'message' => 'AUCTION_NOT_FOUND'
            ], 404);
        }

        // Can only delete DRAFT auctions
        if ($auction->status !== 'DRAFT') {
            return response()->json([
                'success' => false,
                'message' => 'CANNOT_DELETE_LIVE'
            ], 409);
        }

        // Delete images first
        AuctionImage::where('auction_id', $id)->delete();

        // Delete auction
        $auction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Auction deleted successfully'
        ]);
    }

    /**
     * Get all portal auctions (LIVE only - public)
     * GET /api/v1/auctions/portal/list
     */
    public function portalList(Request $request): JsonResponse
    {
        $orgCode = $request->get('organization_code', auth()->user()?->organization_code);
        if (!$orgCode) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 400);
        }

        $query = Auction::where('organization_code', $orgCode)
            ->where('status', 'LIVE');

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

    /**
     * Get single portal auction by ID (public)
     * GET /api/v1/auctions/portal/:id
     */
    public function portalShow(string $id): JsonResponse
    {
        $auction = Auction::where('id', $id)
            ->where('status', 'LIVE')
            ->first();

        if (!$auction) {
            return response()->json([
                'success' => false,
                'message' => 'AUCTION_NOT_FOUND'
            ], 404);
        }

        // Increment view count
        $auction->increment('view_count');

        return response()->json([
            'success' => true,
            'data' => $auction->toPortalArray()
        ]);
    }

    /**
     * Search auctions (public)
     * GET /api/v1/auctions/search
     */
    public function search(Request $request): JsonResponse
    {
        $orgCode = $request->get('organization_code', auth()->user()?->organization_code);
        if (!$orgCode) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 400);
        }

        $query = Auction::where('organization_code', $orgCode)
            ->where('status', 'LIVE');

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
     * GET /api/v1/auctions/category/:category
     */
    public function getByCategory(string $category, Request $request): JsonResponse
    {
        $orgCode = $request->get('organization_code', auth()->user()?->organization_code);
        if (!$orgCode) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 400);
        }

        $query = Auction::where('organization_code', $orgCode)
            ->where('category', $category)
            ->where('status', 'LIVE');

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
     * Get auctions by status (admin only)
     * GET /api/v1/auctions/status/:status
     */
    public function getByStatus(string $status, Request $request): JsonResponse
    {
        // Get user from request (set by middleware)
        $user = $request->attributes->get('user') ?: auth()->user();
        
        // Check permission
        if (!$this->hasPermission('manage_auctions', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'PERMISSION_DENIED'
            ], 403);
        }

        $orgCode = $user?->organization_code;
        $validStatuses = ['DRAFT', 'SCHEDULED', 'LIVE', 'ENDING', 'ENDED', 'CANCELLED'];

        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status'
            ], 400);
        }

        $query = Auction::where('organization_code', $orgCode)
            ->where('status', $status);

        // Pagination
        $page = (int) $request->get('page', 1);
        $limit = min((int) $request->get('limit', 10), 50);
        $offset = ($page - 1) * $limit;

        $total = $query->count();
        $auctions = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $auctions->map(fn ($auction) => $auction->toAdminArray()),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Check if user has permission
     */
    private function hasPermission(string $permission, $user = null): bool
    {
        $user = $user ?: auth()->user();
        
        if (!$user) {
            return false;
        }

        // Check if user is ADMIN - user dapat create auction jika role adalah ADMIN
        if ($user->role === 'ADMIN') {
            return true;
        }

        // Check if permission is in JWT token
        if (isset($user->permissions) && in_array($permission, $user->permissions)) {
            return true;
        }
        
        return false;
    }
}
