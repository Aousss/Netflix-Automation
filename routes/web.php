<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\VaultController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [GoogleAuthController::class, 'showLogin'])->name('login');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
    Route::post('/auth/firebase/callback', [GoogleAuthController::class, 'handleFirebaseCallback'])->name('auth.firebase.callback');
    Route::post('/auth/magic-link', [GoogleAuthController::class, 'sendMagicLink'])
        ->middleware('throttle:5,1')
        ->name('auth.magic.send');
});

Route::get('/auth/magic-link/verify/{user}', [GoogleAuthController::class, 'verifyMagicLink'])
    ->name('auth.magic.verify');

Route::post('/auth/demo-switch/{user}', [GoogleAuthController::class, 'demoSwitch'])
    ->name('auth.demo.switch');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/imap/fetch', [DashboardController::class, 'fetchImapCodes'])
        ->middleware('throttle:10,1')
        ->name('imap.fetch');

    // Encrypted Master Credentials Vault & Step-Up OTP
    Route::post('/vault/request-otp', [VaultController::class, 'requestOtp'])
        ->middleware('throttle:3,1')
        ->name('vault.request-otp');
    Route::post('/vault/reveal', [VaultController::class, 'reveal'])
        ->middleware('throttle:6,1')
        ->name('vault.reveal');
    Route::post('/vault/grace-bypass', [VaultController::class, 'activateGraceBypass'])
        ->name('vault.grace-bypass');

    // Split-Billing & DuitNow Receipt Upload
    Route::post('/payments/{payment}/upload-receipt', [PaymentController::class, 'uploadReceipt'])
        ->name('payments.upload-receipt');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'showReceipt'])
        ->name('payments.receipt');

    // Admin Workflow Routes
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('/payments/{payment}/verify', [AdminController::class, 'verifyPayment'])
            ->name('payments.verify');
        Route::post('/payments/{payment}/status', [AdminController::class, 'updatePaymentStatus'])
            ->name('payments.status');
        Route::post('/settings', [AdminController::class, 'updateSettings'])
            ->name('settings.update');
        Route::post('/members/{member}', [AdminController::class, 'updateMember'])
            ->name('members.update');
        Route::post('/billing/generate-cycle', [AdminController::class, 'generateCycle'])
            ->name('billing.generate');
    });
});
