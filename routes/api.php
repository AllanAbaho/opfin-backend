<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LoanApplicationController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\LoanRepaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/generate-otp', [AuthController::class, 'generateOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/loan-applications', [LoanApplicationController::class, 'store']);
Route::get('/loan-applications/{user}', [LoanApplicationController::class, 'index']);
Route::post('/loan-applications/{id}/status', [LoanApplicationController::class, 'updateStatus']);
Route::patch('/transactions/{id}/approve', [TransactionController::class, 'approve']);
Route::post('/loans/{loan_id}/repay', [LoanRepaymentController::class, 'repay']);
Route::get('/products', [LoanApplicationController::class, 'getProducts']);
Route::get('/institutions', [LoanApplicationController::class, 'getInstitutions']);
Route::get('/product-terms/{product}', [LoanApplicationController::class, 'getProductTerms']);
Route::post('/handleCallback', [TransactionController::class, 'handleCallback'])->name('handleCallback');
