<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy.policy');
// Account deletion routes
Route::get('/account/delete', [AuthController::class, 'showDeleteForm'])->name('account.delete');
Route::delete('/account/delete', [AuthController::class, 'destroy'])->name('account.destroy');
