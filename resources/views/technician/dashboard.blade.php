@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">Panel del técnico</h1>
            <p class="st-muted mb-0">Accede a tu inventario, gestiona transferencias y controla tus órdenes de trabajo.</p>
        </div>
    </div>

    @php
        $menuItems = [
            ['label' => 'Dashboard', 'route' => 'technician.dashboard'],
            ['label' => 'Histórico de devoluciones', 'route' => 'technician.returns.history'],
            ['label' => 'Histórico de transferencias', 'route' => 'technician.transfers.history', 'params' => [auth()->user()]],
            ['label' => 'Mi stock', 'route' => 'technician.stock'],
            ['label' => 'Órdenes de trabajo', 'route' => 'technician.work-orders'],
            ['label' => 'Transferencias', 'route' => 'technician.transfers'],
        ];

        usort($menuItems, fn ($a, $b) => strcmp(mb_strtolower($a['label']), mb_strtolower($b['label'])));

        $descriptions = [
            'Dashboard' => 'Resumen general de tus herramientas de trabajo.',
            'Histórico de devoluciones' => 'Consulta todas las devoluciones que has gestionado.',
            'Histórico de transferencias' => 'Revisa transferencias enviadas o recibidas.',
            'Mi stock' => 'Visualiza los materiales asignados a tu inventario.',
            'Órdenes de trabajo' => 'Crea y actualiza tus órdenes en curso.',
            'Transferencias' => 'Envía materiales o acepta solicitudes pendientes.',
        ];
    @endphp

    <div class="row g-4">
        @foreach ($menuItems as $item)
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="st-card p-3 h-100 d-flex flex-column">
                    <h5 class="card-title">{{ $item['label'] }}</h5>
                    <p class="st-muted flex-grow-1 mb-3">
                        {{ $descriptions[$item['label']] ?? 'Acceso directo a este módulo.' }}
                    </p>
                    <a href="{{ route($item['route'], $item['params'] ?? []) }}" class="btn btn-st w-100">Abrir</a>
                </div>
            </div>
        @endforeach
    </div>
@endsection
