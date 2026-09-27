<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::where('org_id', $request->query('org_id'))
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name', 'branch_code']);

        return response()->json($branches);
    }
}
