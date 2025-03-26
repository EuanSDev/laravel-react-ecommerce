<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::get('/', [ProductController::class, 'index'])->name('dashboard');
Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('product.show');

Route::controller(CartController::class)->group(function() {
    Route::get('/cart', 'index')->name('cart.index');
    Route::post('/cart/add/{product}', 'store')->name('cart.store');
    Route::put('/cart/{product}', 'update')->name('cart.update');
    Route::delete('/cart/{product}', 'destroy')->name('cart.destroy');
});

// Auth Routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware(['verified'])->group(function() {
        Route::post('/cart/checkout', [CartController::class, 'checkout'])
            ->name('cart.checkout');
    });
});

require __DIR__.'/auth.php';
