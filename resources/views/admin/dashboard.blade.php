@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1">Panel de administrador</h1>
            <p class="st-muted mb-0">Resumen general del inventario FTTH</p>
        </div>
    </div>

    @php
        $dashboardRole = auth()->user()->role ?? null;
    @endphp

    <div class="row g-4">
        @if(in_array($dashboardRole, ['admin', 'super_admin'], true))
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card st-card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Personal</h5>
                        <p class="st-muted mb-3">Gestiona técnicos, logística y niveles de administración.</p>
                        <div class="mt-auto">
                            <a href="{{ route('admin.personnel', ['tab' => 'technicians']) }}" class="btn btn-st">
                                Gestionar personal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($dashboardRole === 'super_admin')
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card st-card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Transferencias</h5>
                        <p class="st-muted mb-3">Gestiona movimientos de materiales entre almacén y técnicos.</p>
                        <div class="mt-auto">
                            <a href="{{ route('admin.transfers') }}" class="btn btn-st">
                                Ver transferencias
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12 col-md-6 col-lg-4">
            <div class="card st-card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">Materiales</h5>
                    <p class="st-muted mb-3">Revisa catálogos, números de serie y disponibilidad.</p>
                    <div class="mt-auto">
                        <a href="{{ route('admin.materials') }}" class="btn btn-st">
                            Gestionar materiales
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if(in_array($dashboardRole, ['admin', 'super_admin'], true))
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card st-card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Histórico de usuarios</h5>
                        <p class="st-muted mb-3">Consulta las acciones realizadas sobre las cuentas de usuario.</p>
                        <div class="mt-auto">
                            <a href="{{ route('admin.user-history') }}" class="btn btn-st">
                                Ver histórico
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <div class="card st-card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Órdenes de trabajo</h5>
                        <p class="st-muted mb-3">Monitorea órdenes creadas, pendientes y consumos registrados.</p>
                        <div class="mt-auto">
                            <a href="{{ route('admin.work-orders.index') }}" class="btn btn-st">
                                Ver órdenes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- Para perfiles como logística --}}
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card st-card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Histórico del almacén</h5>
                        <p class="st-muted mb-3">Consulta los movimientos y ajustes del inventario.</p>
                        <div class="mt-auto">
                            <a href="{{ route('admin.warehouse-movements') }}" class="btn btn-st">
                                Ver histórico
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
