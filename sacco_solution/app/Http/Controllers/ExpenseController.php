<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BRD Section 11 "Expenditure": Origination -> Approval -> Authorization
 * (a THREE-stage flow). Origination is store() itself; Approval +
 * Authorization are the two required_approvals on the generic
 * maker-checker request (required_approvals = 2 means two DIFFERENT
 * users must each approve before the expense is actually payable).
 */
class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::where('org_id', $request->query('org_id'))
            ->with('budgetAllocation')->orderByDesc('created_at')->get();
        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'org_id' => 'required|integer|exists:organizations,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'budget_allocation_id' => 'nullable|integer|exists:budget_allocations,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'created_by' => 'required|integer|exists:users,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $expense = Expense::create([
                ...$validated,
                'status' => 'pending',
            ]);

            $approvalRequest = ApprovalRequest::create([
                'org_id' => $validated['org_id'],
                'entity_type' => 'EXPENSE_AUTHORIZATION',
                'entity_id' => $expense->id,
                'action' => 'AUTHORIZE',
                'payload_json' => ['amount' => (float) $validated['amount']],
                'required_approvals' => 2,
                'initiated_by' => $validated['created_by'],
                'status' => 'pending',
            ]);

            $expense->update(['approval_request_id' => $approvalRequest->id]);

            return response()->json($expense->fresh(), 201);
        });
    }
}
