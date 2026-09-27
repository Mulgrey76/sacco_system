<?php

namespace App\Http\Controllers;

use App\Models\BudgetAllocation;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $allocations = BudgetAllocation::where('org_id', $request->query('org_id'))
            ->with('branch')->orderByDesc('created_at')->get();
        return response()->json($allocations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'org_id' => 'required|integer|exists:organizations,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'expense_type' => 'required|string|max:80',
            'period_label' => 'required|string|max:20',
            'allocated_amount' => 'required|numeric|min:0.01',
            'allocated_by' => 'required|integer|exists:users,id',
        ]);

        $allocation = BudgetAllocation::create($validated);
        return response()->json($allocation, 201);
    }
}
