<?php

namespace App\Http\Controllers;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RecurringTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $recurring = RecurringTransaction::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $recurring,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:income,expense',
            'category' => 'nullable|string|max:100',
            'amount' => 'required|numeric|min:0',
            'note' => 'nullable|string',
            'frequency' => 'required|string|in:daily,weekly,monthly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $nextOccurrence = $this->calculateNextOccurrence($startDate, $validated['frequency']);

        $recurring = RecurringTransaction::create([
            'user_id' => $request->user()->id,
            ...$validated,
            'next_occurrence' => $nextOccurrence,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recurring transaction created successfully.',
            'data' => $recurring,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $recurring = RecurringTransaction::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|required|string|in:income,expense',
            'category' => 'nullable|string|max:100',
            'amount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
            'frequency' => 'sometimes|required|string|in:daily,weekly,monthly,yearly',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['frequency']) || isset($validated['start_date'])) {
            $startDate = Carbon::parse($validated['start_date'] ?? $recurring->start_date);
            $frequency = $validated['frequency'] ?? $recurring->frequency;
            $validated['next_occurrence'] = $this->calculateNextOccurrence($startDate, $frequency);
        }

        $recurring->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Recurring transaction updated successfully.',
            'data' => $recurring,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $recurring = RecurringTransaction::where('user_id', $request->user()->id)->findOrFail($id);
        $recurring->delete();

        return response()->json([
            'success' => true,
            'message' => 'Recurring transaction deleted successfully.',
        ]);
    }

    private function calculateNextOccurrence(Carbon $startDate, string $frequency): Carbon
    {
        return match($frequency) {
            'daily' => $startDate->copy()->addDay(),
            'weekly' => $startDate->copy()->addWeek(),
            'monthly' => $startDate->copy()->addMonth(),
            'yearly' => $startDate->copy()->addYear(),
            default => $startDate->copy()->addDay(),
        };
    }
}
