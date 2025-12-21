<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssessmentController extends Controller
{
    /**
     * List assessments for a course owned by the authenticated user.
     */
    public function index(Request $request, int $courseId): JsonResponse
    {
        $course = Course::with('semester')
            ->find($courseId);

        if (!$course || $course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found.',
            ], 404);
        }

        $assessments = $course->assessments()
            ->with('fileAttachments')
            ->orderByDesc('due_date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $assessments,
        ]);
    }

    /**
     * Create an assessment for a course owned by the authenticated user.
     */
    public function store(Request $request, int $courseId): JsonResponse
    {
        $course = Course::with('semester')
            ->find($courseId);

        if (!$course || $course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:quiz,midterm,final,assignment,project',
            'grade_received' => 'nullable|numeric|min:0',
            'grade_max' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'weight_percentage' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|string|in:not_started,in_progress,completed,submitted,graded',
        ]);

        // Set default status if not provided
        if (!isset($validated['status'])) {
            $validated['status'] = 'not_started';
        }

        $assessment = $course->assessments()->create($validated);
        // Set course relation for later access
        $assessment->setRelation('course', $course);

        // Auto-sync to Google Calendar if user connected (skip if already submitted)
        if ($assessment->status !== 'submitted') {
            $this->syncAssessmentToGoogle($request->user(), $assessment);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assessment created successfully.',
            'data' => $assessment,
        ], 201);
    }

    /**
     * Get a single assessment (only if it belongs to the user).
     */
    public function show(Request $request, int $assessmentId): JsonResponse
    {
        $assessment = Assessment::with(['course.semester', 'fileAttachments'])->find($assessmentId);

        if (!$assessment || $assessment->course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Assessment not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $assessment,
        ]);
    }

    /**
     * Update an assessment (only if it belongs to the user).
     */
    public function update(Request $request, int $assessmentId): JsonResponse
    {
        $assessment = Assessment::with('course.semester')->find($assessmentId);

        if (!$assessment || $assessment->course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Assessment not found.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|in:quiz,midterm,final,assignment,project',
            'grade_received' => 'nullable|numeric|min:0',
            'grade_max' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'weight_percentage' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|string|in:not_started,in_progress,completed,submitted,graded',
        ]);

        // Check if status is being changed to "submitted"
        $isBeingSubmitted = isset($validated['status']) && $validated['status'] === 'submitted';

        $assessment->update($validated);
        $assessment->refresh();

        // If status changed to "submitted", remove from Google Calendar
        if ($isBeingSubmitted) {
            $this->removeAssessmentFromGoogle($request->user(), $assessment);
        } else {
            // Auto-sync to Google Calendar if user connected
            $this->syncAssessmentToGoogle($request->user(), $assessment);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assessment updated successfully.',
            'data' => $assessment,
        ]);
    }

    /**
     * Delete an assessment (only if it belongs to the user).
     */
    public function destroy(Request $request, int $assessmentId): JsonResponse
    {
        $assessment = Assessment::with('course.semester')->find($assessmentId);

        if (!$assessment || $assessment->course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Assessment not found.',
            ], 404);
        }

        // Remove from Google Calendar if synced
        if ($assessment->google_event_id && $request->user()->google_access_token) {
            try {
                $accessToken = decrypt($request->user()->google_access_token);
                Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                ])->delete("https://www.googleapis.com/calendar/v3/calendars/primary/events/{$assessment->google_event_id}");
            } catch (\Exception $e) {
                Log::warning('Failed to delete assessment event from Google Calendar: ' . $e->getMessage(), [
                    'assessment_id' => $assessment->id,
                ]);
            }
        }

        $assessment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assessment deleted successfully.',
        ]);
    }

    /**
     * Remove assessment from Google Calendar when status is "submitted".
     * Non-blocking: logs errors but does not fail the main request.
     */
    private function removeAssessmentFromGoogle(User $user, Assessment $assessment): void
    {
        try {
            if (!$user->google_access_token || !$assessment->google_event_id) {
                return;
            }

            $accessToken = decrypt($user->google_access_token);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->delete("https://www.googleapis.com/calendar/v3/calendars/primary/events/{$assessment->google_event_id}");

            if ($response->successful() || $response->status() === 410) {
                // Clear the google_event_id since it's been removed
                $assessment->google_event_id = null;
                $assessment->save();
                
                Log::info('Assessment removed from Google Calendar (status: submitted)', [
                    'assessment_id' => $assessment->id,
                ]);
            } else {
                Log::warning('Failed to remove assessment from Google Calendar', [
                    'assessment_id' => $assessment->id,
                    'response' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Google Calendar removal error for assessment: ' . $e->getMessage(), [
                'assessment_id' => $assessment->id,
            ]);
        }
    }

    /**
     * Sync assessment to Google Calendar for connected users.
     * Non-blocking: logs errors but does not fail the main request.
     */
    private function syncAssessmentToGoogle(User $user, Assessment $assessment): void
    {
        try {
            if (!$user->google_access_token) {
                return;
            }

            $course = $assessment->course ?? $assessment->load('course.semester')->course;
            if (!$course || !$course->semester || $course->semester->user_id !== $user->id) {
                return;
            }

            $accessToken = decrypt($user->google_access_token);

            $dueDate = $assessment->due_date ? $assessment->due_date->format('Y-m-d') : now()->format('Y-m-d');
            $startDateTime = $dueDate . 'T09:00:00';
            $endDateTime = $dueDate . 'T10:00:00';

            $googleEvent = [
                'summary' => $assessment->title . ' - ' . $course->name,
                'description' => "Type: {$assessment->type}\nCourse: {$course->name}",
                'start' => [
                    'dateTime' => $startDateTime,
                    'timeZone' => 'UTC',
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => 'UTC',
                ],
            ];

            if ($assessment->grade_max) {
                $googleEvent['description'] .= "\nMax Grade: {$assessment->grade_max}";
            }

            $headers = [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ];

            // Update existing event if google_event_id exists, else create new
            if ($assessment->google_event_id) {
                $response = Http::withHeaders($headers)
                    ->put("https://www.googleapis.com/calendar/v3/calendars/primary/events/{$assessment->google_event_id}", $googleEvent);
            } else {
                $response = Http::withHeaders($headers)
                    ->post('https://www.googleapis.com/calendar/v3/calendars/primary/events', $googleEvent);
            }

            if ($response->successful()) {
                $googleEventData = $response->json();
                $assessment->google_event_id = $googleEventData['id'] ?? $assessment->google_event_id;
                $assessment->save();
            } else {
                Log::warning('Google Calendar sync failed for assessment', [
                    'assessment_id' => $assessment->id,
                    'response' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Google Calendar sync error for assessment: ' . $e->getMessage(), [
                'assessment_id' => $assessment->id,
            ]);
        }
    }
}

