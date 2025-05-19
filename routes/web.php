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
});
