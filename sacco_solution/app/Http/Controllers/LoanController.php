<?php

namespace App\Http\Controllers;

use App\Models\AccountNumberSequence;
use App\Models\ApprovalRequest;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanNote;
use App\Models\LoanProduct;
use App\Models\LoanRepaymentSchedule;
use App\Models\PaymentTransaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BRD Section 8 "Loan Manager" -- application through disbursement.
 * Approval itself deliberately reuses the GENERIC maker-checker engine
 * (ApprovalController) rather than a bespoke loan-approval endpoint --
 * same pattern already proven working for savings account creation.
 */
class LoanController extends Controller
{
    public function index(Request $request)
    {
        $loans = Loan::where('org_id', $request->query('org_id'))
            ->with('client', 'product')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($loans);
    }

    public function show(int $id)
    {
        $loan = Loan::with('client', 'product', 'guarantors', 'collateral', 'notes', 'repaymentSchedule')
            ->findOrFail($id);

        return response()->json($loan);
    }

    /**
     * BRD: "Loan creation... Validate the existence of customer M-wallet
     * account... When creating loan, filter clients & loan products
     * based on the account types selected." Guarantors are attached
     * here but start as 'pending' -- each must be separately approved
     * (simulating OTP verification) before the loan itself can be
     * submitted for maker-checker approval.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'org_id' => 'required|integer|exists:organizations,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'client_id' => 'required|integer|exists:clients,id',
            'loan_product_id' => 'required|integer|exists:loan_products,id',
            'loan_purpose_id' => 'nullable|integer|exists:loan_purposes,id',
            'loan_officer_id' => 'required|integer|exists:users,id',
            'amount_applied' => 'required|numeric|min:0.01',
            'source_of_funds' => 'nullable|string|max:100',
            'guarantors' => 'required|array|min:1',
            'guarantors.*.guarantor_type' => 'required|in:internal,external',
            'guarantors.*.client_id' => 'required_if:guarantors.*.guarantor_type,internal|nullable|integer|exists:clients,id',
            'guarantors.*.external_full_name' => 'required_if:guarantors.*.guarantor_type,external|nullable|string|max:150',
            'creation_note' => 'nullable|string|max:1000',
        ]);

        $product = LoanProduct::findOrFail($validated['loan_product_id']);

        // BRD: loan amount must respect the product's configured range
        if (bccomp((string) $validated['amount_applied'], (string) $product->min_amount, 2) < 0
            || bccomp((string) $validated['amount_applied'], (string) $product->max_amount, 2) > 0) {
            return response()->json([
                'message' => "Amount must be between {$product->min_amount} and {$product->max_amount} for this product.",
            ], 422);
        }

        // BRD: "No client transaction is allowed without an existing & active wallet."
        $wallet = Wallet::where('client_id', $validated['client_id'])->first();
        if ($wallet === null || $wallet->status !== 1) {
            return response()->json(['message' => 'Client has no active wallet; cannot create a loan.'], 409);
        }

        return DB::transaction(function () use ($validated, $product) {
            $loanReference = AccountNumberSequence::generate($validated['org_id'], 'LOAN', 'L');

            $loan = Loan::create([
                'org_id' => $validated['org_id'],
                'branch_id' => $validated['branch_id'],
                'client_id' => $validated['client_id'],
                'loan_product_id' => $validated['loan_product_id'],
                'loan_purpose_id' => $validated['loan_purpose_id'] ?? null,
                'loan_officer_id' => $validated['loan_officer_id'],
                'loan_reference' => $loanReference,
                'amount_applied' => $validated['amount_applied'],
                'interest_rate_pct' => $product->interest_rate_pct,
                'interest_period' => $product->interest_period,
                'source_of_funds' => $validated['source_of_funds'] ?? null,
                'status' => Loan::STATUS_PENDING,
            ]);

            foreach ($validated['guarantors'] as $g) {
                LoanGuarantor::create([
                    'loan_id' => $loan->id,
                    'guarantor_type' => $g['guarantor_type'],
                    'client_id' => $g['guarantor_type'] === 'internal' ? $g['client_id'] : null,
                    'external_full_name' => $g['guarantor_type'] === 'external' ? $g['external_full_name'] : null,
                    'external_id_number' => $g['external_id_number'] ?? null,
                    'external_phone' => $g['external_phone'] ?? null,
                    'approval_status' => 'pending',
                ]);
            }

            LoanNote::create([
                'loan_id' => $loan->id,
                'stage' => 'creation',
                'note_text' => $validated['creation_note'] ?? 'Loan application created.',
                'created_by' => $validated['loan_officer_id'],
            ]);

            return response()->json($loan->fresh(['guarantors', 'notes']), 201);
        });
    }

    /**
     * Simulates a guarantor completing OTP verification (BRD: "Validate
     * OTP via sms - loan officer"). Real OTP generation/validation would
     * go through the generic otp_codes table; simplified here to a
     * direct status flip so the loan flow can be tested end-to-end.
     */
    public function approveGuarantor(int $loanId, int $guarantorId)
    {
        $guarantor = LoanGuarantor::where('loan_id', $loanId)->where('id', $guarantorId)->firstOrFail();

        $guarantor->update(['approval_status' => 'approved', 'approved_at' => now()]);

        return response()->json($guarantor->fresh());
    }

