<?php

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        Route::get('user-management', function () {
            return "test asd";
        })->name('user-management');
        // Route::get('/users', UserIndex::class)->name('users.index');
    });

    // Admin + Operator routes
    Route::middleware('role:admin,operator')->group(function () {
        // Route::get('/inventory', InventoryIndex::class)->name('inventory.index');
    });
});

Route::post('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();
    return redirect()->route('login');
})->middleware('auth')->name('logout');