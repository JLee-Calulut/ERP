<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GeneralLedgerController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\JournalEntryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\PurchaseReceiptController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SalesOrderController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TrialBalanceController;
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

        Route::middleware('permission:sales.manage')->group(function () {
            Route::get('sales/customers/create', [CustomerController::class, 'create'])
                ->name('sales.customers.create');

            Route::post('sales/customers', [CustomerController::class, 'store'])
                ->name('sales.customers.store');

            Route::get('sales/customers/{customer}/edit', [CustomerController::class, 'edit'])
                ->name('sales.customers.edit');

            Route::put('sales/customers/{customer}', [CustomerController::class, 'update'])
                ->name('sales.customers.update');

            Route::delete('sales/customers/{customer}', [CustomerController::class, 'destroy'])
                ->name('sales.customers.destroy');

            Route::get('sales/orders/create', [SalesOrderController::class, 'create'])
                ->name('sales.orders.create');

            Route::post('sales/orders', [SalesOrderController::class, 'store'])
                ->name('sales.orders.store');

            Route::patch('sales/orders/{order}/status', [SalesOrderController::class, 'updateStatus'])
                ->name('sales.orders.status');

            Route::post('sales/orders/{order}/invoice', [InvoiceController::class, 'store'])
                ->name('sales.invoices.store');
        });

        Route::middleware('permission:sales.view')->group(function () {
            Route::get('sales/customers', [CustomerController::class, 'index'])
                ->name('sales.customers.index');

            Route::get('sales/orders', [SalesOrderController::class, 'index'])
                ->name('sales.orders.index');

            Route::get('sales/orders/{order}', [SalesOrderController::class, 'show'])
                ->name('sales.orders.show');

            Route::get('sales/invoices', [InvoiceController::class, 'index'])
                ->name('sales.invoices.index');

            Route::get('sales/invoices/{invoice}', [InvoiceController::class, 'show'])
                ->name('sales.invoices.show');
        });

        Route::middleware('permission:purchasing.manage')->group(function () {
            Route::get('purchasing/suppliers/create', [SupplierController::class, 'create'])
                ->name('purchasing.suppliers.create');

            Route::post('purchasing/suppliers', [SupplierController::class, 'store'])
                ->name('purchasing.suppliers.store');

            Route::get('purchasing/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
                ->name('purchasing.suppliers.edit');

            Route::put('purchasing/suppliers/{supplier}', [SupplierController::class, 'update'])
                ->name('purchasing.suppliers.update');

            Route::delete('purchasing/suppliers/{supplier}', [SupplierController::class, 'destroy'])
                ->name('purchasing.suppliers.destroy');

            Route::get('purchasing/orders/create', [PurchaseOrderController::class, 'create'])
                ->name('purchasing.orders.create');

            Route::post('purchasing/orders', [PurchaseOrderController::class, 'store'])
                ->name('purchasing.orders.store');

            Route::patch('purchasing/orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus'])
                ->name('purchasing.orders.status');

            Route::post('purchasing/orders/{purchaseOrder}/receipts', [PurchaseReceiptController::class, 'store'])
                ->name('purchasing.receipts.store');

            Route::delete('purchasing/orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])
                ->name('purchasing.orders.destroy');
        });

        Route::middleware('permission:purchasing.view')->group(function () {
            Route::get('purchasing/suppliers', [SupplierController::class, 'index'])
                ->name('purchasing.suppliers.index');

            Route::get('purchasing/orders', [PurchaseOrderController::class, 'index'])
                ->name('purchasing.orders.index');

            Route::get('purchasing/orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
                ->name('purchasing.orders.show');

            Route::get('purchasing/receipts', [PurchaseReceiptController::class, 'index'])
                ->name('purchasing.receipts.index');

            Route::get('purchasing/receipts/{purchaseReceipt}', [PurchaseReceiptController::class, 'show'])
                ->name('purchasing.receipts.show');
        });

        Route::middleware('permission:accounting.view')->group(function () {
            Route::get('accounting/accounts', [AccountController::class, 'index'])
                ->name('accounting.accounts.index');

            Route::get('accounting/journal', [JournalEntryController::class, 'index'])
                ->name('accounting.journal.index');

            Route::get('accounting/reports/trial-balance', [TrialBalanceController::class, 'index'])
                ->name('accounting.reports.trial-balance');

            Route::get('accounting/reports/ledger', [GeneralLedgerController::class, 'index'])
                ->name('accounting.reports.ledger');
        });

        Route::middleware('permission:accounting.manage')->group(function () {
            Route::get('accounting/accounts/create', [AccountController::class, 'create'])
                ->name('accounting.accounts.create');

            Route::post('accounting/accounts', [AccountController::class, 'store'])
                ->name('accounting.accounts.store');

            Route::get('accounting/accounts/{account}/edit', [AccountController::class, 'edit'])
                ->name('accounting.accounts.edit');

            Route::put('accounting/accounts/{account}', [AccountController::class, 'update'])
                ->name('accounting.accounts.update');

            Route::delete('accounting/accounts/{account}', [AccountController::class, 'destroy'])
                ->name('accounting.accounts.destroy');

            Route::get('accounting/journal/create', [JournalEntryController::class, 'create'])
                ->name('accounting.journal.create');

            Route::post('accounting/journal', [JournalEntryController::class, 'store'])
                ->name('accounting.journal.store');

            // Posting invoices and receipts to accounting is an accounting
            // operation (not a sales/purchasing one), so it sits behind
            // accounting.manage even though the actions live on the
            // invoice/receipt detail pages.
            Route::post('sales/invoices/{invoice}/post', [InvoiceController::class, 'post'])
                ->name('sales.invoices.post');

            Route::post('purchasing/receipts/{purchaseReceipt}/post', [PurchaseReceiptController::class, 'post'])
                ->name('purchasing.receipts.post');
        });

        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});
