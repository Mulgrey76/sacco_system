<?php

namespace App\Http\Controllers;

use App\Models\AccountNumberSequence;
use App\Models\Branch;
use App\Models\Client;
use Illuminate\Http\Request;

/**
 * BRD "Client Profile/Account Manager" rules ported from the original
 * FastAPI routers/clients.py:
 *   - member_number auto-generated via AccountNumberSequence
 *   - mobile numbers may repeat but only up to a configurable max
 *   - a disabled branch cannot onboard clients
 */
class ClientController extends Controller
{
    // BRD: "Allow use of the same mobile number but with a max limit."
    private const MAX_CLIENTS_PER_MOBILE = 3;

    public function index(Request $request)
    {
        $orgId = $request->query('org_id');

        $clients = Client::where('org_id', $orgId)
            ->with('branch')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($clients);
    }

    public function show(int $id)
    {
        $client = Client::with('branch', 'wallet')->findOrFail($id);
        return response()->json($client);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'org_id' => 'required|integer|exists:organizations,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'account_category' => 'required|string|max:20',
            'first_name' => 'required|string|max:80',
            'middle_name' => 'nullable|string|max:80',
            'last_name' => 'required|string|max:80',
            'other_names' => 'nullable|string|max:80',
            'national_id' => 'nullable|string|max:40',
            'mobile_number' => ['required', 'regex:/^(?:\+254|0)7\d{8}$/'],
            'email' => 'nullable|email',
            'account_type' => 'required|in:individual,joint,diaspora',
            'onboarded_by' => 'required|integer|exists:users,id',
        ]);

        $branch = Branch::where('id', $validated['branch_id'])
            ->where('org_id', $validated['org_id'])
            ->first();

        if ($branch === null) {
            return response()->json(['message' => 'Branch not found for this organization.'], 404);
        }

        // BRD: "A disabled branch should not transact or add users."
        if ($branch->is_disabled) {
            return response()->json(['message' => 'This branch is disabled and cannot onboard clients.'], 409);
        }

        $existingCount = Client::where('org_id', $validated['org_id'])
            ->where('mobile_number', $validated['mobile_number'])
            ->count();

        if ($existingCount >= self::MAX_CLIENTS_PER_MOBILE) {
            return response()->json([
                'message' => 'Mobile number already used by the maximum of '
                    . self::MAX_CLIENTS_PER_MOBILE . ' client profiles.',
            ], 409);
        }

        $memberNumber = AccountNumberSequence::generate(
            $validated['org_id'],
            'CLIENT_MAIN',
            $validated['account_category']
        );

        $client = Client::create([
            ...$validated,
            'member_number' => $memberNumber,
            'status' => 0,  // pending until KYC/approval workflow completes
        ]);

        return response()->json($client, 201);
    }
}
