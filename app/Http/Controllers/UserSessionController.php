<?php

namespace App\Http\Controllers;

use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = UserSession::where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $session = UserSession::where('user_id', $request->user()->id)->findOrFail($id);

        // Revoke the Sanctum token
        $request->user()->tokens()->where('id', $session->token_id)->delete();

        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session revoked successfully.',
        ]);
    }

    public function revokeAll(Request $request): JsonResponse
    {
        // Revoke all tokens except current
        $currentTokenId = $request->user()->currentAccessToken()->id;
        $request->user()->tokens()->where('id', '!=', $currentTokenId)->delete();

        UserSession::where('user_id', $request->user()->id)
            ->where('token_id', '!=', $currentTokenId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'All other sessions revoked.',
        ]);
    }
}
