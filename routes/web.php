<?php

use App\Http\Controllers\Admin\AdminTransferController;
use App\Http\Controllers\Admin\MaterialController;
use App\Http\Controllers\Admin\PersonnelController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\TechnicianController;
use App\Http\Controllers\Admin\UserHistoryController;
use App\Http\Controllers\Admin\WarehouseHistoryController;
use App\Http\Controllers\Admin\WorkOrderController as AdminWorkOrderController;
use App\Http\Controllers\Admin\TechnicianOverviewController;
use App\Http\Controllers\Admin\ReturnsController;
use App\Http\Controllers\Admin\ReturnHistoryController;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;

use App\Http\Controllers\Technician\MenuController as TechnicianMenuController;
use App\Http\Controllers\Technician\TransferController as TechnicianTransferController;
use App\Http\Controllers\Technician\ReturnsController as TechnicianReturnsController;
use App\Http\Controllers\Technician\WorkOrderController as TechnicianWorkOrderController;
use App\Http\Controllers\Technician\TransferHistoryController as TechnicianTransferHistoryController;
use App\Http\Controllers\Auth\ProfileController;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
 * RUTA RAÍZ
 * Si hay sesión iniciada redirige al dashboard según rol.
 * Si no hay sesión, muestra la vista de bienvenida.
 */
Route::get('/', function () {
    if (Auth::check()) {
        $role = Auth::user()->role;

        return redirect()->route(match ($role) {
            'technician' => 'technician.stock',
            'logistics' => 'admin.materials',
            'admin', 'super_admin' => 'admin.personnel',
            default => 'login',
        });
    }
    return view('welcome');
})->name('home');

/*
 * AUTENTICACIÓN - INVITADOS (guest)
 * Acceso sólo para usuarios no autenticados:
 *  - Selector de rol
 *  - Formulario por rol
 *  - Envío de credenciales
 */
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showRoleSelection'])->name('login');
    Route::get('/login/{role}', [LoginController::class, 'showRoleLogin'])->name('login.role');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
});

/*
 * ZONA AUTENTICADA (auth)
 * Todo lo que hay dentro requiere sesión iniciada.
 */
