<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSession;
use App\Models\AiMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAIController extends Controller
{
    public function sessions(Request $request)
    {
        try {
            $query = AiSession::with(['user'])->orderBy('created_at', 'desc');

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $sessions = $query->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $sessions
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'AI sessions table not available yet',
                'data' => []
            ], 200);
        }
    }

    public function usageStats()
    {
        try {
            // Daily token usage (last 30 days)
            $dailyUsage = AiMessage::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as messages_count')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

            // Total stats
            $totalSessions = AiSession::count();
            $totalMessages = AiMessage::count();
            $todaySessions = AiSession::whereDate('created_at', today())->count();
            $todayMessages = AiMessage::whereDate('created_at', today())->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_sessions' => $totalSessions,
                    'total_messages' => $totalMessages,
                    'today_sessions' => $todaySessions,
                    'today_messages' => $todayMessages,
                    'daily_usage' => $dailyUsage,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'AI tables not available yet',
                'data' => [
                    'total_sessions' => 0,
                    'total_messages' => 0,
                    'today_sessions' => 0,
                    'today_messages' => 0,
                    'daily_usage' => [],
                ]
            ], 200);
        }
    }

    public function flaggedMessages()
    {
        // Placeholder - implement when you add flagging system
        return response()->json([
            'success' => true,
            'data' => [
                'flagged_messages' => [],
                'message' => 'Flagging system coming soon'
            ]
        ], 200);
    }

    public function failedRequests()
    {
        // Check system logs for AI-related errors
        $failedRequests = \App\Models\SystemLog::where('type', 'ai_error')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $failedRequests
        ], 200);
    }
}
