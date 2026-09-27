<?php

namespace App\Http\Controllers;

use App\Models\LoanProduct;
use Illuminate\Http\Request;

/**
 * Minimal controller so the frontend can populate a product dropdown
 * dynamically instead of hardcoding IDs. BRD Section 8 "Loan product":
 * a product is only meaningfully usable once a client/branch/officer
 * selects it during loan creation.
 */
class LoanProductController extends Controller
{
    public function index(Request $request)
    {
        $products = LoanProduct::where('org_id', $request->query('org_id'))
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'min_amount', 'max_amount', 'interest_rate_pct', 'interest_period']);

        return response()->json($products);
    }
}
