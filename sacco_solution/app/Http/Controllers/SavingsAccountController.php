<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BRD Section 13 "Savings Manager":
 *   - "Creation / edit processes are maker checker" -- so store() does
 *     NOT create the account directly; it raises an ApprovalRequest.
 *     The account is only actually created once ApprovalController's
 *     applyApprovedEffect() fires, after enough approvals land.
 *   - "Close savings accounts only with 0 balance."
 *   - Deposits trace back to the wallet movement that funded them
 *     (wallet_transaction_id), same pattern proven in our SQL test.
 */
class SavingsAccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = SavingsAccount::where('client_id', $request->query('client_id'))
            ->with('product')
            ->get();

        return response()->json($accounts);
    }

    /**
     * BRD: creation is maker-checker, so this raises a request instead
     * of creating the row. required_approvals = 1 here as a default
     * (the BRD doesn't specify a savings-specific approval count the
     * way loan products do); adjust if your org needs more.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'org_id' => 'required|integer|exists:organizations,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'client_id' => 'required|integer|exists:clients,id',
            'savings_product_id' => 'required|integer|exists:savings_products,id',
            'initiated_by' => 'required|integer|exists:users,id',
        ]);

        $approvalRequest = ApprovalRequest::create([
            'org_id' => $validated['org_id'],
            'entity_type' => 'SAVINGS_ACCOUNT_CREATION',
            'action' => 'CREATE',
            'payload_json' => [
                'branch_id' => $validated['branch_id'],
                'client_id' => $validated['client_id'],
                'savings_product_id' => $validated['savings_product_id'],
            ],
            'required_approvals' => 1,
            'initiated_by' => $validated['initiated_by'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Savings account creation submitted for approval.',
            'approval_request' => $approvalRequest,
        ], 202);
    }

    /**
     * BRD: deposit moves money FROM the client's wallet INTO the
     * savings account -- traced via wallet_transaction_id so a deposit
     * can always be walked back to its funding source.
     */
    public function deposit(Request $request, int $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'created_by' => 'required|integer|exists:users,id',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $account = SavingsAccount::where('id', $id)->lockForUpdate()->firstOrFail();
            $wallet = Wallet::where('client_id', $account->client_id)->lockForUpdate()->firstOrFail();

            $walletAvailable = bcsub((string) $wallet->balance, (string) $wallet->reserved_amount, 2);
            if (bccomp((string) $validated['amount'], $walletAvailable, 2) > 0) {
                return response()->json(['message' => 'Insufficient wallet balance.'], 409);
            }

            $walletNewBalance = bcsub((string) $wallet->balance, (string) $validated['amount'], 2);
            $walletTxn = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'direction' => 'debit',
                'purpose' => 'savings_deposit',
                'amount' => $validated['amount'],
                'balance_after' => $walletNewBalance,
                'created_by' => $validated['created_by'],
            ]);
            $wallet->update(['balance' => $walletNewBalance]);

            $accountNewBalance = bcadd((string) $account->balance, (string) $validated['amount'], 2);
            $savingsTxn = SavingsTransaction::create([
                'savings_account_id' => $account->id,
                'wallet_transaction_id' => $walletTxn->id,
                'type' => 'deposit',
                'amount' => $validated['amount'],
                'balance_after' => $accountNewBalance,
                'created_by' => $validated['created_by'],
            ]);
            $account->update(['balance' => $accountNewBalance]);

            return response()->json([
                'savings_account' => $account->fresh(),
                'wallet' => $wallet->fresh(),
                'transaction' => $savingsTxn,
            ], 201);
        });
    }

    /**
     * BRD: "Close savings accounts only with 0 balance."
     */
    public function close(int $id)
    {
        $account = SavingsAccount::findOrFail($id);

        if (!$account->canClose()) {
            return response()->json([
                'message' => 'Cannot close a savings account with a non-zero balance.',
            ], 409);
        }

        $account->update(['status' => 5]);  // 5 = closed

        return response()->json($account->fresh());
    }
}
