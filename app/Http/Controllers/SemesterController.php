<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SemesterController extends Controller
{
    /**
     * List all semesters for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $semesters = Semester::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $semesters,
        ]);
    }

    /**
     * Get a single semester for the authenticated user.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $semester = Semester::where('user_id', $request->user()->id)
            ->find($id);

        if (!$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Semester not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $semester,
        ]);
    }

    /**
     * Create a semester for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
        ]);

        $semester = Semester::create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Semester created successfully.',
            'data' => $semester,
        ], 201);
    }

    /**
     * Update a semester for the authenticated user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $semester = Semester::where('user_id', $request->user()->id)
            ->find($id);

        if (!$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Semester not found.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
        ]);

        $semester->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Semester updated successfully.',
            'data' => $semester,
        ]);
    }

    /**
     * Delete a semester for the authenticated user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $semester = Semester::where('user_id', $request->user()->id)
            ->find($id);

        if (!$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Semester not found.',
            ], 404);
        }

        $semester->delete();

        return response()->json([
            'success' => true,
            'message' => 'Semester deleted successfully.',
        ]);
    }
}

