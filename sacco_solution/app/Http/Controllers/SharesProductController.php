<?php

namespace App\Http\Controllers;

use App\Models\SharesProduct;
use Illuminate\Http\Request;

class SharesProductController extends Controller
{
    public function index(Request $request)
    {
        $products = SharesProduct::where('org_id', $request->query('org_id'))
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'interest_rate_pct']);

        return response()->json($products);
    }
}
