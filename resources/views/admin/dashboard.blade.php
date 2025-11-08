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
        $menuByRole = [
            'super_admin' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
                ['label' => 'Devoluciones', 'route' => 'admin.returns'],
                ['label' => 'Histórico de devoluciones', 'route' => 'admin.returns.history'],
                ['label' => 'Histórico de almacén', 'route' => 'admin.warehouse-movements'],
                ['label' => 'Histórico de usuarios', 'route' => 'admin.user-history'],
                ['label' => 'Materiales', 'route' => 'admin.materials'],
                ['label' => 'Crear material', 'route' => 'admin.materials.create'],
                ['label' => 'Órdenes de trabajo', 'route' => 'admin.work-orders.index'],
                ['label' => 'Personal', 'route' => 'admin.personnel', 'params' => ['tab' => 'technicians']],
                ['label' => 'Transferencias', 'route' => 'admin.transfers'],
                ['label' => 'Técnicos', 'route' => 'admin.technicians.overview'],
            ],
            'admin' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
                ['label' => 'Histórico de almacén', 'route' => 'admin.warehouse-movements'],
                ['label' => 'Histórico de usuarios', 'route' => 'admin.user-history'],
                ['label' => 'Materiales', 'route' => 'admin.materials'],
                ['label' => 'Órdenes de trabajo', 'route' => 'admin.work-orders.index'],
                ['label' => 'Personal', 'route' => 'admin.personnel', 'params' => ['tab' => 'technicians']],
                ['label' => 'Técnicos', 'route' => 'admin.technicians.overview'],
            ],
            'logistics' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
                ['label' => 'Devoluciones', 'route' => 'admin.returns'],
                ['label' => 'Histórico de almacén', 'route' => 'admin.warehouse-movements'],
                ['label' => 'Histórico de devoluciones', 'route' => 'admin.returns.history'],
                ['label' => 'Materiales', 'route' => 'admin.materials'],
                ['label' => 'Crear material', 'route' => 'admin.materials.create'],
                ['label' => 'Órdenes de trabajo', 'route' => 'admin.work-orders.index'],
                ['label' => 'Transferencias', 'route' => 'admin.transfers'],
                ['label' => 'Técnicos', 'route' => 'admin.technicians.overview'],
            ],
        ];

        $menuItems = $menuByRole[$dashboardRole] ?? [];
        usort($menuItems, fn ($a, $b) => strcmp(mb_strtolower($a['label']), mb_strtolower($b['label'])));

        $descriptions = [
            'Dashboard' => 'Resumen general del panel administrativo.',
            'Devoluciones' => 'Genera solicitudes para que los técnicos devuelvan material al almacén.',
            'Histórico de devoluciones' => 'Revisa todas las devoluciones registradas.',
            'Histórico de almacén' => 'Consulta los movimientos y ajustes del inventario central.',
            'Histórico de usuarios' => 'Audita los cambios realizados sobre las cuentas.',
            'Materiales' => 'Explora el catálogo y estado de cada material.',
            'Crear material' => 'Da de alta nuevas plantillas de materiales.',
            'Órdenes de trabajo' => 'Supervisa y administra las órdenes en curso.',
            'Personal' => 'Gestiona técnicos, logística y otros perfiles.',
            'Transferencias' => 'Controla los envíos desde el almacén hacia los técnicos.',
            'Técnicos' => 'Consulta información consolidada de cada técnico.',
        ];
    @endphp

    <div class="row g-4">
        @forelse ($menuItems as $item)
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                <div class="card st-card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">{{ $item['label'] }}</h5>
                        <p class="st-muted mb-3 flex-grow-1">
                            {{ $descriptions[$item['label']] ?? 'Acceso directo a este módulo.' }}
                        </p>
                        <a href="{{ route($item['route'], $item['params'] ?? []) }}" class="btn btn-st">
                            Abrir
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-warning mb-0">No hay accesos disponibles para tu perfil.</div>
            </div>
        @endforelse
    </div>
@endsection
