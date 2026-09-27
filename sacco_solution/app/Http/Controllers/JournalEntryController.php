<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BRD Section 3: "Automated journal entries... Validate adding of
 * journal entries." The database CANNOT enforce "total debits = total
 * credits across a whole entry" via a single-table CHECK constraint
 * (documented in sacco_schema_04's header) -- JournalEntry::isBalanced()
 * is the real enforcement point, called here before flipping an entry
 * from 'draft' to 'posted'.
 */
class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        $entries = JournalEntry::where('org_id', $request->query('org_id'))
            ->with('lines.glAccount')
            ->orderByDesc('entry_date')
            ->paginate(20);

        return response()->json($entries);
    }

    public function show(int $id)
    {
        $entry = JournalEntry::with('lines.glAccount', 'branch')->findOrFail($id);
        return response()->json($entry);
    }

    /**
     * Accepts a full entry (header + lines) in one request and only
     * commits it if debits == credits. Rejects with a 422 otherwise --
     * this is the actual guard rail the BRD asks for.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'org_id' => 'required|integer|exists:organizations,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'entry_date' => 'required|date',
            'reference' => 'required|string|max:40',
            'narrative' => 'required|string|max:255',
            'source_module' => 'required|in:loan,savings,expense,payroll,budget,manual',
            'source_reference_id' => 'nullable|integer',
            'created_by' => 'required|integer|exists:users,id',
            'lines' => 'required|array|min:2',
            'lines.*.gl_account_id' => 'required|integer|exists:gl_accounts,id',
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
            'lines.*.narrative' => 'nullable|string|max:255',
        ]);

        // Check the entry balances BEFORE touching the database at all --
        // avoids ever needing a manual rollback inside DB::transaction(),
        // which conflicts with Laravel's own automatic commit/rollback.
        $totalDebits = array_sum(array_column($validated['lines'], 'debit_amount'));
        $totalCredits = array_sum(array_column($validated['lines'], 'credit_amount'));

        if (bccomp((string) $totalDebits, (string) $totalCredits, 2) !== 0) {
            return response()->json([
                'message' => 'Journal entry does not balance: total debits must equal total credits.',
            ], 422);
        }

        foreach ($validated['lines'] as $line) {
            $isDebit = $line['debit_amount'] > 0;
            $isCredit = $line['credit_amount'] > 0;
            if ($isDebit === $isCredit) {
                return response()->json([
                    'message' => 'Each line must be either a debit or a credit, never both or neither.',
                ], 422);
            }
        }

        return DB::transaction(function () use ($validated) {
            $entry = JournalEntry::create([
                'org_id' => $validated['org_id'],
                'branch_id' => $validated['branch_id'],
                'entry_date' => $validated['entry_date'],
                'reference' => $validated['reference'],
                'narrative' => $validated['narrative'],
                'source_module' => $validated['source_module'],
                'source_reference_id' => $validated['source_reference_id'] ?? null,
                'status' => 'draft',
                'created_by' => $validated['created_by'],
            ]);

            foreach ($validated['lines'] as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'gl_account_id' => $line['gl_account_id'],
                    'debit_amount' => $line['debit_amount'],
                    'credit_amount' => $line['credit_amount'],
                    'narrative' => $line['narrative'] ?? null,
                ]);
            }

            // Belt-and-braces: re-check via the model method too, in
            // case of any floating point edge case the pre-check missed.
            $entry->refresh();
            if (!$entry->isBalanced()) {
                throw new \RuntimeException('Journal entry failed balance verification after insert.');
            }

            $entry->update(['status' => 'posted', 'posted_at' => now()]);

            return response()->json($entry->fresh('lines.glAccount'), 201);
        });
    }
}
