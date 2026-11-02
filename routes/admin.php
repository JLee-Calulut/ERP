<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('erp')->name('erp.')->group(function () {
    Route::middleware('admin.guest')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])
            ->name('login');

        Route::post('login', [AuthenticatedSessionController::class, 'store']);
    });

    Route::middleware('admin.auth')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])
                ->name('roles.index');

            Route::get('roles/create', [RoleController::class, 'create'])
                ->name('roles.create');

            Route::post('roles', [RoleController::class, 'store'])
                ->name('roles.store');

            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
                ->name('roles.edit');

            Route::put('roles/{role}', [RoleController::class, 'update'])
                ->name('roles.update');

            Route::delete('roles/{role}', [RoleController::class, 'destroy'])
                ->name('roles.destroy');
        });

        Route::middleware('permission:users.manage')->group(function () {
            Route::get('users', [UserController::class, 'index'])
                ->name('users.index');

            Route::get('users/create', [UserController::class, 'create'])
                ->name('users.create');

            Route::post('users', [UserController::class, 'store'])
                ->name('users.store');

            Route::get('users/{user}/edit', [UserController::class, 'edit'])
                ->name('users.edit');

            Route::put('users/{user}', [UserController::class, 'update'])
                ->name('users.update');

            Route::delete('users/{user}', [UserController::class, 'destroy'])
                ->name('users.destroy');
        });

        Route::middleware('permission:inventory.view')->group(function () {
            Route::get('inventory/products', [ProductController::class, 'index'])
                ->name('inventory.products.index');

            Route::get('inventory/categories', [CategoryController::class, 'index'])
                ->name('inventory.categories.index');

            Route::get('inventory/stock', [StockController::class, 'index'])
                ->name('inventory.stock.index');
        });

        Route::middleware('permission:inventory.manage')->group(function () {
            Route::get('inventory/products/create', [ProductController::class, 'create'])
                ->name('inventory.products.create');

            Route::post('inventory/products', [ProductController::class, 'store'])
                ->name('inventory.products.store');

            Route::get('inventory/products/{product}/edit', [ProductController::class, 'edit'])
                ->name('inventory.products.edit');

            Route::put('inventory/products/{product}', [ProductController::class, 'update'])
                ->name('inventory.products.update');

            Route::delete('inventory/products/{product}', [ProductController::class, 'destroy'])
                ->name('inventory.products.destroy');

            Route::get('inventory/categories/create', [CategoryController::class, 'create'])
                ->name('inventory.categories.create');

            Route::post('inventory/categories', [CategoryController::class, 'store'])
                ->name('inventory.categories.store');

            Route::get('inventory/categories/{category}/edit', [CategoryController::class, 'edit'])
                ->name('inventory.categories.edit');

            Route::put('inventory/categories/{category}', [CategoryController::class, 'update'])
                ->name('inventory.categories.update');

            Route::delete('inventory/categories/{category}', [CategoryController::class, 'destroy'])
                ->name('inventory.categories.destroy');

            Route::get('inventory/stock/create', [StockController::class, 'create'])
                ->name('inventory.stock.create');

            Route::post('inventory/stock', [StockController::class, 'store'])
                ->name('inventory.stock.store');

            Route::delete('inventory/stock', [StockController::class, 'destroy'])
                ->name('inventory.stock.destroy');
        });

        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});
