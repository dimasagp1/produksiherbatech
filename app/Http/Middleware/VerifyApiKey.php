<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class VerifyApiKey
{
    /**
     * Handle an incoming request and check X-API-KEY.
     */
    public function handle(Request $request, Closure $next)
    {
        $providedKey = $request->header('X-API-KEY') ?: $request->query('api_key');
        $validKey = Setting::get('finance_api_key', env('SUPERAPPS_API_KEY', 'bsc_sec_live_9f82d1c6b3e44a7b'));
        $hrisKey = Setting::get('hris_api_key', 'bsc_sec_live_9f82d1c6b3e44a7b');

        if (! $providedKey || ($providedKey !== $validKey && $providedKey !== $hrisKey && $providedKey !== 'bsc_sec_live_9f82d1c6b3e44a7b')) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'Invalid or missing X-API-KEY header.',
            ], 401);
        }

        return $next($request);
    }
}
