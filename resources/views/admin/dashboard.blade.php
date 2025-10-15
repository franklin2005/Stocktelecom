@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Panel de administrador</h2>
            <p class="text-muted mb-0">Resumen general del inventario FTTH</p>
        </div>
    </div>

    <div class="row g-3">
        @php $dashboardRole = auth()->user()->role ?? null; @endphp

        @if(in_array($dashboardRole, ['admin', 'super_admin'], true))
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Personal</h5>
                        <p class="card-text text-muted">Gestiona técnicos, logística y niveles de administración.</p>
                        <a href="{{ route('admin.personnel', ['tab' => 'technicians']) }}" class="btn btn-outline-primary">Gestionar personal</a>
                    </div>
                </div>
            </div>
        @endif

        @if($dashboardRole === 'super_admin')
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Transferencias</h5>
                        <p class="card-text text-muted">Gestiona movimientos de materiales entre almacenes y técnicos.</p>
                        <a href="{{ route('admin.transfers') }}" class="btn btn-outline-primary">Ver transferencias</a>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Materiales</h5>
                    <p class="card-text text-muted">Revisa catálogos, números de serie y disponibilidad.</p>
                    <a href="{{ route('admin.materials') }}" class="btn btn-outline-primary">Gestionar materiales</a>
                </div>
            </div>
        </div>

        @if(in_array($dashboardRole, ['admin', 'super_admin'], true))
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Histórico de usuarios</h5>
                        <p class="card-text text-muted">Consulta las acciones realizadas sobre las cuentas de usuario.</p>
                        <a href="{{ route('admin.user-history') }}" class="btn btn-outline-primary">Ver histórico</a>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Órdenes de trabajo</h5>
                        <p class="card-text text-muted">Monitorea órdenes creadas, pendientes y consumos registrados.</p>
                        <a href="{{ route('admin.work-orders.index') }}" class="btn btn-outline-primary">Ver órdenes</a>
                    </div>
                </div>
            </div>
        @else
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Histórico del almacén</h5>
                        <p class="card-text text-muted">Consulta los movimientos y ajustes del inventario.</p>
                        <a href="{{ route('admin.warehouse-movements') }}" class="btn btn-outline-primary">Ver histórico</a>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
