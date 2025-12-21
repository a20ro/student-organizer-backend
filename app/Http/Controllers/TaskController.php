<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * List all tasks for the authenticated user (optionally filtered by goal_id).
     */
    public function listAll(Request $request): JsonResponse
    {
        $query = Task::where('user_id', $request->user()->id)
            ->with(['goal', 'parent', 'children']);

        // Optional filter by goal_id
        if ($request->has('goal_id')) {
            $goalId = $request->query('goal_id');
            if ($goalId === 'null' || $goalId === null) {
                // Filter for standalone tasks (no goal)
                $query->whereNull('goal_id');
            } else {
                // Filter for tasks in a specific goal
                $goal = Goal::where('user_id', $request->user()->id)->find($goalId);
                if (!$goal) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Goal not found.',
                    ], 404);
                }
                $query->where('goal_id', $goalId);
            }
        }

        $tasks = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Create a standalone task (goal_id optional in request body).
     */
    public function storeStandalone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'goal_id' => 'nullable|integer|exists:goals,id',
            'parent_task_id' => 'nullable|integer',
        ]);

        // If goal_id is provided, verify it belongs to the user
        if (!empty($validated['goal_id'])) {
            $goal = Goal::where('user_id', $request->user()->id)
                ->find($validated['goal_id']);

            if (!$goal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Goal not found.',
                ], 404);
            }
        }

        // If parent_task_id is provided, ensure it belongs to the same user
        if (!empty($validated['parent_task_id'])) {
            $parentQuery = Task::where('user_id', $request->user()->id)
                ->find($validated['parent_task_id']);

            if (!$parentQuery) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent task not found.',
                ], 404);
            }

            // If goal_id is provided, ensure parent task belongs to the same goal
            if (!empty($validated['goal_id'])) {
                $parent = Task::where('user_id', $request->user()->id)
                    ->where('goal_id', $validated['goal_id'])
                    ->find($validated['parent_task_id']);

                if (!$parent) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent task must belong to the same goal.',
                    ], 400);
                }
            } else {
                // For standalone tasks, parent must also be standalone
                $parent = Task::where('user_id', $request->user()->id)
                    ->whereNull('goal_id')
                    ->find($validated['parent_task_id']);

                if (!$parent) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent task must be a standalone task.',
                    ], 400);
                }
            }
        }

        $task = Task::create([
            'user_id' => $request->user()->id,
            'completed' => false,
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully.',
            'data' => $task->load(['goal', 'parent', 'children']),
        ], 201);
    }

    /**
     * List tasks for a goal (owned by the authenticated user).
     */
    public function index(Request $request, int $goalId): JsonResponse
    {
        $goal = Goal::where('user_id', $request->user()->id)->find($goalId);

        if (!$goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        $tasks = $goal->tasks()
            ->with('children')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Create a task under a goal (or as a subtask if parent_task_id provided).
     */
    public function store(Request $request, int $goalId): JsonResponse
    {
        $goal = Goal::where('user_id', $request->user()->id)->find($goalId);

        if (!$goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'parent_task_id' => 'nullable|integer',
        ]);

        // If parent_task_id is provided, ensure it belongs to the same user and goal
        if (!empty($validated['parent_task_id'])) {
            $parent = Task::where('user_id', $request->user()->id)
                ->where('goal_id', $goalId)
                ->find($validated['parent_task_id']);

            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent task not found.',
                ], 404);
            }
        }

        $task = Task::create([
            'goal_id' => $goalId,
            'user_id' => $request->user()->id,
            'completed' => false,
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully.',
            'data' => $task,
        ], 201);
    }

    /**
     * Update a task (or its subtask flag).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $task = Task::where('user_id', $request->user()->id)->find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'completed' => 'nullable|boolean',
            'goal_id' => 'nullable|integer|exists:goals,id',
            'parent_task_id' => 'nullable|integer',
        ]);

        // Determine the goal_id to use for validation (new value if being updated, otherwise current)
        $goalIdForValidation = array_key_exists('goal_id', $validated) 
            ? $validated['goal_id'] 
            : $task->goal_id;

        // If goal_id is being updated, verify it belongs to the user
        if (array_key_exists('goal_id', $validated) && $validated['goal_id'] !== null) {
            $goal = Goal::where('user_id', $request->user()->id)
                ->find($validated['goal_id']);

            if (!$goal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Goal not found.',
                ], 404);
            }
        }

        if (array_key_exists('parent_task_id', $validated) && $validated['parent_task_id']) {
            // Validate parent task based on the goal_id (new or current)
            if ($goalIdForValidation) {
                // Task has a goal, parent must belong to the same goal
                $parent = Task::where('user_id', $request->user()->id)
                    ->where('goal_id', $goalIdForValidation)
                    ->find($validated['parent_task_id']);

                if (!$parent) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent task must belong to the same goal.',
                    ], 400);
                }
            } else {
                // Standalone task, parent must also be standalone
                $parent = Task::where('user_id', $request->user()->id)
                    ->whereNull('goal_id')
                    ->find($validated['parent_task_id']);

                if (!$parent) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent task must be a standalone task.',
                    ], 400);
                }
            }
        }

        $task->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully.',
            'data' => $task,
        ]);
    }

    /**
     * Mark task as done.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $task = Task::where('user_id', $request->user()->id)->find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
        }

        $task->update(['completed' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Task marked as completed.',
            'data' => $task,
        ]);
    }

    /**
     * Delete task (and its subtasks via cascade/null).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $task = Task::where('user_id', $request->user()->id)->find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully.',
        ]);
    }
}

