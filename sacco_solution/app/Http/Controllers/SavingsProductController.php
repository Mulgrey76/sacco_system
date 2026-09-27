<?php

namespace App\Http\Controllers;

use App\Models\SavingsProduct;
use Illuminate\Http\Request;

class SavingsProductController extends Controller
{
    public function index(Request $request)
    {
        $products = SavingsProduct::where('org_id', $request->query('org_id'))
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'interest_rate_pct', 'min_withdrawable', 'non_withdrawable_limit']);

        return response()->json($products);
    }
}
