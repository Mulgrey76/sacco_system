<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BRD Section 9 "Wallet Manager":
 *   - "No client transaction is allowed without an existing & active wallet"
 *   - Withdrawal (B2C) respects min/max withdrawable limits
 *   - Wallet-to-wallet transfer records BOTH legs, linked via
 *     counterparty_wallet_id so either side can be traced to the other
 */
class WalletController extends Controller
{
    public function show(int $clientId)
    {
        $wallet = Wallet::where('client_id', $clientId)->with('transactions')->firstOrFail();
        return response()->json($wallet);
    }

    /**
     * Simulates money landing in a wallet from an external source
     * (M-Pesa C2B, bank deposit, etc). In a real deployment this would
     * be called by a payment-gateway webhook, not directly by a client.
     */
    public function deposit(Request $request, int $walletId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'purpose' => 'required|in:loan_processing_fee,loan_repayment,savings_deposit,client_subscription,payroll,other',
            'external_reference' => 'nullable|string|max:60',
            'created_by' => 'nullable|integer|exists:users,id',
        ]);

        return DB::transaction(function () use ($validated, $walletId) {
            // lock the wallet row so a concurrent request can't read a
            // stale balance and compute the wrong balance_after
            $wallet = Wallet::where('id', $walletId)->lockForUpdate()->firstOrFail();

            $newBalance = bcadd((string) $wallet->balance, (string) $validated['amount'], 2);

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'direction' => 'credit',
                'purpose' => $validated['purpose'],
                'amount' => $validated['amount'],
                'balance_after' => $newBalance,
                'external_reference' => $validated['external_reference'] ?? null,
                'created_by' => $validated['created_by'] ?? null,
            ]);

            $wallet->update(['balance' => $newBalance]);

            return response()->json(['wallet' => $wallet->fresh(), 'transaction' => $transaction], 201);
        });
    }

    /**
     * BRD: "Withdrawal (B2C) - set min & max withdrawable amount."
     */
    public function withdraw(Request $request, int $walletId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'created_by' => 'required|integer|exists:users,id',
        ]);

        return DB::transaction(function () use ($validated, $walletId) {
            $wallet = Wallet::where('id', $walletId)->lockForUpdate()->firstOrFail();

            $available = bcsub((string) $wallet->balance, (string) $wallet->reserved_amount, 2);

            if (bccomp((string) $validated['amount'], $available, 2) > 0) {
                return response()->json(['message' => 'Insufficient available balance.'], 409);
            }

            if ($wallet->min_withdrawable !== null
                && bccomp((string) $validated['amount'], (string) $wallet->min_withdrawable, 2) < 0) {
                return response()->json(['message' => 'Amount is below the minimum withdrawable limit.'], 422);
            }

            if ($wallet->max_withdrawable !== null
                && bccomp((string) $validated['amount'], (string) $wallet->max_withdrawable, 2) > 0) {
                return response()->json(['message' => 'Amount exceeds the maximum withdrawable limit.'], 422);
            }

            $newBalance = bcsub((string) $wallet->balance, (string) $validated['amount'], 2);

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'direction' => 'debit',
                'purpose' => 'other',
                'amount' => $validated['amount'],
                'balance_after' => $newBalance,
                'created_by' => $validated['created_by'],
            ]);

            $wallet->update(['balance' => $newBalance]);

            return response()->json(['wallet' => $wallet->fresh(), 'transaction' => $transaction]);
        });
    }

    /**
     * BRD: "Wallet to wallet transfer." Both legs recorded, each
     * pointing at the other via counterparty_wallet_id.
     */
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'from_wallet_id' => 'required|integer|exists:wallets,id',
            'to_wallet_id' => 'required|integer|different:from_wallet_id|exists:wallets,id',
            'amount' => 'required|numeric|min:0.01',
            'created_by' => 'required|integer|exists:users,id',
        ]);

        return DB::transaction(function () use ($validated) {
            // lock both wallets in a consistent order (lowest id first)
            // to avoid deadlocks if two transfers happen between the
            // same pair of wallets in opposite directions simultaneously
            $ids = [$validated['from_wallet_id'], $validated['to_wallet_id']];
            sort($ids);
            $locked = Wallet::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $from = $locked[$validated['from_wallet_id']];
            $to = $locked[$validated['to_wallet_id']];

            $available = bcsub((string) $from->balance, (string) $from->reserved_amount, 2);
            if (bccomp((string) $validated['amount'], $available, 2) > 0) {
                return response()->json(['message' => 'Insufficient available balance.'], 409);
            }

            $fromNewBalance = bcsub((string) $from->balance, (string) $validated['amount'], 2);
            $toNewBalance = bcadd((string) $to->balance, (string) $validated['amount'], 2);

            $debitTxn = WalletTransaction::create([
                'wallet_id' => $from->id,
                'direction' => 'debit',
                'purpose' => 'wallet_to_wallet',
                'amount' => $validated['amount'],
                'balance_after' => $fromNewBalance,
                'counterparty_wallet_id' => $to->id,
                'created_by' => $validated['created_by'],
            ]);

            WalletTransaction::create([
                'wallet_id' => $to->id,
                'direction' => 'credit',
                'purpose' => 'wallet_to_wallet',
                'amount' => $validated['amount'],
                'balance_after' => $toNewBalance,
                'counterparty_wallet_id' => $from->id,
                'created_by' => $validated['created_by'],
            ]);

            $from->update(['balance' => $fromNewBalance]);
            $to->update(['balance' => $toNewBalance]);

            return response()->json([
                'from_wallet' => $from->fresh(),
                'to_wallet' => $to->fresh(),
                'transaction' => $debitTxn,
            ]);
        });
    }
}
