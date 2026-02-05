<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\AuctionEnded;
use App\Events\AuctionUpdated;
use App\Models\Auction;
use App\Models\AuctionImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class AdminAuctionController extends Controller
{
    /**
     * Get all auctions (admin only)
     * GET /api/v1/admin/auctions
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

        // Recalculate status for all auctions based on current time
        $auctions->each(fn ($auction) => $this->recalculateStatus($auction));

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
     * Get single auction (admin only)
     * GET /api/v1/admin/auctions/:id
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $orgCode = $request->user()?->organization_code;
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

        // Recalculate status based on current time
        $this->recalculateStatus($auction);

        return response()->json([
            'success' => true,
            'data' => $auction->toAdminArray()
        ]);
    }

    /**
     * Create auction (admin only)
     * POST /api/v1/admin/auctions
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$this->hasPermission('manage_auctions', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'PERMISSION_DENIED'
            ], 403);
        }

        $orgCode = $user->organization_code;
        if (!$orgCode) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 400);
        }

        // Pre-process dates to support multiple formats
        if ($request->has('start_time')) {
            $request->merge(['start_time' => $this->parseDateTime($request->input('start_time'))]);
        }

        if ($request->has('end_time')) {
            $request->merge(['end_time' => $this->parseDateTime($request->input('end_time'))]);
        }

        // Validate request
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'item_location' => 'nullable|string|max:100',
            'purchase_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'starting_price' => 'required|numeric|min:0',
            'bid_increment' => 'required|numeric|min:0',
            'start_time' => 'nullable|date_format:Y-m-d H:i:s',
            'end_time' => 'nullable|date_format:Y-m-d H:i:s',
            'image' => 'nullable|string|max:255',
            'images' => 'nullable|array|max:10',
            'images.*' => 'string|url|max:255',
        ], [
            'starting_price.required' => 'Starting price is required',
            'bid_increment.required' => 'Bid increment is required',
        ]);

        // Create auction
        $auctionId = Str::uuid()->toString();
        
        // Calculate initial status based on start_time and end_time
        $initialStatus = 'DRAFT';
        if (!empty($validated['start_time']) && !empty($validated['end_time'])) {
            $now = now();
            $startTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $validated['start_time'], 'UTC');
            $endTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $validated['end_time'], 'UTC');
            
            if ($now < $startTime) {
                $initialStatus = 'DRAFT';
            } elseif ($now <= $endTime) {
                $initialStatus = 'LIVE';
            } else {
                $initialStatus = 'ENDED';
            }
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
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'seller' => $user->name ?? 'Admin',
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

        // Auto-create winner if auction is already ENDED
        if ($initialStatus === 'ENDED') {
            $auction->autoCreateWinner();
        }

        // Broadcast auction update to portal/admin clients
        broadcast(new AuctionUpdated($auction))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Auction created successfully',
            'data' => $auction->toAdminArray()
        ], 201);
    }

    /**
     * Update auction (admin only)
     * PUT /api/v1/admin/auctions/:id
     */
    public function update(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$this->hasPermission('manage_auctions', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'PERMISSION_DENIED'
            ], 403);
        }

        $orgCode = $user->organization_code;
        $auction = Auction::where('id', $id)->where('organization_code', $orgCode)->first();

        if (!$auction) {
            return response()->json([
                'success' => false,
                'message' => 'AUCTION_NOT_FOUND'
            ], 404);
        }

        // Pre-process dates to support multiple formats
        if ($request->has('start_time')) {
            $request->merge(['start_time' => $this->parseDateTime($request->input('start_time'))]);
        }

        if ($request->has('end_time')) {
            $request->merge(['end_time' => $this->parseDateTime($request->input('end_time'))]);
        }

        // Build validation rules conditionally
        // For LIVE/ENDED auctions, relax start_time validation
        $isAuctionActive = in_array($auction->status, ['LIVE', 'ENDING', 'ENDED']);
        
        $rules = [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'item_location' => 'nullable|string|max:100',
            'purchase_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'starting_price' => 'nullable|numeric|min:0',
            'bid_increment' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:DRAFT,SCHEDULED,LIVE,ENDING,ENDED,CANCELLED',
            'start_time' => 'nullable|date_format:Y-m-d H:i:s',
            'end_time' => 'nullable|date_format:Y-m-d H:i:s',
            'image' => 'nullable|string|max:255',
            'images' => 'nullable|array|max:10',
            'images.*' => 'string|url|max:255',
        ];

        // Validate request
        $validated = $request->validate($rules);

        // Prepare update data
        $updateData = [];
        $fieldsToUpdate = ['title', 'description', 'category', 'condition', 'serial_number', 
                          'item_location', 'purchase_year', 'starting_price', 
                          'bid_increment', 'start_time', 'end_time', 'image'];
        
        foreach ($fieldsToUpdate as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $updateData[$field] = $validated[$field];
            }
        }

        // Recalculate status based on current datetime if dates exist
        if (!array_key_exists('status', $validated) || $validated['status'] === null) {
            $appTimezone = config('app.timezone', 'UTC');
            $startTime = isset($validated['start_time']) 
                ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $validated['start_time'], $appTimezone) 
                : $auction->start_time;
            $endTime = isset($validated['end_time']) 
                ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $validated['end_time'], $appTimezone) 
                : $auction->end_time;
            
            if (!$startTime || !$endTime) {
                $updateData['status'] = 'DRAFT';
            } else {
                $now = now();
                if ($now < $startTime) {
                    $updateData['status'] = 'DRAFT';
                } elseif ($now <= $endTime) {
                    $updateData['status'] = 'LIVE';
                } else {
                    $updateData['status'] = 'ENDED';
                }
            }
        }

        // Update auction fields
        $auction->update($updateData);

        // Auto-create winner if auction just became ENDED
        if (($updateData['status'] ?? $auction->status) === 'ENDED' && $auction->status !== 'ENDED') {
            $auction->fresh()->autoCreateWinner();
            // Broadcast auction ended event to portal/admin clients
            broadcast(new AuctionEnded($auction->fresh()))->toOthers();
        } else {
            // Broadcast auction update to portal/admin clients
            broadcast(new AuctionUpdated($auction->fresh()))->toOthers();
        }

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
            'data' => Auction::find($id)->toAdminArray()
        ]);
    }

    /**
     * Delete auction (admin only)
     * DELETE /api/v1/admin/auctions/:id
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$this->hasPermission('manage_auctions', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'PERMISSION_DENIED'
            ], 403);
        }

        $orgCode = $user->organization_code;
        $auction = Auction::where('id', $id)->where('organization_code', $orgCode)->first();

        if (!$auction) {
            return response()->json([
                'success' => false,
                'message' => 'AUCTION_NOT_FOUND'
            ], 404);
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
     * Get auctions by status (admin only)
     * GET /api/v1/admin/auctions/status/:status
     */
    public function getByStatus(string $status, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$this->hasPermission('manage_auctions', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'PERMISSION_DENIED'
            ], 403);
        }

        $orgCode = $user->organization_code;
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
     * Recalculate auction status based on current datetime
     */
    private function recalculateStatus(Auction $auction): void
    {
        if (!$auction->start_time || !$auction->end_time) {
            if ($auction->status !== 'DRAFT') {
                $auction->update(['status' => 'DRAFT']);
            }
            return;
        }

        $now = now();
        $newStatus = null;

        if ($now < $auction->start_time) {
            $newStatus = 'DRAFT';
        } elseif ($now <= $auction->end_time) {
            $newStatus = 'LIVE';
        } else {
            $newStatus = 'ENDED';
        }

        // Update if status has changed
        if ($newStatus && $auction->status !== $newStatus) {
            $auction->update(['status' => $newStatus]);
            
            // Auto-create winner if auction just ended
            if ($newStatus === 'ENDED') {
                $auction->autoCreateWinner();
            }
        }
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

        if ($user->role === 'ADMIN') {
            return true;
        }

        if (isset($user->permissions) && in_array($permission, $user->permissions)) {
            return true;
        }
        
        return false;
    }

    /**
     * Parse datetime string from multiple formats
     * Supports ISO 8601, MySQL format, and standard DateTime parsing
     */
    private function parseDateTime(string $dateString): string
    {
        // Try specific formats first (strict parsing)
        $formats = [
            'Y-m-d\TH:i:s\Z',           // ISO 8601: 2026-02-10T10:30:00Z
            'Y-m-d\TH:i:s.u\Z',         // ISO 8601 with microseconds
            'Y-m-d\TH:i:sP',            // ISO 8601 with timezone: 2026-02-10T10:30:00+07:00
            'Y-m-d H:i:s',              // MySQL format: 2026-02-10 10:30:00
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        // If no format matched, return original string
        // This will cause validation to fail with proper error message
        return $dateString;
    }
}
