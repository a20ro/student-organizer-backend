<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureSessionIsRecent
{
    /**
     * Maximum allowed inactivity in minutes.
     */
    private const MAX_INACTIVITY_MINUTES = 30;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): JsonResponse
    {
        $user = $request->user();

        // If there's no authenticated user, just continue.
        if (!$user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        // If no Sanctum token (shouldn't happen on auth:sanctum routes), continue.
        if (!$token) {
            return $next($request);
        }

        /** @var UserSession|null $session */
        $session = UserSession::where('token_id', $token->id)->first();

        // If we don't have a matching session row, just continue (don't lock user out).
        if (!$session) {
            return $next($request);
        }

        // If session already marked inactive, treat it as expired.
        if ($session->is_active === false) {
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => 'Your session has expired. Please log in again.',
                'redirect_url' => 'https://studentorganizer.netlify.app/login.html'
            ], 401);
        }

        $lastActivity = $session->last_activity ?? $session->created_at;
        $cutoff = Carbon::now()->subMinutes(self::MAX_INACTIVITY_MINUTES);

        // If the last activity was more than MAX_INACTIVITY_MINUTES ago, expire the session.
        if ($lastActivity && $lastActivity->lt($cutoff)) {
            $session->is_active = false;
            $session->save();

            $token->delete();

            return response()->json([
                'success' => false,
                'message' => 'Your session has expired due to 30 minutes of inactivity. Please log in again.',
                'redirect_url' => 'https://studentorganizer.netlify.app/login.html'
            ], 401);
        }

        // Otherwise, update last_activity and continue, but throttle updates to reduce DB noise.
        $now = Carbon::now();
        if (!$session->last_activity || $session->last_activity->diffInMinutes($now) >= 1) {
            $session->last_activity = $now;
            $session->is_active = true;
            $session->save();
        }

        return $next($request);
    }
}

