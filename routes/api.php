<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\LoanApplicationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/generate-otp', [AuthController::class, 'generateOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/loan-applications', [LoanApplicationController::class, 'store']);
Route::post('/loan-applications/{id}/status', [LoanApplicationController::class, 'updateStatus']);
