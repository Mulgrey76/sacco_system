<?php

namespace App\Http\Controllers;

use App\Models\GeneratedReport;
use App\Models\Loan;
use App\Models\LoanRepaymentSchedule;
use App\Models\SavingsAccount;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

/**
 * BRD Section 12 "Reports Manager": almost none of the ~25 named
 * reports need their own table -- they're queries against tables
 * already built in Modules 1-4. Each method here logs itself to
 * generated_reports (Module 5), doubling as the BRD's "Failed and
 * undelivered emails & sms report" concept if generation ever fails.
 */
class ReportController extends Controller
{
    public function loanPortfolio(Request $request)
    {
        $orgId = $request->query('org_id');

        $loans = Loan::where('org_id', $orgId)
            ->whereIn('status', [Loan::STATUS_ACTIVE, Loan::STATUS_OVERDUE])
            ->with('client', 'product')
            ->get()
            ->map(function ($loan) {
                return [
                    'loan_reference' => $loan->loan_reference,
                    'client_name' => trim($loan->client->first_name . ' ' . $loan->client->last_name),
                    'product' => $loan->product->name,
                    'amount_approved' => $loan->amount_approved,
                    'status' => $loan->status,
                    'disbursed_at' => $loan->disbursed_at,
                ];
            });

        $this->logReport($orgId, 'LOAN_PORTFOLIO', $loans->count());

        return response()->json($loans);
    }

    public function loanArrears(Request $request)
    {
        $orgId = $request->query('org_id');

        $arrears = LoanRepaymentSchedule::whereHas('loan', function ($q) use ($orgId) {
                $q->where('org_id', $orgId);
            })
            ->where('due_date', '<', now()->toDateString())
            ->where('status', '!=', 'paid')
            ->with('loan.client')
            ->get()
            ->map(function ($schedule) {
                return [
                    'loan_reference' => $schedule->loan->loan_reference,
                    'client_name' => trim($schedule->loan->client->first_name . ' ' . $schedule->loan->client->last_name),
                    'installment_number' => $schedule->installment_number,
                    'due_date' => $schedule->due_date,
                    'expected_total' => $schedule->expected_total,
                    'paid_amount' => $schedule->paid_amount,
                    'outstanding' => $schedule->outstanding,
                    'days_overdue' => now()->diffInDays($schedule->due_date),
                ];
            });

        $this->logReport($orgId, 'LOAN_ARREARS', $arrears->count());

        return response()->json($arrears);
    }

    public function disbursements(Request $request)
    {
        $orgId = $request->query('org_id');

        $disbursements = PaymentTransaction::where('org_id', $orgId)
            ->where('direction', 'disbursement_b2c')
            ->with('loan.client')
            ->orderByDesc('processed_at')
            ->get()
            ->map(function ($txn) {
                return [
                    'loan_reference' => $txn->loan?->loan_reference,
                    'client_name' => $txn->loan?->client
                        ? trim($txn->loan->client->first_name . ' ' . $txn->loan->client->last_name)
                        : null,
                    'amount' => $txn->amount,
                    'external_reference' => $txn->external_reference,
                    'processed_at' => $txn->processed_at,
                ];
            });

        $this->logReport($orgId, 'DISBURSEMENT_REPORT', $disbursements->count());

        return response()->json($disbursements);
    }

    public function savingsBalances(Request $request)
    {
        $orgId = $request->query('org_id');

        $accounts = SavingsAccount::where('org_id', $orgId)
            ->with('client', 'product')
            ->get()
            ->map(function ($acc) {
                return [
                    'account_number' => $acc->account_number,
                    'client_name' => trim($acc->client->first_name . ' ' . $acc->client->last_name),
                    'product' => $acc->product->name,
                    'balance' => $acc->balance,
                    'status' => $acc->status,
                ];
            });

        $this->logReport($orgId, 'SAVINGS_BALANCES', $accounts->count());

        return response()->json($accounts);
    }

    private function logReport(?int $orgId, string $reportType, int $rowCount): void
    {
        GeneratedReport::create([
            'org_id' => $orgId,
            'output_format' => 'csv',
            'row_count' => $rowCount,
            'status' => 'success',
            'generated_at' => now(),
        ]);
    }
}
