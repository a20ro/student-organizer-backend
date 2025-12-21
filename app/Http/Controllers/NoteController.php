<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NoteController extends Controller
{
    public function index(Request $request, int $courseId): JsonResponse
    {
        $course = Course::with('semester')->find($courseId);

        if (!$course || $course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found.',
            ], 404);
        }

        $query = $course->notes();

        // Filter by pinned/favorite
        if ($request->has('pinned')) {
            $query->where('is_pinned', $request->boolean('pinned'));
        }
        if ($request->has('favorite')) {
            $query->where('is_favorite', $request->boolean('favorite'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $notes = $query->with('fileAttachments')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    public function show(Request $request, int $noteId): JsonResponse
    {
        $note = Note::with(['course.semester', 'fileAttachments'])->find($noteId);

        if (!$note || $note->course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $note,
        ]);
    }

    public function store(Request $request, int $courseId): JsonResponse
    {
        $course = Course::with('semester')->find($courseId);

        if (!$course || $course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'week_number' => 'nullable|integer|min:1|max:52',
            'attachments' => 'nullable|array',
            'tags' => 'nullable|array',
        ]);

        $note = $course->notes()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Note created successfully.',
            'data' => $note,
        ], 201);
    }

    public function update(Request $request, int $noteId): JsonResponse
    {
        $note = Note::with('course.semester')->find($noteId);

        if (!$note || $note->course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'nullable|string',
            'week_number' => 'nullable|integer|min:1|max:52',
            'attachments' => 'nullable|array',
            'is_pinned' => 'nullable|boolean',
            'is_favorite' => 'nullable|boolean',
            'tags' => 'nullable|array',
        ]);

        $note->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully.',
            'data' => $note->fresh(),
        ]);
    }

    public function destroy(Request $request, int $noteId): JsonResponse
    {
        $note = Note::with('course.semester')->find($noteId);

        if (!$note || $note->course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found.',
            ], 404);
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully.',
        ]);
    }

    public function togglePin(Request $request, int $noteId): JsonResponse
    {
        $note = Note::with('course.semester')->find($noteId);

        if (!$note || $note->course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found.',
            ], 404);
        }

        $note->is_pinned = !$note->is_pinned;
        $note->save();

        return response()->json([
            'success' => true,
            'message' => $note->is_pinned ? 'Note pinned.' : 'Note unpinned.',
            'data' => $note,
        ]);
    }

    public function toggleFavorite(Request $request, int $noteId): JsonResponse
    {
        $note = Note::with('course.semester')->find($noteId);

        if (!$note || $note->course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found.',
            ], 404);
        }

        $note->is_favorite = !$note->is_favorite;
        $note->save();

        return response()->json([
            'success' => true,
            'message' => $note->is_favorite ? 'Note favorited.' : 'Note unfavorited.',
            'data' => $note,
        ]);
    }

    public function generateShareLink(Request $request, int $noteId): JsonResponse
    {
        $note = Note::with('course.semester')->find($noteId);

        if (!$note || $note->course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found.',
            ], 404);
        }

        $note->share_token = Str::random(32);
        $note->is_public = true;
        $note->save();

        return response()->json([
            'success' => true,
            'message' => 'Share link generated.',
            'data' => [
                'note' => $note,
                'share_url' => url("/api/notes/public/{$note->share_token}"),
            ],
        ]);
    }

    public function revokeShareLink(Request $request, int $noteId): JsonResponse
    {
        $note = Note::with('course.semester')->find($noteId);

        if (!$note || $note->course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found.',
            ], 404);
        }

        $note->share_token = null;
        $note->is_public = false;
        $note->save();

        return response()->json([
            'success' => true,
            'message' => 'Share link revoked.',
            'data' => $note,
        ]);
    }

    public function showPublic(string $token): JsonResponse
    {
        $note = Note::where('share_token', $token)
            ->where('is_public', true)
            ->with(['course'])
            ->first();

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found or not public.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $note,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Note::whereHas('course.semester', function($q) use ($userId) {
            $q->where('user_id', $userId);
        });

        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->has('tags')) {
            $tags = is_array($request->tags) ? $request->tags : [$request->tags];
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        $notes = $query->with(['course'])->get();

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }
}
