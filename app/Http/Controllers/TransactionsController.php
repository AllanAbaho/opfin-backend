<?php

namespace App\Http\Controllers;

use App\Models\Transaction;

class TransactionsController extends Controller
{
    public function index()
    {
        $transactions = Transaction::latest()->get();
        return view('transactions.index', compact('transactions'));
    }
}
