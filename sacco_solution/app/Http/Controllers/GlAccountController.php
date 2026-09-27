<?php

namespace App\Http\Controllers;

use App\Models\GlAccount;
use Illuminate\Http\Request;

/**
 * BRD Section 3 "Accounting Manager": Chart of Accounts setup across
 * the five major types (asset, liability, equity, revenue, expense),
 * with optional parent/child nesting for control accounts.
 */
class GlAccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = GlAccount::where('org_id', $request->query('org_id'))
            ->orderBy('account_code')
            ->get();

        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'org_id' => 'required|integer|exists:organizations,id',
            'account_code' => 'required|string|max:20',
            'name' => 'required|string|max:150',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'parent_account_id' => 'nullable|integer|exists:gl_accounts,id',
            'is_control_account' => 'boolean',
        ]);

        $account = GlAccount::create([...$validated, 'status' => 1]);

        return response()->json($account, 201);
    }
}
