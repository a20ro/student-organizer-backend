<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    /**
     * List goals for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $goals = Goal::where('user_id', $request->user()->id)
            ->with('tasks')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $goals,
        ]);
    }

    /**
     * Create a goal.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'nullable|date',
        ]);

        $goal = Goal::create([
            'user_id' => $request->user()->id,
            'completed' => false,
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Goal created successfully.',
            'data' => $goal,
        ], 201);
    }

    /**
     * Update a goal.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $goal = Goal::where('user_id', $request->user()->id)->find($id);

        if (!$goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'nullable|date',
            'completed' => 'nullable|boolean',
        ]);

        $goal->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Goal updated successfully.',
            'data' => $goal,
        ]);
    }

    /**
     * Delete a goal.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $goal = Goal::where('user_id', $request->user()->id)->find($id);

        if (!$goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        $goal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Goal deleted successfully.',
        ]);
    }

    /**
     * Mark goal complete.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $goal = Goal::where('user_id', $request->user()->id)->find($id);

        if (!$goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        $goal->update(['completed' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Goal marked as completed.',
            'data' => $goal,
        ]);
    }
}

