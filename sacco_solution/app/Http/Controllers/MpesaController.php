<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanRepaymentSchedule;
use App\Models\PaymentTransaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BRD Section 8 "Loan repayments (C2B)": query transaction status,
 * validate before reconciliation, apply payment against outstanding
 * installments, deposit any excess to the client's wallet.
 *
 * Flow: initiateRepayment() starts the STK push and records a PENDING
 * payment_transaction keyed by CheckoutRequestID. callback() is what
 * Safaricom calls once the client enters their M-Pesa PIN (or, since
 * we're simulating without ngrok, what WE call manually via Postman
 * with a sample payload matching Safaricom's real callback shape).
 */
class MpesaController extends Controller
{
    public function initiateRepayment(Request $request, int $loanId)
    {
        $validated = $request->validate([
            'phone_number' => 'required|regex:/^254\d{9}$/', // Daraja requires 2547XXXXXXXX format
            'amount' => 'required|numeric|min:1',
        ]);

        $loan = Loan::findOrFail($loanId);
        $wallet = Wallet::where('client_id', $loan->client_id)->firstOrFail();

        $mpesa = new MpesaService();
        $result = $mpesa->stkPush(
            $validated['phone_number'],
            $validated['amount'],
            $loan->loan_reference,
            "Loan repayment {$loan->loan_reference}"
        );

        PaymentTransaction::create([
            'org_id' => $loan->org_id,
            'loan_id' => $loan->id,
            'wallet_id' => $wallet->id,
            'direction' => 'repayment_c2b',
            'amount' => $validated['amount'],
            'external_reference' => $result['CheckoutRequestID'],
            'paid_by_mobile' => $validated['phone_number'],
            'status' => 'pending',
            'initiated_by' => $loan->loan_officer_id,
        ]);

        return response()->json([
            'message' => 'STK push sent. Awaiting client confirmation.',
            'checkout_request_id' => $result['CheckoutRequestID'],
        ]);
    }

    /**
     * Processes Safaricom's callback (or our manual simulation of it).
     * Real payload shape:
     *   Body.stkCallback.ResultCode (0 = success)
     *   Body.stkCallback.CheckoutRequestID
     *   Body.stkCallback.CallbackMetadata.Item[] -- Amount, MpesaReceiptNumber, etc.
     */
    public function callback(Request $request)
    {
        $callback = $request->input('Body.stkCallback');

        if ($callback === null) {
            return response()->json(['message' => 'Invalid callback payload.'], 422);
        }

        $checkoutRequestId = $callback['CheckoutRequestID'];
        $resultCode = $callback['ResultCode'];

        $transaction = PaymentTransaction::where('external_reference', $checkoutRequestId)
            ->where('status', 'pending')
            ->first();

        if ($transaction === null) {
            return response()->json(['message' => 'No matching pending transaction found.'], 404);
        }

        if ($resultCode !== 0) {
            $transaction->update([
                'status' => 'failed',
                'gateway_response_json' => $callback,
                'processed_at' => now(),
            ]);
            return response()->json(['message' => 'Payment failure recorded.']);
        }

        // Pull the actual confirmed amount + receipt number Safaricom sent,
        // rather than trusting whatever we originally requested.
        $items = collect($callback['CallbackMetadata']['Item'] ?? []);
        $confirmedAmount = $items->firstWhere('Name', 'Amount')['Value'] ?? $transaction->amount;
        $receiptNumber = $items->firstWhere('Name', 'MpesaReceiptNumber')['Value'] ?? null;

        return DB::transaction(function () use ($transaction, $confirmedAmount, $receiptNumber, $callback) {
            $loan = Loan::where('id', $transaction->loan_id)->lockForUpdate()->first();
            $remaining = (float) $confirmedAmount;

            // BRD: apply payment against outstanding installments,
            // oldest first.
            $schedules = LoanRepaymentSchedule::where('loan_id', $loan->id)
                ->where('status', '!=', 'paid')
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            foreach ($schedules as $schedule) {
                if ($remaining <= 0) break;

                $outstanding = (float) $schedule->outstanding;
                $applied = min($remaining, $outstanding);

                $schedule->paid_amount = bcadd((string) $schedule->paid_amount, (string) $applied, 2);
                $schedule->status = bccomp((string) $schedule->paid_amount, (string) $schedule->expected_total, 2) >= 0
                    ? 'paid' : 'partial';
                $schedule->paid_date = now()->toDateString();
                $schedule->save();

                $remaining -= $applied;
            }

            // BRD: "Excess amount paid to be deposited to M-wallet account."
            if ($remaining > 0) {
                $wallet = Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->first();
                $newBalance = bcadd((string) $wallet->balance, (string) $remaining, 2);
                WalletTransaction::create([
                    'wallet_id' => $wallet->id, 'direction' => 'credit', 'purpose' => 'loan_repayment',
                    'amount' => $remaining, 'balance_after' => $newBalance,
                    'external_reference' => $receiptNumber,
                ]);
                $wallet->update(['balance' => $newBalance]);
            }

            // If every installment is now paid, close the loan.
            $stillOutstanding = LoanRepaymentSchedule::where('loan_id', $loan->id)
                ->where('status', '!=', 'paid')->exists();
            if (!$stillOutstanding) {
                $loan->update(['status' => Loan::STATUS_CLOSED]);
            }

            $transaction->update([
                'status' => 'completed',
                'external_reference' => $receiptNumber ?? $transaction->external_reference,
                'gateway_response_json' => $callback,
                'processed_at' => now(),
            ]);

            return response()->json(['message' => 'Repayment processed successfully.']);
        });
    }
}
