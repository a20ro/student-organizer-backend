<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    /**
    * List all transactions for the authenticated user.
    */
    public function index(Request $request): JsonResponse
    {
        $transactions = Transaction::where('user_id', $request->user()->id)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
    * Create a transaction (income or expense).
    */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:income,expense',
            'category' => 'nullable|string|max:100',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction created successfully.',
            'data' => $transaction,
        ], 201);
    }

    /**
    * Update a transaction (must belong to the user).
    */
    public function update(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::where('user_id', $request->user()->id)->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found.',
            ], 404);
        }

        $validated = $request->validate([
            'type' => 'sometimes|required|string|in:income,expense',
            'category' => 'nullable|string|max:100',
            'amount' => 'nullable|numeric|min:0',
            'date' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $transaction->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Transaction updated successfully.',
            'data' => $transaction,
        ]);
    }

    /**
    * Delete a transaction (must belong to the user).
    */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::where('user_id', $request->user()->id)->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found.',
            ], 404);
        }

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transaction deleted successfully.',
        ]);
    }

    /**
    * Get balance summary (total income, total expense, net).
    */
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $income = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->sum('amount');

        $expense = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->sum('amount');

        $net = $income - $expense;

        return response()->json([
            'success' => true,
            'data' => [
                'total_income' => (float) $income,
                'total_expense' => (float) $expense,
                'net_balance' => (float) $net,
            ],
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        // Monthly summary
        $monthlyIncome = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');

        $monthlyExpense = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');

        // By category
        $byCategory = Transaction::where('user_id', $userId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->select('category', 'type', DB::raw('SUM(amount) as total'))
            ->groupBy('category', 'type')
            ->get();

        // Budget comparison
        $budgets = Budget::where('user_id', $userId)
            ->where('year', $year)
            ->where('month', $month)
            ->get();

        $budgetComparison = [];
        foreach ($budgets as $budget) {
            $spent = Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->where('category', $budget->category)
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->sum('amount');

            $budgetComparison[] = [
                'category' => $budget->category ?? 'Overall',
                'budget' => (float) $budget->amount,
                'spent' => (float) $spent,
                'remaining' => (float) ($budget->amount - $spent),
                'percentage' => $budget->amount > 0 ? round(($spent / $budget->amount) * 100, 2) : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month,
                'year' => $year,
                'monthly_income' => (float) $monthlyIncome,
                'monthly_expense' => (float) $monthlyExpense,
                'monthly_net' => (float) ($monthlyIncome - $monthlyExpense),
                'by_category' => $byCategory,
                'budget_comparison' => $budgetComparison,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $userId = $request->user()->id;
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $transactions = Transaction::where('user_id', $userId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get();

        $filename = "transactions_{$year}_{$month}.csv";

        return response()->streamDownload(function () use ($transactions) {
            $file = fopen('php://output', 'w');
            
            // Headers
            fputcsv($file, ['Date', 'Type', 'Category', 'Amount', 'Note']);

            // Data
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->date->format('Y-m-d'),
                    $transaction->type,
                    $transaction->category ?? '',
                    $transaction->amount,
                    $transaction->note ?? '',
                ]);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

