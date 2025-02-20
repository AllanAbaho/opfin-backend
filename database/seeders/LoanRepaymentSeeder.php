<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Loan;
use App\Models\LoanRepayment;

class LoanRepaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $loans = Loan::all();

        foreach ($loans as $loan) {
            // Assuming a single repayment for simplicity
            LoanRepayment::create([
                'user_id' => $loan->user_id,
                'institution_id' => $loan->institution_id,
                'loan_id' => $loan->id,
                'amount' => $loan->repayment_amount,
            ]);
        }
    }
}
