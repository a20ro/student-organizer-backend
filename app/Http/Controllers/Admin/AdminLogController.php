<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class AdminLogController extends Controller
{
    public function index(Request $request)
    {
        $query = SystemLog::with('admin');

        // Filter by admin_id
        if ($request->has('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }

        // Filter by entity_type (stored in type field or context)
        if ($request->has('entity_type')) {
            $query->where('type', 'like', '%' . $request->entity_type . '%');
        }

        // Filter by action (stored in type field)
        if ($request->has('action')) {
            $query->where('type', $request->action);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by level
        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs
        ], 200);
    }

    public function show($id)
    {
        $log = SystemLog::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $log
        ], 200);
    }

    public function errors()
    {
        $errors = SystemLog::where('level', 'error')
            ->orWhere('level', 'critical')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $errors
        ], 200);
    }

    public function authFailures()
    {
        $failures = SystemLog::where('type', 'auth_failure')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $failures
        ], 200);
    }

    public function apiErrors()
    {
        $errors = SystemLog::where('type', 'api_error')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $errors
        ], 200);
    }
}
