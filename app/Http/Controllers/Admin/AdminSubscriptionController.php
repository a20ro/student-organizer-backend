<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function index()
    {
        // Placeholder - implement when subscriptions are ready
        return response()->json([
            'success' => true,
            'message' => 'Subscriptions feature coming soon',
            'data' => [
                'subscriptions' => [],
                'revenue' => [
                    'total' => 0,
                    'monthly' => 0,
                    'yearly' => 0,
                ]
            ]
        ], 200);
    }
}
