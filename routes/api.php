<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\LoanRepaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/generate-otp', [AuthController::class, 'generateOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/loan-applications', [LoanApplicationController::class, 'store']);
Route::post('/loan-applications/{id}/status', [LoanApplicationController::class, 'updateStatus']);
Route::patch('/transactions/{id}/approve', [TransactionController::class, 'approve']);
Route::post('/loans/{loan_id}/repay', [LoanRepaymentController::class, 'repay']);
