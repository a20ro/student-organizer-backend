<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

class AdminAnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with(['admin', 'targetUser'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $announcements
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'audience' => 'required|in:all,students,single',
            'target_user_id' => 'required_if:audience,single|exists:users,id',
            'scheduled_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $announcement = Announcement::create([
            'admin_id' => $request->user()->id,
            'title' => $request->title,
            'message' => $request->message,
            'audience' => $request->audience,
            'target_user_id' => $request->target_user_id,
            'scheduled_at' => $request->scheduled_at,
        ]);

        // If not scheduled, send immediately
        $result = null;
        if (!$request->scheduled_at || now() >= $request->scheduled_at) {
            $result = $this->sendAnnouncement($announcement);
        }

        $response = [
            'success' => true,
            'message' => 'Announcement created successfully',
            'data' => $announcement->load(['admin', 'targetUser'])
        ];

        if ($result) {
            $response['email_stats'] = $result;
        }

        // Log the action
        SystemLog::create([
            'admin_id' => $request->user()->id,
            'type' => 'announcement_create',
            'level' => 'info',
            'message' => "Created announcement: {$announcement->title}",
            'context' => [
                'announcement_id' => $announcement->id,
                'title' => $announcement->title,
                'audience' => $announcement->audience,
                'target_user_id' => $announcement->target_user_id,
                'scheduled_at' => $announcement->scheduled_at,
                'sent_count' => $result['sent'] ?? 0,
                'failed_count' => $result['failed'] ?? 0,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json($response, 201);
    }

    public function show($id)
    {
        $announcement = Announcement::with(['admin', 'targetUser'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $announcement
        ], 200);
    }

    public function send($id)
    {
        $announcement = Announcement::findOrFail($id);
        
        if ($announcement->sent_at) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement already sent'
            ], 400);
        }

        $result = $this->sendAnnouncement($announcement);

        // Log the action
        SystemLog::create([
            'admin_id' => request()->user()->id,
            'type' => 'announcement_send',
            'level' => 'info',
            'message' => "Sent announcement: {$announcement->title}",
            'context' => [
                'announcement_id' => $announcement->id,
                'title' => $announcement->title,
                'audience' => $announcement->audience,
                'sent_count' => $result['sent'],
                'failed_count' => $result['failed'],
                'total' => $result['total'],
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Announcement sent successfully',
            'data' => $announcement,
            'email_stats' => $result
        ], 200);
    }

    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);
        
        // Store info before deletion for logging
        $announcementInfo = [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'audience' => $announcement->audience,
        ];
        
        $announcement->delete();

        // Log the action
        SystemLog::create([
            'admin_id' => request()->user()->id,
            'type' => 'announcement_delete',
            'level' => 'warning',
            'message' => "Deleted announcement: {$announcementInfo['title']}",
            'context' => $announcementInfo,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Announcement deleted successfully'
        ], 200);
    }

    private function sendAnnouncement(Announcement $announcement)
    {
        $users = collect();

        if ($announcement->audience === 'all') {
            $users = User::where('status', 'active')->get();
        } elseif ($announcement->audience === 'students') {
            $users = User::where('role', 'student')->where('status', 'active')->get();
        } elseif ($announcement->audience === 'single' && $announcement->target_user_id) {
            $users = User::where('id', $announcement->target_user_id)->get();
        }

        $sentCount = 0;
        $failedCount = 0;

        // Send email to each user
        foreach ($users as $user) {
            try {
                Mail::send('emails.announcement', [
                    'announcement' => $announcement,
                    'user' => $user,
                ], function ($message) use ($user, $announcement) {
                    $message->to($user->email, $user->name)
                            ->subject($announcement->title . ' - Student Tracker');
                });
                $sentCount++;
            } catch (\Exception $e) {
                \Log::error('Failed to send announcement email to ' . $user->email . ': ' . $e->getMessage());
                $failedCount++;
            }
        }

        // Mark as sent
        $announcement->sent_at = now();
        $announcement->save();

        return [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total' => $users->count(),
        ];
    }
}
