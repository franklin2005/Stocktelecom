<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AdminTransferController;
use App\Http\Controllers\Admin\MaterialController;
use App\Http\Controllers\Admin\PersonnelController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\TechnicianController;
use App\Http\Controllers\Admin\UserHistoryController;
use App\Http\Controllers\Admin\WarehouseHistoryController;
use App\Http\Controllers\Admin\WorkOrderController as AdminWorkOrderController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Technician\DashboardController as TechnicianDashboardController;
use App\Http\Controllers\Technician\MenuController as TechnicianMenuController;
use App\Http\Controllers\Technician\TransferController as TechnicianTransferController;
use App\Http\Controllers\Technician\WorkOrderController as TechnicianWorkOrderController;
use App\Http\Controllers\TechnicianOverviewController;
use App\Http\Controllers\TransferHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        $role = auth()->user()->role;

        return redirect()->route(
            in_array($role, ['admin', 'super_admin', 'logistics'], true)
                ? 'admin.dashboard'
                : 'technician.dashboard'
        );
    }

    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showRoleSelection'])->name('login');
    Route::get('/login/{role}', [LoginController::class, 'showRoleLogin'])->name('login.role');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('role:admin,super_admin,logistics')->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
            Route::get('/transfers', [AdminTransferController::class, 'index'])->name('transfers');
            Route::post('/transfers/cart/add', [AdminTransferController::class, 'addToCart'])->name('transfers.cart.add');
            Route::post('/transfers/cart/{itemKey}/remove', [AdminTransferController::class, 'removeFromCart'])->name('transfers.cart.remove');
            Route::post('/transfers/cart/clear', [AdminTransferController::class, 'clearCart'])->name('transfers.cart.clear');
            Route::post('/transfers/send', [AdminTransferController::class, 'send'])->name('transfers.send');
            Route::get('/materials', [MaterialController::class, 'index'])->name('materials');
            Route::get('/warehouse-movements', [WarehouseHistoryController::class, 'index'])->name('warehouse-movements');
            Route::get('/work-orders', [AdminWorkOrderController::class, 'index'])->name('work-orders.index');
            Route::get('/work-orders/{workOrder}', [AdminWorkOrderController::class, 'show'])->name('work-orders.show');
        });

        Route::middleware('role:super_admin,logistics')->group(function () {
            Route::post('/materials/add-stock', [MaterialController::class, 'addStock'])->name('materials.add-stock');
            Route::post('/materials/remove-stock', [MaterialController::class, 'removeStock'])->name('materials.remove-stock');
            Route::post('/materials/assign', [MaterialController::class, 'assignToTechnician'])->name('materials.assign');
        });

        Route::middleware('role:admin,super_admin')->group(function () {
            Route::get('/personnel', [PersonnelController::class, 'index'])->name('personnel');
            Route::get('/personnel/{user}/movements', [PersonnelController::class, 'movements'])->name('personnel.movements');
            Route::controller(TechnicianController::class)->prefix('technicians')->name('technicians.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{technician}', 'update')->name('update');
                Route::delete('/{technician}', 'destroy')->name('destroy');
            });

            Route::controller(StaffController::class)->prefix('staff')->name('staff.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{staff}', 'update')->name('update');
                Route::delete('/{staff}', 'destroy')->name('destroy');
            });

            Route::get('/user-history', [UserHistoryController::class, 'index'])->name('user-history');
            Route::put('/work-orders/{workOrder}', [AdminWorkOrderController::class, 'update'])->name('work-orders.update');
        });
    });

    Route::middleware('role:admin,super_admin,logistics')->group(function () {
        Route::get('/technicians/overview', [TechnicianOverviewController::class, 'index'])->name('technicians.overview');
        Route::get('/technicians/{technician}/stock-overview', [TechnicianOverviewController::class, 'stock'])->name('technicians.stock.overview');
    });

    Route::middleware('role:technician')->prefix('technician')->name('technician.')->group(function () {
        Route::get('/dashboard', [TechnicianDashboardController::class, 'index'])->name('dashboard');
        Route::get('/stock', [TechnicianMenuController::class, 'stock'])->name('stock');
        Route::get('/transfers', [TechnicianTransferController::class, 'index'])->name('transfers');
        Route::post('/transfers/cart/add', [TechnicianTransferController::class, 'addToCart'])->name('transfers.cart.add');
        Route::post('/transfers/cart/{itemKey}/remove', [TechnicianTransferController::class, 'removeFromCart'])->name('transfers.cart.remove');
        Route::post('/transfers/cart/clear', [TechnicianTransferController::class, 'clearCart'])->name('transfers.cart.clear');
        Route::post('/transfers/send', [TechnicianTransferController::class, 'send'])->name('transfers.send');
        Route::post('/transfers/{transfer}/accept', [TechnicianTransferController::class, 'accept'])->name('transfers.accept');
        Route::post('/transfers/{transfer}/reject', [TechnicianTransferController::class, 'reject'])->name('transfers.reject');
        Route::get('/work-orders', [TechnicianWorkOrderController::class, 'index'])->name('work-orders');
        Route::get('/work-orders/{workOrder}', [TechnicianWorkOrderController::class, 'show'])->name('work-orders.show');
        Route::post('/work-orders', [TechnicianWorkOrderController::class, 'store'])->name('work-orders.store');
        Route::post('/work-orders/{workOrder}/items/quantity', [TechnicianWorkOrderController::class, 'addQuantityItem'])->name('work-orders.items.quantity');
        Route::post('/work-orders/{workOrder}/items/serial', [TechnicianWorkOrderController::class, 'addSerialItem'])->name('work-orders.items.serial');
        Route::delete('/work-orders/{workOrder}/items/{item}', [TechnicianWorkOrderController::class, 'removeItem'])->name('work-orders.items.destroy');
        Route::post('/work-orders/{workOrder}/confirm', [TechnicianWorkOrderController::class, 'confirm'])->name('work-orders.confirm');
        Route::post('/work-orders/{workOrder}/cancel', [TechnicianWorkOrderController::class, 'cancel'])->name('work-orders.cancel');
    });

    Route::get('/transfers/technicians/{technician}', [TransferHistoryController::class, 'show'])
        ->name('technicians.transfers.history');
});
