<?php

namespace App\Http\Controllers;

use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\AccountNumberSequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GENERIC maker-checker endpoints, reused across the whole platform:
 * branch fund transfers, client status changes, loan approvals,
 * savings account creation, reversals, etc. -- distinguished only by
 * entity_type, never a dedicated controller per use case.
 *
 * BRD Validation Settings (hard rule, enforced here):
 *   "Maker and checker should always be two different users. If the
 *    same user tries to initiate, approve and authorize then the
 *    operation will always fail with an error message."
 */
class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query = ApprovalRequest::where('org_id', $request->query('org_id'));

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }
        if ($request->has('entity_id')) {
            $query->where('entity_id', $request->query('entity_id'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function decide(Request $request, int $id)
    {
        $validated = $request->validate([
            'acted_by' => 'required|integer|exists:users,id',
            'action' => 'required|in:approve,reject',
            'remarks' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $approvalRequest = ApprovalRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($approvalRequest->status !== 'pending') {
                return response()->json(['message' => "Request already {$approvalRequest->status}."], 409);
            }

            if (!$approvalRequest->canBeActionedBy($validated['acted_by'])) {
                return response()->json([
                    'message' => 'The operation cannot be completed by the same user role. '
                        . 'Maker and checker must be different users, and a user cannot action the same request twice.',
                ], 403);
            }

            ApprovalAction::create([
                'approval_request_id' => $approvalRequest->id,
                'acted_by' => $validated['acted_by'],
                'action' => $validated['action'],
                'remarks' => $validated['remarks'] ?? null,
            ]);

            if ($validated['action'] === 'reject') {
                $approvalRequest->update(['status' => 'rejected', 'resolved_at' => now()]);
                return response()->json($approvalRequest->fresh());
            }

            $approvalsSoFar = $approvalRequest->actions()->where('action', 'approve')->count();

            if ($approvalsSoFar >= $approvalRequest->required_approvals) {
                $approvalRequest->update(['status' => 'approved', 'resolved_at' => now()]);

                // The one place where "approved" actually DOES something:
                // finish whatever the entity_type says this request was for.
                $this->applyApprovedEffect($approvalRequest);
            }

            return response()->json($approvalRequest->fresh());
        });
    }

    /**
     * Once a request crosses its required_approvals threshold, this is
     * where the REAL side-effect happens. SAVINGS_ACCOUNT_CREATION and
     * LOAN_APPROVAL are wired up so far -- CLIENT_STATUS, reversals,
     * etc. will be added here as their own controllers are built.
     */
    private function applyApprovedEffect(ApprovalRequest $approvalRequest): void
    {
        if ($approvalRequest->entity_type === 'SAVINGS_ACCOUNT_CREATION') {
            $payload = $approvalRequest->payload_json;

            $product = SavingsProduct::findOrFail($payload['savings_product_id']);

            $accountNumber = AccountNumberSequence::generate(
                $approvalRequest->org_id,
                'SAVINGS',
                $product->account_number_prefix
            );

            SavingsAccount::create([
                'org_id' => $approvalRequest->org_id,
                'branch_id' => $payload['branch_id'],
                'client_id' => $payload['client_id'],
                'savings_product_id' => $product->id,
                'account_number' => $accountNumber,
                'status' => 1,
            ]);
        }

        if ($approvalRequest->entity_type === 'SHARES_ACCOUNT_CREATION') {
            $payload = $approvalRequest->payload_json;

            $product = \App\Models\SharesProduct::findOrFail($payload['shares_product_id']);

            $accountNumber = AccountNumberSequence::generate(
                $approvalRequest->org_id,
                'SHARES',
                $product->account_number_prefix
            );

            \App\Models\SharesAccount::create([
                'org_id' => $approvalRequest->org_id,
                'branch_id' => $payload['branch_id'],
                'client_id' => $payload['client_id'],
                'shares_product_id' => $product->id,
                'account_number' => $accountNumber,
                'status' => 1,
            ]);
        }

        if ($approvalRequest->entity_type === 'EXPENSE_AUTHORIZATION') {
            \App\Models\Expense::where('id', $approvalRequest->entity_id)
                ->update(['status' => 'authorized']);
        }

        if ($approvalRequest->entity_type === 'LOAN_APPROVAL') {
            $payload = $approvalRequest->payload_json;

            $loan = \App\Models\Loan::findOrFail($approvalRequest->entity_id);

            $loan->update([
                'status' => \App\Models\Loan::STATUS_APPROVED,
                'amount_approved' => $payload['amount_applied'],
                'approved_at' => now(),
            ]);

            \App\Models\LoanNote::create([
                'loan_id' => $loan->id,
                'stage' => 'approval',
                'note_text' => 'Approved at full requested amount via maker-checker.',
                'created_by' => $approvalRequest->actions()->latest()->first()->acted_by,
            ]);
        }
    }
}
