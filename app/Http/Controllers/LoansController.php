<?php

namespace App\Http\Controllers;

use App\Models\Loan;

class LoansController extends Controller
{
    public function index()
    {
        $loans = Loan::with(['user', 'loanProduct'])
            ->latest()
            ->paginate(15);

        return view('loans.index', compact('loans'));
    }
}
