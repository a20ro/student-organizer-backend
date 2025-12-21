<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Budget::where('user_id', $request->user()->id);

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }
        if ($request->has('month')) {
            $query->where('month', $request->month);
        }
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $budgets = $query->orderBy('category')->get();

        return response()->json([
            'success' => true,
            'data' => $budgets,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'nullable|string|max:100',
            'amount' => 'required|numeric|min:0',
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $budget = Budget::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'category' => $validated['category'] ?? null,
                'year' => $validated['year'],
                'month' => $validated['month'],
            ],
            ['amount' => $validated['amount']]
        );

        return response()->json([
            'success' => true,
            'message' => 'Budget set successfully.',
            'data' => $budget,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $budget = Budget::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $budget->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Budget updated successfully.',
            'data' => $budget,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $budget = Budget::where('user_id', $request->user()->id)->findOrFail($id);
        $budget->delete();

        return response()->json([
            'success' => true,
            'message' => 'Budget deleted successfully.',
        ]);
    }
}
