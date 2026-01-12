<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Habit;
use App\Models\Semester;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StudentDashboardController extends Controller
{
    /**
     * Get a consolidated summary for the student dashboard.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        // 1. Semesters with Courses
        $semesters = Semester::where('user_id', $user->id)
            ->with('courses')
            ->orderByDesc('created_at')
            ->get();

        // 2. Habits with Today's logs
        $habits = Habit::where('user_id', $user->id)
            ->with(['logs' => function ($query) use ($today) {
                $query->whereDate('date', $today);
            }])
            ->get();

        // 3. Pending Tasks for this week
        $tasks = Task::where('user_id', $user->id)
            ->where('completed', false)
            ->where(function ($query) use ($startOfWeek, $endOfWeek) {
                $query->whereBetween('due_date', [$startOfWeek, $endOfWeek])
                    ->orWhereNull('due_date');
            })
            ->with(['goal', 'parent'])
            ->orderBy('due_date', 'asc')
            ->get();

        // 4. Latest Announcements
        $announcements = Announcement::where(function ($query) use ($user) {
                $query->where('audience', 'everyone')
                    ->orWhere(function ($q) use ($user) {
                        $q->where('audience', 'individual')
                            ->where('target_user_id', $user->id);
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->where('audience', 'students')
                            ->whereHas('targetUser', function ($sub) use ($user) {
                                $sub->where('role', 'student');
                            });
                    });
            })
            ->whereNotNull('sent_at')
            ->orderByDesc('sent_at')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'semesters' => $semesters,
                'habits' => $habits,
                'tasks' => $tasks,
                'announcements' => $announcements,
            ],
        ]);
    }
}
