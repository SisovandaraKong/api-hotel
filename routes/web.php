<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentTestController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/payment', [PaymentTestController::class, 'showForm'])->name('payment.form');
Route::post('/payment', [PaymentTestController::class, 'process'])->name('payment.process');
