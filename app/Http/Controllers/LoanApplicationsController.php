<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;

class LoanApplicationsController extends Controller
{
    public function index()
    {
        $loanApplications = LoanApplication::latest()->get();
        return view('loan-applications.index', compact('loanApplications'));
    }
}