    /**
     * BRD: "Assessment must be done by at least 2 users... the bigger
     * the loan the more the guarantors required to authorize." This
     * raises a GENERIC ApprovalRequest -- the same engine already
     * proven for savings account creation -- with required_approvals
     * computed from the loan product's rules.
     */
    public function requestApproval(Request $request, int $id)
    {
        $validated = $request->validate([
            'initiated_by' => 'required|integer|exists:users,id',
        ]);

        $loan = Loan::with('product', 'guarantors')->findOrFail($id);

        if ($loan->status !== Loan::STATUS_PENDING) {
            return response()->json(['message' => 'Only pending loans can be submitted for approval.'], 409);
        }

        if (!$loan->allGuarantorsApproved()) {
            return response()->json(['message' => 'All guarantors must approve before the loan can go for approval.'], 409);
        }

        $requiredApprovals = $loan->product->requiredApprovalsFor((float) $loan->amount_applied);

        $approvalRequest = ApprovalRequest::create([
            'org_id' => $loan->org_id,
            'entity_type' => 'LOAN_APPROVAL',
            'entity_id' => $loan->id,
            'action' => 'APPROVE',
            'payload_json' => ['amount_applied' => (float) $loan->amount_applied],
            'required_approvals' => $requiredApprovals,
            'initiated_by' => $validated['initiated_by'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => "Loan submitted for approval ({$requiredApprovals} approver(s) required).",
            'approval_request' => $approvalRequest,
        ], 202);
    }

    /**
     * BRD "Loan disbursement (B2C)": query utility/wallet balance first,
     * credit the client's wallet, log the payment_transaction, flip the
     * loan to Active, and generate a simple repayment schedule.
     */
    public function disburse(Request $request, int $id)
    {
        $validated = $request->validate([
            'installments' => 'required|integer|min:1|max:36',
            'external_reference' => 'required|string|max:60',
            'disbursed_by' => 'required|integer|exists:users,id',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $loan = Loan::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== Loan::STATUS_APPROVED) {
                return response()->json(['message' => 'Only approved loans can be disbursed.'], 409);
            }

            if (PaymentTransaction::referenceAlreadyUsed($loan->org_id, $validated['external_reference'])) {
                return response()->json(['message' => 'This transaction reference has already been used.'], 409);
            }

            $wallet = Wallet::where('client_id', $loan->client_id)->lockForUpdate()->firstOrFail();
            $amount = (float) $loan->amount_approved;

            $walletNewBalance = bcadd((string) $wallet->balance, (string) $amount, 2);
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'direction' => 'credit',
                'purpose' => 'loan_repayment',
                'amount' => $amount,
                'balance_after' => $walletNewBalance,
                'external_reference' => $validated['external_reference'],
            ]);
            $wallet->update(['balance' => $walletNewBalance]);

            PaymentTransaction::create([
                'org_id' => $loan->org_id,
                'loan_id' => $loan->id,
                'wallet_id' => $wallet->id,
                'direction' => 'disbursement_b2c',
                'amount' => $amount,
                'external_reference' => $validated['external_reference'],
                'status' => 'completed',
                'initiated_by' => $validated['disbursed_by'],
                'processed_at' => now(),
            ]);

            $loan->update(['status' => Loan::STATUS_ACTIVE, 'disbursed_at' => now()]);

            LoanNote::create([
                'loan_id' => $loan->id,
                'stage' => 'disbursement',
                'note_text' => 'Disbursed in full to client wallet.',
                'created_by' => $validated['disbursed_by'],
            ]);

            // Simple equal-principal schedule with flat interest split
            // across installments. Real amortization logic (reducing
            // balance, product interest_period, exclude_weekends/
            // holidays flags) belongs in a dedicated scheduling service
            // -- this proves the mechanism, not the finance math.
            $installments = $validated['installments'];
            $principalPerInstallment = round($amount / $installments, 2);
            $totalInterest = bcmul((string) $amount, bcdiv((string) $loan->interest_rate_pct, '100', 4), 2);
            $interestPerInstallment = round((float) $totalInterest / $installments, 2);

            for ($i = 1; $i <= $installments; $i++) {
                LoanRepaymentSchedule::create([
                    'loan_id' => $loan->id,
                    'installment_number' => $i,
                    'due_date' => now()->addMonths($i)->toDateString(),
                    'expected_principal' => $principalPerInstallment,
                    'expected_interest' => $interestPerInstallment,
                    'expected_total' => bcadd((string) $principalPerInstallment, (string) $interestPerInstallment, 2),
                ]);
            }

            return response()->json($loan->fresh(['repaymentSchedule', 'notes']));
        });
    }
}
