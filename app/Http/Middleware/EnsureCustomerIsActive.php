<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureCustomerIsActive
{
    /**
     * Handle an incoming request for customer authentication and active account status.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Customer|null $customer */
        $customer = auth('customer')->user();

        // 1. If not authenticated or customer was deleted from database
        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'code' => 'unauthenticated',
                'message' => 'Unauthorized or account deleted.',
            ], 401);
        }

        // 2. If customer account is deactivated
        if (!$customer->is_active) {
            try {
                JWTAuth::parseToken()->invalidate();
            } catch (\Throwable $t) {}

            return response()->json([
                'status' => 'error',
                'code' => 'account_deactivated',
                'message' => 'Your account has been deactivated. Please contact support.',
                'error' => 'account_deactivated',
            ], 401);
        }

        // 3. Verify token version claim (to invalidate all prior tokens upon logout/revocation)
        try {
            $token = JWTAuth::getToken();
            if ($token) {
                $payload = JWTAuth::getPayload($token);
                $claimVersion = $payload->get('tv');
                if ($claimVersion !== null && (int)$claimVersion !== (int)($customer->token_version ?? 1)) {
                    try {
                        JWTAuth::setToken($token)->invalidate();
                    } catch (\Throwable $t) {}

                    return response()->json([
                        'status' => 'error',
                        'code' => 'session_revoked',
                        'message' => 'Your session has expired or was revoked. Please log in again.',
                        'error' => 'session_revoked',
                    ], 401);
                }
            }
        } catch (\Throwable $t) {
            // Ignore token parsing exceptions here; auth:customer already validated token validity
        }

        return $next($request);
    }
}
