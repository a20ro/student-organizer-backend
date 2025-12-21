<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarController extends Controller
{
    /**
     * Get Google Calendar access token (refresh if needed)
     */
    private function getAccessToken($user): ?string
    {
        if (!$user->google_access_token) {
            return null;
        }

        try {
            $accessToken = decrypt($user->google_access_token);
            
            // Try to refresh token if expired (simplified - in production, check expiry)
            if ($user->google_refresh_token) {
                // For now, return the access token
                // In production, implement token refresh logic
                return $accessToken;
            }

            return $accessToken;
        } catch (\Exception $e) {
            Log::error("Failed to decrypt Google access token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sync event to Google Calendar
     */
    public function syncEvent(Request $request, int $eventId): JsonResponse
    {
        $user = $request->user();
        $event = Event::where('user_id', $user->id)->findOrFail($eventId);

        $accessToken = $this->getAccessToken($user);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Google account not connected. Please connect your Google account first.',
            ], 400);
        }

        try {
            // Prepare event data for Google Calendar
            $startDateTime = $event->date->format('Y-m-d') . 'T' . ($event->time ? $event->time->format('H:i:s') : '09:00:00');
            $endTime = $event->time ? $event->time->copy()->addHour() : now()->setTime(10, 0, 0);
            $endDateTime = $event->date->format('Y-m-d') . 'T' . $endTime->format('H:i:s');

            $googleEvent = [
                'summary' => $event->title,
                'description' => $event->description ?? '',
                'start' => [
                    'dateTime' => $startDateTime,
                    'timeZone' => 'UTC',
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => 'UTC',
                ],
            ];

            if ($event->location) {
                $googleEvent['location'] = $event->location;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ];

            // If event already synced, update it; otherwise create new
            if ($event->google_event_id) {
                // Update existing event
                $response = Http::withHeaders($headers)
                    ->put("https://www.googleapis.com/calendar/v3/calendars/primary/events/{$event->google_event_id}", $googleEvent);
            } else {
                // Create new event
                $response = Http::withHeaders($headers)
                    ->post('https://www.googleapis.com/calendar/v3/calendars/primary/events', $googleEvent);
            }

            if ($response->successful()) {
                $googleEventData = $response->json();
                
                // Update event with Google event ID
                $event->google_event_id = $googleEventData['id'];
                $event->save();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Event synced to Google Calendar successfully.',
                    'data' => [
                        'event' => $event->fresh(),
                        'google_event_id' => $googleEventData['id'],
                        'google_event_link' => $googleEventData['htmlLink'] ?? null,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to sync event to Google Calendar.',
                    'error' => $response->json(),
                ], $response->status());
            }

        } catch (\Exception $e) {
            Log::error("Google Calendar sync error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync event to Google Calendar.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync assessment to Google Calendar
     */
    public function syncAssessment(Request $request, int $assessmentId): JsonResponse
    {
        $user = $request->user();
        $assessment = Assessment::with('course.semester')->findOrFail($assessmentId);

        // Check ownership
        if ($assessment->course->semester->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $accessToken = $this->getAccessToken($user);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Google account not connected. Please connect your Google account first.',
            ], 400);
        }

        try {
            $dueDate = $assessment->due_date ? $assessment->due_date->format('Y-m-d') : now()->format('Y-m-d');
            $startDateTime = $dueDate . 'T09:00:00';
            $endDateTime = $dueDate . 'T10:00:00';

            $googleEvent = [
                'summary' => $assessment->title . ' - ' . $assessment->course->name,
                'description' => "Type: {$assessment->type}\nCourse: {$assessment->course->name}",
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

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post('https://www.googleapis.com/calendar/v3/calendars/primary/events', $googleEvent);

            if ($response->successful()) {
                $googleEventData = $response->json();

                // Persist Google event id on assessment
                $assessment->google_event_id = $googleEventData['id'];
                $assessment->save();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Assessment synced to Google Calendar successfully.',
                    'data' => [
                        'assessment' => $assessment->fresh(),
                        'google_event_id' => $googleEventData['id'],
                        'google_event_link' => $googleEventData['htmlLink'] ?? null,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to sync assessment to Google Calendar.',
                    'error' => $response->json(),
                ], $response->status());
            }

        } catch (\Exception $e) {
            Log::error("Google Calendar sync error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync assessment to Google Calendar.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
