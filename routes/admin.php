<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController;
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

        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});
