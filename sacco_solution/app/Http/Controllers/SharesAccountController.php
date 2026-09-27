<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\SharesAccount;
use App\Models\SharesProduct;
use App\Models\SharesTransaction;
use App\Models\AccountNumberSequence;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SharesAccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = SharesAccount::where('client_id', $request->query('client_id'))
            ->with('product')->get();
        return response()->json($accounts);
    }

    // Maker-checker, same as SavingsAccountController::store()
    public function store(Request $request)
    {
        $validated = $request->validate([
            'org_id' => 'required|integer|exists:organizations,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'client_id' => 'required|integer|exists:clients,id',
            'shares_product_id' => 'required|integer|exists:shares_products,id',
            'initiated_by' => 'required|integer|exists:users,id',
        ]);

        $approvalRequest = ApprovalRequest::create([
            'org_id' => $validated['org_id'],
            'entity_type' => 'SHARES_ACCOUNT_CREATION',
            'action' => 'CREATE',
            'payload_json' => [
                'branch_id' => $validated['branch_id'],
                'client_id' => $validated['client_id'],
                'shares_product_id' => $validated['shares_product_id'],
            ],
            'required_approvals' => 1,
            'initiated_by' => $validated['initiated_by'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Shares account creation submitted for approval.',
            'approval_request' => $approvalRequest,
        ], 202);
    }

    public function deposit(Request $request, int $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'created_by' => 'required|integer|exists:users,id',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $account = SharesAccount::where('id', $id)->lockForUpdate()->firstOrFail();
            $wallet = Wallet::where('client_id', $account->client_id)->lockForUpdate()->firstOrFail();

            $walletAvailable = bcsub((string) $wallet->balance, (string) $wallet->reserved_amount, 2);
            if (bccomp((string) $validated['amount'], $walletAvailable, 2) > 0) {
                return response()->json(['message' => 'Insufficient wallet balance.'], 409);
            }

            $walletNewBalance = bcsub((string) $wallet->balance, (string) $validated['amount'], 2);
            $walletTxn = WalletTransaction::create([
                'wallet_id' => $wallet->id, 'direction' => 'debit', 'purpose' => 'other',
                'amount' => $validated['amount'], 'balance_after' => $walletNewBalance,
                'created_by' => $validated['created_by'],
            ]);
            $wallet->update(['balance' => $walletNewBalance]);

            $accountNewBalance = bcadd((string) $account->balance, (string) $validated['amount'], 2);
            $txn = SharesTransaction::create([
                'shares_account_id' => $account->id, 'wallet_transaction_id' => $walletTxn->id,
                'type' => 'deposit', 'amount' => $validated['amount'],
                'balance_after' => $accountNewBalance, 'created_by' => $validated['created_by'],
            ]);
            $account->update(['balance' => $accountNewBalance]);

            return response()->json(['shares_account' => $account->fresh(), 'transaction' => $txn], 201);
        });
    }

    public function close(int $id)
    {
        $account = SharesAccount::findOrFail($id);
        if (!$account->canClose()) {
            return response()->json(['message' => 'Cannot close a shares account with a non-zero balance.'], 409);
        }
        $account->update(['status' => 5]);
        return response()->json($account->fresh());
    }
}
