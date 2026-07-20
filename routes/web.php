<?php

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Inventory\CategoryManagement;
use App\Livewire\Inventory\MenuManagement;
use App\Livewire\Inventory\RawMaterialManagement;
use App\Livewire\Inventory\StockManagement;
use App\Livewire\Inventory\StockPrediction;
use App\Livewire\Users\UserManagement;
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
        Route::get('user-management', UserManagement::class)->name('user-management');
    });

    // Admin + Operator routes
    Route::middleware('role:admin,operator')->group(function () {
        Route::get('inventory/categories', CategoryManagement::class)->name('inventory.categories');
        Route::get('inventory/raw-materials', RawMaterialManagement::class)->name('inventory.raw-materials');
        Route::get('inventory/stock', StockManagement::class)->name('inventory.stock');
        Route::get('inventory/menus', MenuManagement::class)->name('inventory.menus');
        Route::get('inventory/prediction', StockPrediction::class)->name('inventory.prediction');
    });
});

Route::post('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');
