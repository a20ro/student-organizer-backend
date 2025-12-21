<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * List courses for a semester owned by the authenticated user.
     */
    public function index(Request $request, int $semesterId): JsonResponse
    {
        $semester = Semester::where('user_id', $request->user()->id)->find($semesterId);

        if (!$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Semester not found.',
            ], 404);
        }

        $courses = $semester->courses()->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $courses,
        ]);
    }

    /**
     * Create a course under a semester owned by the authenticated user.
     */
    public function store(Request $request, int $semesterId): JsonResponse
    {
        $semester = Semester::where('user_id', $request->user()->id)->find($semesterId);

        if (!$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Semester not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'instructor' => 'nullable|string|max:255',
            'credit_hours' => 'nullable|integer|min:0|max:30',
            'room' => 'nullable|string|max:100',
            'color_tag' => 'nullable|string|max:32',
        ]);

        $course = $semester->courses()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully.',
            'data' => $course,
        ], 201);
    }

    /**
     * Update a course (only if it belongs to a semester owned by the user).
     */
    public function update(Request $request, int $courseId): JsonResponse
    {
        $course = Course::with('semester')->find($courseId);

        if (!$course || $course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50',
            'instructor' => 'nullable|string|max:255',
            'credit_hours' => 'nullable|integer|min:0|max:30',
            'room' => 'nullable|string|max:100',
            'color_tag' => 'nullable|string|max:32',
        ]);

        $course->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully.',
            'data' => $course,
        ]);
    }

    /**
     * Delete a course (only if it belongs to a semester owned by the user).
     */
    public function destroy(Request $request, int $courseId): JsonResponse
    {
        $course = Course::with('semester')->find($courseId);

        if (!$course || $course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found.',
            ], 404);
        }

        $course->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully.',
        ]);
    }
}

