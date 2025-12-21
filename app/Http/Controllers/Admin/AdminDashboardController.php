<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SystemLog;
use App\Models\AiSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function analytics()
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $suspendedUsers = User::where('status', 'suspended')->count();
        $students = User::where('role', 'student')->count();
        $admins = User::where('role', 'admin')->count();
        
        // Recent users (last 30 days)
        $recentUsers = User::where('created_at', '>=', now()->subDays(30))->count();
        
        // AI usage stats (if AI sessions exist)
        try {
            $aiSessionsCount = AiSession::count();
            $recentAiSessions = AiSession::where('created_at', '>=', now()->subDays(7))->count();
        } catch (\Exception $e) {
            // Table doesn't exist yet
            $aiSessionsCount = 0;
            $recentAiSessions = 0;
        }
        
        // System performance - recent errors
        $recentErrors = SystemLog::where('level', 'error')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        
        // Subscription stats (placeholder - implement when subscriptions are ready)
        $subscriptionStats = [
            'total' => 0,
            'active' => 0,
            'cancelled' => 0,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'suspended' => $suspendedUsers,
                    'students' => $students,
                    'admins' => $admins,
                    'recent_30_days' => $recentUsers,
                ],
                'ai_usage' => [
                    'total_sessions' => $aiSessionsCount,
                    'recent_7_days' => $recentAiSessions,
                ],
                'subscriptions' => $subscriptionStats,
                'system' => [
                    'recent_errors' => $recentErrors,
                ],
            ]
        ], 200);
    }
}
