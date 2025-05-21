<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});
Auth::routes(['register' => false]);
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy.policy');
// Account deletion routes
Route::get('/account/delete', [AuthController::class, 'showDeleteForm'])->name('account.delete');
Route::delete('/account/delete', [AuthController::class, 'destroy'])->name('account.destroy');


Route::middleware('auth')->group(function () {
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/loan-applications', [App\Http\Controllers\LoanApplicationsController::class, 'index'])->name('loan-applications.index');
    Route::get('/transactions', [App\Http\Controllers\TransactionsController::class, 'index'])->name('transactions.index');
    Route::get('/users', [App\Http\Controllers\UsersController::class, 'index'])->name('users.index');
});
