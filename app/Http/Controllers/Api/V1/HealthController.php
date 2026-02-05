<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health Check Endpoint
     * GET /api/v1/health
     */
    public function check()
    {
        try {
            // Check database connection
            DB::connection()->getPdo();
            
            return response()->json([
                'status' => 'ok',
                'message' => 'Application is running',
                'timestamp' => now()->toIso8601String(),
                'database' => 'connected'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application health check failed',
                'timestamp' => now()->toIso8601String(),
                'database' => 'disconnected',
                'error' => $e->getMessage()
            ], 503);
        }
    }
}