Route::middleware('auth')->group(function () {
    // Cierre de sesión
    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');
    // Perfil - cambio de contrasena
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/password', [ProfileController::class, 'passwordEdit'])->name('profile.edit');
    Route::patch('/profile/password', [ProfileController::class, 'passwordUpdate'])->name('profile.update');
    /*
     * MÓDULO ADMIN / LOGÍSTICA
     * Panel de gestión, transferencias desde almacén, materiales y
     * consultas de órdenes de trabajo.
     */
    Route::prefix('admin')->name('admin.')->group(function () {
        /*
         * Acceso: admin, super_admin, logistics
         */
        Route::middleware('role:admin,super_admin,logistics')->group(function () {
            // Route dashboard eliminado
            // Transferencias desde almacén
            Route::get('/transfers', [AdminTransferController::class, 'index'])->name('transfers');
            Route::post('/transfers/cart/add', [AdminTransferController::class, 'addToCart'])->name('transfers.cart.add');
            Route::post('/transfers/cart/{itemKey}/remove', [AdminTransferController::class, 'removeFromCart'])->name('transfers.cart.remove');
            Route::post('/transfers/cart/clear', [AdminTransferController::class, 'clearCart'])->name('transfers.cart.clear');
            Route::post('/transfers/send', [AdminTransferController::class, 'send'])->name('transfers.send');
            // Materiales (visualización)
            Route::get('/materials', [MaterialController::class, 'index'])->name('materials');
            // Historial de movimientos de almacén
            Route::get('/warehouse-movements', [WarehouseHistoryController::class, 'index'])->name('warehouse-movements');
            // Órdenes de trabajo (consulta)
            Route::get('/work-orders', [AdminWorkOrderController::class, 'index'])->name('work-orders.index');
            Route::get('/work-orders/{workOrder}', [AdminWorkOrderController::class, 'show'])->name('work-orders.show');
        });

        /*
         * Acceso: super_admin, logistics
         * Modificación directa de stock y asignaciones.
         */
        Route::middleware('role:super_admin,logistics')->group(function () {
            Route::post('/materials/add-stock', [MaterialController::class, 'addStock'])->name('materials.add-stock');
            Route::post('/materials/remove-stock', [MaterialController::class, 'removeStock'])->name('materials.remove-stock');
            Route::post('/materials/assign', [MaterialController::class, 'assignToTechnician'])->name('materials.assign');
        });

        /*
         * Acceso: admin, super_admin
         * Gestión de personal (técnicos, logistica) y auditorías.
         */
        Route::middleware('role:admin,super_admin')->group(function () {
            Route::get('/personnel', [PersonnelController::class, 'index'])->name('personnel');
            Route::get('/personnel/{user}/movements', [PersonnelController::class, 'movements'])->name('personnel.movements');
            // CRUD de técnicos
            Route::controller(TechnicianController::class)->prefix('technicians')->name('technicians.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{technician}', 'update')->name('update');
                Route::delete('/{technician}', 'destroy')->name('destroy');
            });
            // CRUD de administradores  y logistica
            Route::controller(StaffController::class)->prefix('staff')->name('staff.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{staff}', 'update')->name('update');
                Route::delete('/{staff}', 'destroy')->name('destroy');
            });
            // Historial de usuarios (auditoría)
            Route::get('/user-history', [UserHistoryController::class, 'index'])->name('user-history');
            // Actualización de órdenes de trabajo
            Route::put('/work-orders/{workOrder}', [AdminWorkOrderController::class, 'update'])->name('work-orders.update');
        });

        /*
         * MÓDULO DEVOLUCIONES Y GESTIÓN DE MATERIALES
         * Acceso: super_admin, logistics
         */
        Route::middleware('role:super_admin,logistics')->group(function () {
            // Gestión de materiales (CRUD)
            Route::get('/materials/create', [MaterialController::class, 'create'])->name('materials.create');
            Route::post('/materials', [MaterialController::class, 'store'])->name('materials.store');
            Route::patch('/materials/{material}', [MaterialController::class, 'update'])->name('materials.update');
            Route::delete('/materials/{material}', [MaterialController::class, 'destroy'])->name('materials.destroy');
            // Devoluciones de materiales desde técnicos al almacén
            Route::get('/returns', [ReturnsController::class, 'index'])->name('returns');
            Route::post('/returns/cart/add', [ReturnsController::class, 'addToCart'])->name('returns.cart.add');
            Route::post('/returns/cart/{key}/remove', [ReturnsController::class, 'removeFromCart'])->name('returns.cart.remove');
            Route::post('/returns/cart/clear', [ReturnsController::class, 'clearCart'])->name('returns.cart.clear');
            Route::post('/returns/send', [ReturnsController::class, 'send'])->name('returns.send');
            Route::get('/returns/history', [ReturnHistoryController::class, 'index'])->name('returns.history');
        });

        /*
         * VISTA GENERAL DE TÉCNICOS 
         * Acceso: admin, super_admin, logistics
         */
        Route::middleware('role:admin,super_admin,logistics')->group(function () {
            Route::get('/technicians/overview', [TechnicianOverviewController::class, 'index'])->name('technicians.overview');
            Route::get('/technicians/{technician}/stock-overview', [TechnicianOverviewController::class, 'stock'])->name('technicians.stock.overview');
            Route::get('/technicians/{technician}/transfers', [TechnicianTransferHistoryController::class, 'show'])->name('technicians.transfers.history');
            Route::get('/technicians/{technician}/returns', [TechnicianTransferHistoryController::class, 'showReturns'])->name('technicians.returns.history');
        });
    });

    /*
     * MÓDULO TÉCNICO
     * Dashboard, stock propio, transferencias entre técnicos y gestión de órdenes de trabajo.
     */
    Route::middleware('role:technician')->prefix('technician')->name('technician.')->group(function () {
        // Dashboard técnico
        // Route dashboard eliminado
        // Stock personal
        Route::get('/stock', [TechnicianMenuController::class, 'stock'])->name('stock');
        // Transferencias entre técnicos
        Route::get('/transfers', [TechnicianTransferController::class, 'index'])->name('transfers');
        Route::post('/transfers/cart/add', [TechnicianTransferController::class, 'addToCart'])->name('transfers.cart.add');
        Route::post('/transfers/cart/{itemKey}/remove', [TechnicianTransferController::class, 'removeFromCart'])->name('transfers.cart.remove');
        Route::post('/transfers/cart/clear', [TechnicianTransferController::class, 'clearCart'])->name('transfers.cart.clear');
        Route::post('/transfers/send', [TechnicianTransferController::class, 'send'])->name('transfers.send');
        Route::post('/transfers/{transfer}/accept', [TechnicianTransferController::class, 'accept'])->name('transfers.accept');
        Route::post('/transfers/{transfer}/reject', [TechnicianTransferController::class, 'reject'])->name('transfers.reject');
        // Devoluciones de materiales al almacén
        Route::post('/returns/{transfer}/accept', [TechnicianReturnsController::class, 'accept'])->name('returns.accept');
        Route::post('/returns/{transfer}/reject', [TechnicianReturnsController::class, 'reject'])->name('returns.reject');
        Route::get('/returns/history', function (Illuminate\Http\Request $request, TechnicianTransferHistoryController $controller) {
            return $controller->showReturns($request, $request->user());
        })->name('returns.history');
        // Órdenes de trabajo
        Route::get('/work-orders', [TechnicianWorkOrderController::class, 'index'])->name('work-orders');
        Route::get('/work-orders/{workOrder}', [TechnicianWorkOrderController::class, 'show'])->name('work-orders.show');
        Route::post('/work-orders', [TechnicianWorkOrderController::class, 'store'])->name('work-orders.store');
        Route::post('/work-orders/{workOrder}/items/quantity', [TechnicianWorkOrderController::class, 'addQuantityItem'])->name('work-orders.items.quantity');
        Route::post('/work-orders/{workOrder}/items/serial', [TechnicianWorkOrderController::class, 'addSerialItem'])->name('work-orders.items.serial');
        Route::delete('/work-orders/{workOrder}/items/{item}', [TechnicianWorkOrderController::class, 'removeItem'])->name('work-orders.items.destroy');
        Route::post('/work-orders/{workOrder}/confirm', [TechnicianWorkOrderController::class, 'confirm'])->name('work-orders.confirm');
        Route::post('/work-orders/{workOrder}/cancel', [TechnicianWorkOrderController::class, 'cancel'])->name('work-orders.cancel');
        // Historial de transferencias (propio o visible a roles permitidos)
        Route::get('/transfers/technicians/{technician}', [TechnicianTransferHistoryController::class, 'show'])
            ->name('transfers.history');
    });
});
