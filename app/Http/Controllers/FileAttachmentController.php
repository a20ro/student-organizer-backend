<?php

namespace App\Http\Controllers;

use App\Models\FileAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FileAttachmentController extends Controller
{
    /**
     * Get all attachments for a note or assessment
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attachable_type' => 'required|string|in:Note,Assessment',
            'attachable_id' => 'required|integer',
        ]);

        $modelClass = 'App\\Models\\' . $validated['attachable_type'];
        $attachable = $modelClass::with('course.semester')->find($validated['attachable_id']);
        
        if (!$attachable || $attachable->course->semester->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($validated['attachable_type']) . ' not found.',
            ], 404);
        }

        $attachments = FileAttachment::where('attachable_type', 'App\\Models\\' . $validated['attachable_type'])
            ->where('attachable_id', $validated['attachable_id'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attachments,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        try {
            // Log incoming request for debugging
            \Log::info('File upload request', [
                'has_file' => $request->hasFile('file'),
                'attachable_type' => $request->input('attachable_type'),
                'attachable_id' => $request->input('attachable_id'),
                'all_input' => $request->all(),
            ]);

            // Convert attachable_id to integer if it's a string
            $attachableId = $request->input('attachable_id');
            if (is_string($attachableId)) {
                $request->merge(['attachable_id' => (int) $attachableId]);
            }

            $validated = $request->validate([
                'file' => 'required|file|max:10240', // 10MB max - removed mimes for now to be more permissive
                'attachable_type' => 'required|string|in:Note,Assessment',
                'attachable_id' => 'required|integer',
            ], [
                'file.required' => 'Please select a file to upload.',
                'file.file' => 'The uploaded file is invalid.',
                'file.max' => 'The file size must not exceed 10MB.',
                'attachable_type.required' => 'Attachable type is required.',
                'attachable_type.in' => 'Attachable type must be either Note or Assessment.',
                'attachable_id.required' => 'Attachable ID is required.',
                'attachable_id.integer' => 'Attachable ID must be a number.',
            ]);

            // Validate attachable_id exists and belongs to user
            $modelClass = 'App\\Models\\' . $validated['attachable_type'];
            $attachable = $modelClass::with('course.semester')->find($validated['attachable_id']);
            
            if (!$attachable) {
                return response()->json([
                    'success' => false,
                    'message' => ucfirst($validated['attachable_type']) . ' not found.',
                ], 404);
            }

            // Check ownership through course->semester->user_id
            $course = $attachable->course;
            if (!$course || $course->semester->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You can only attach files to your own ' . strtolower($validated['attachable_type']) . 's.',
                ], 403);
            }

            $file = $request->file('file');
            
            if (!$file || !$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file upload.',
                ], 400);
            }

            $fileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('attachments', $fileName, 'public');

            if (!$filePath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save file to storage.',
                ], 500);
            }

            $attachment = FileAttachment::create([
                'attachable_type' => 'App\\Models\\' . $validated['attachable_type'],
                'attachable_id' => $validated['attachable_id'],
                'user_id' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'file_name' => $fileName,
                'file_path' => $filePath,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully.',
                'data' => $attachment,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('File upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred during file upload.',
            ], 500);
        }
    }

    public function download(Request $request, int $id): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        try {
            $attachment = FileAttachment::findOrFail($id);

            // Check ownership
            if ($attachment->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            // Check if file exists
            if (!Storage::disk('public')->exists($attachment->file_path)) {
                \Log::error('File not found in storage', [
                    'attachment_id' => $attachment->id,
                    'file_path' => $attachment->file_path,
                    'full_path' => storage_path('app/public/' . $attachment->file_path),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'File not found in storage.',
                    'file_path' => $attachment->file_path,
                ], 404);
            }

            return Storage::disk('public')->download($attachment->file_path, $attachment->original_name);
        } catch (\Exception $e) {
            \Log::error('File download error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to download file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $attachment = FileAttachment::findOrFail($id);

            if ($attachment->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            // Delete file from storage (if exists)
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            // Delete database record
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('File delete error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file: ' . $e->getMessage(),
            ], 500);
        }
    }
}
