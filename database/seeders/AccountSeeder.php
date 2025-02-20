<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $accounts = [
            ['name' => 'Funds', 'balance' => 10000000],
            ['name' => 'Repayment'],
            ['name' => 'Disbursement'],
            ['name' => 'Interest'],
            ['name' => 'Principal'],
        ];

        foreach ($accounts as $account) {
            Account::create($account);
        }
    }
}
