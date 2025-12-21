<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HabitController extends Controller
{
    /**
     * List habits with recent logs.
     */
    public function index(Request $request): JsonResponse
    {
        $habits = Habit::where('user_id', $request->user()->id)
            ->with(['logs' => function ($q) {
                $q->orderByDesc('date')->limit(30);
            }])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $habits,
        ]);
    }

    /**
     * Create habit.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'frequency_type' => 'required|string|in:daily,weekly,monthly',
            'target_count' => 'nullable|integer|min:1',
        ]);

        $habit = Habit::create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Habit created successfully.',
            'data' => $habit,
        ], 201);
    }

    /**
     * Update habit.
     */
    public function update(Request $request, int $habitId): JsonResponse
    {
        $habit = Habit::where('user_id', $request->user()->id)->find($habitId);

        if (!$habit) {
            return response()->json([
                'success' => false,
                'message' => 'Habit not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'frequency_type' => 'sometimes|required|string|in:daily,weekly,monthly',
            'target_count' => 'nullable|integer|min:1',
        ]);

        $habit->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Habit updated successfully.',
            'data' => $habit->fresh(),
        ]);
    }

    /**
     * Mark habit for today (increment count by 1).
     */
    public function markToday(Request $request, int $habitId): JsonResponse
    {
        $habit = Habit::where('user_id', $request->user()->id)->find($habitId);

        if (!$habit) {
            return response()->json([
                'success' => false,
                'message' => 'Habit not found.',
            ], 404);
        }

        $today = Carbon::today()->toDateString();

        $log = HabitLog::firstOrNew([
            'habit_id' => $habit->id,
            'date' => $today,
        ]);

        $log->count = ($log->count ?? 0) + 1;
        $log->save();

        return response()->json([
            'success' => true,
            'message' => 'Habit marked for today.',
            'data' => $log,
        ]);
    }

    /**
     * Get habit history (logs).
     */
    public function history(Request $request, int $habitId): JsonResponse
    {
        $habit = Habit::where('user_id', $request->user()->id)->find($habitId);

        if (!$habit) {
            return response()->json([
                'success' => false,
                'message' => 'Habit not found.',
            ], 404);
        }

        $logs = HabitLog::where('habit_id', $habitId)
            ->orderByDesc('date')
            ->limit(90)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'habit' => $habit,
                'logs' => $logs,
            ],
        ]);
    }

    /**
     * Delete habit (logs will cascade).
     */
    public function destroy(Request $request, int $habitId): JsonResponse
    {
        $habit = Habit::where('user_id', $request->user()->id)->find($habitId);

        if (!$habit) {
            return response()->json([
                'success' => false,
                'message' => 'Habit not found.',
            ], 404);
        }

        $habit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Habit deleted successfully.',
        ]);
    }
}

