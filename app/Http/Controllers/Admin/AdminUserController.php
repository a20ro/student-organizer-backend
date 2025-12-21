<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $users
        ], 200);
    }

    public function show($id)
    {
        $user = User::with(['semesters', 'goals', 'tasks'])->findOrFail($id);

        // Get user activity stats
        try {
            $aiSessionsCount = $user->aiSessions()->count();
        } catch (\Exception $e) {
            $aiSessionsCount = 0;
        }

        $activity = [
            'last_login' => $user->last_login,
            'semesters_count' => $user->semesters()->count(),
            'goals_count' => $user->goals()->count(),
            'tasks_count' => $user->tasks()->count(),
            'ai_sessions_count' => $aiSessionsCount,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'activity' => $activity,
            ]
        ], 200);
    }

    public function updateRole(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:student,admin,super_admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);
        $oldRole = $user->role;
        $user->role = $request->role;
        $user->save();

        // Log the action
        SystemLog::create([
            'admin_id' => $request->user()->id,
            'type' => 'user_role_update',
            'level' => 'info',
            'message' => "Changed user {$user->id} ({$user->name}) role from {$oldRole} to {$request->role}",
            'context' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'old_role' => $oldRole,
                'new_role' => $request->role,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => $user
        ], 200);
    }

    public function suspend($id)
    {
        $user = User::findOrFail($id);
        $currentUser = request()->user();
        
        // Prevent suspending super_admin users (even by super_admin)
        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot suspend super admin users'
            ], 403);
        }
        
        // Regular admins cannot suspend any admin users
        // Super admins can suspend regular admin users
        if ($user->isAdmin() && !$currentUser->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot suspend admin users'
            ], 403);
        }

        $user->status = 'suspended';
        $user->save();

        // Log the action
        SystemLog::create([
            'admin_id' => $currentUser->id,
            'type' => 'user_suspend',
            'level' => 'warning',
            'message' => "Suspended user {$user->id} ({$user->name})",
            'context' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_role' => $user->role,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User suspended successfully',
            'data' => $user
        ], 200);
    }

    public function activate($id)
    {
        $user = User::findOrFail($id);
        $user->status = 'active';
        $user->save();

        // Log the action
        SystemLog::create([
            'admin_id' => request()->user()->id,
            'type' => 'user_activate',
            'level' => 'info',
            'message' => "Activated user {$user->id} ({$user->name})",
            'context' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_role' => $user->role,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully',
            'data' => $user
        ], 200);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $currentUser = request()->user();
        
        // Prevent deleting super_admin users (even by super_admin)
        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete super admin users'
            ], 403);
        }
        
        // Regular admins cannot delete any admin users
        // Super admins can delete regular admin users
        if ($user->isAdmin() && !$currentUser->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete admin users'
            ], 403);
        }

        // Store user info before deletion for logging
        $userInfo = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        $user->delete();

        // Log the action
        SystemLog::create([
            'admin_id' => $currentUser->id,
            'type' => 'user_delete',
            'level' => 'warning',
            'message' => "Deleted user {$userInfo['id']} ({$userInfo['name']})",
            'context' => $userInfo,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ], 200);
    }
}
