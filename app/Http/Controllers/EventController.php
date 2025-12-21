<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    /**
     * List events for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $events = Event::where('user_id', $request->user()->id)
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Get a single event.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $event = Event::where('user_id', $request->user()->id)->find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $event,
        ]);
    }

    /**
     * Create an event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'reminder_before' => 'nullable|string|max:50',
        ]);

        $event = Event::create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        // Auto-sync to Google Calendar if user has Google connected
        if ($request->user()->google_access_token) {
            try {
                $accessToken = decrypt($request->user()->google_access_token);
                
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

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ])->post('https://www.googleapis.com/calendar/v3/calendars/primary/events', $googleEvent);

                if ($response->successful()) {
                    $googleEventData = $response->json();
                    $event->google_event_id = $googleEventData['id'];
                    $event->save();
                }
                
            } catch (\Exception $e) {
                Log::warning("Failed to sync new event to Google Calendar: " . $e->getMessage());
                // Continue even if Google sync fails
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully.',
            'data' => $event,
        ], 201);
    }

    /**
     * Update an event.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $event = Event::where('user_id', $request->user()->id)->find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
            'time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'reminder_before' => 'nullable|string|max:50',
        ]);

        $event->update($validated);
        $event->refresh(); // Refresh to get updated data

        // Auto-sync to Google Calendar if event was previously synced
        if ($event->google_event_id && $request->user()->google_access_token) {
            try {
                $accessToken = decrypt($request->user()->google_access_token);
                
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

                Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ])->put("https://www.googleapis.com/calendar/v3/calendars/primary/events/{$event->google_event_id}", $googleEvent);
                
            } catch (\Exception $e) {
                Log::warning("Failed to update event in Google Calendar: " . $e->getMessage());
                // Continue even if Google sync fails
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully.',
            'data' => $event,
        ]);
    }

    /**
     * Delete an event.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $event = Event::where('user_id', $request->user()->id)->find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found.',
            ], 404);
        }

        // Delete from Google Calendar if synced
        if ($event->google_event_id && $request->user()->google_access_token) {
            try {
                $accessToken = decrypt($request->user()->google_access_token);
                
                Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                ])->delete("https://www.googleapis.com/calendar/v3/calendars/primary/events/{$event->google_event_id}");
                
            } catch (\Exception $e) {
                Log::warning("Failed to delete event from Google Calendar: " . $e->getMessage());
                // Continue with local deletion even if Google deletion fails
            }
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully.',
        ]);
    }
}

