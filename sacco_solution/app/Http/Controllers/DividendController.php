<?php

namespace App\Http\Controllers;

use App\Models\DividendDeclaration;
use App\Models\DividendPayout;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\SharesTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BRD Section 17 "Dividends Manager": "Saccos usually pay dividends
 * based on a rate determined by the business and is calculated from
 * member's entire savings for the year." declare() computes one
 * payout row per client with a nonzero savings balance; pay() then
 * actually credits each client's savings account, same as the BRD's
 * worked example (KES 1,000,000 savings x 10% = KES 100,000).
 */
class DividendController extends Controller
{
    public function index(Request $request)
    {
        $declarations = DividendDeclaration::where('org_id', $request->query('org_id'))
            ->orderByDesc('created_at')->get();
        return response()->json($declarations);
    }

    public function show(int $id)
    {
        $declaration = DividendDeclaration::with('payouts.client')->findOrFail($id);
        return response()->json($declaration);
    }

    public function declare(Request $request)
    {
        $validated = $request->validate([
            'org_id' => 'required|integer|exists:organizations,id',
            'period_label' => 'required|string|max:20',
            'rate_pct' => 'required|numeric|min:0.001',
            'declared_by' => 'required|integer|exists:users,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $declaration = DividendDeclaration::create([
                'org_id' => $validated['org_id'],
                'period_label' => $validated['period_label'],
                'rate_pct' => $validated['rate_pct'],
                'basis' => 'savings',
                'status' => 'draft',
                'declared_by' => $validated['declared_by'],
            ]);

            // One payout row per client with a nonzero savings balance,
            // computed as rate_pct% of their current balance -- the
            // exact worked example from the BRD.
            $accounts = SavingsAccount::where('org_id', $validated['org_id'])
                ->where('balance', '>', 0)
                ->get();

            foreach ($accounts as $account) {
                $dividendAmount = bcmul(
                    (string) $account->balance,
                    bcdiv((string) $validated['rate_pct'], '100', 6),
                    2
                );

                DividendPayout::create([
                    'dividend_declaration_id' => $declaration->id,
                    'client_id' => $account->client_id,
                    'basis_amount' => $account->balance,
                    'dividend_amount' => $dividendAmount,
                    'status' => 'pending',
                ]);
            }

            $declaration->update(['status' => 'approved']);

            return response()->json($declaration->fresh('payouts'), 201);
        });
    }

    /**
     * Actually credits each pending payout into the client's savings
     * account (BRD doesn't specify a dedicated dividend wallet, so we
     * post directly to savings, matching the "member's shares account"
     * language used elsewhere in the BRD for dividend crediting).
     */
    public function pay(int $id)
    {
        $declaration = DividendDeclaration::with('payouts')->findOrFail($id);

        if ($declaration->status !== 'approved') {
            return response()->json(['message' => 'Only approved declarations can be paid.'], 409);
        }

        return DB::transaction(function () use ($declaration) {
            foreach ($declaration->payouts()->where('status', 'pending')->get() as $payout) {
                $account = SavingsAccount::where('client_id', $payout->client_id)
                    ->where('org_id', $declaration->org_id)
                    ->lockForUpdate()
                    ->first();

                if ($account === null) {
                    continue; // client has no savings account to credit into
                }

                $newBalance = bcadd((string) $account->balance, (string) $payout->dividend_amount, 2);

                SavingsTransaction::create([
                    'savings_account_id' => $account->id,
                    'type' => 'deposit',
                    'amount' => $payout->dividend_amount,
                    'balance_after' => $newBalance,
                    'narrative' => "Dividend payout — {$declaration->period_label}",
                ]);

                $account->update(['balance' => $newBalance]);
                $payout->update(['status' => 'paid', 'paid_at' => now()]);
            }

            $declaration->update(['status' => 'paid']);

            return response()->json($declaration->fresh('payouts'));
        });
    }
}
