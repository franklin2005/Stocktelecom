<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'STOCKTELECOM') }}</title>

    {{-- Bootstrap + jQuery --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    {{-- Estilos del proyecto --}}
    <link rel="stylesheet" href="{{ asset('css/mainStyle.css') }}">
</head>
<body class="bg-light">
    @php
        $navRole = auth()->user()->role ?? null;
        $menuByRole = [
            'super_admin' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard'],
                ['label' => 'Devoluciones', 'route' => 'admin.returns', 'pattern' => 'admin.returns'],
                ['label' => 'Histórico de devoluciones', 'route' => 'admin.returns.history', 'pattern' => 'admin.returns.history'],
                ['label' => 'Histórico de almacén', 'route' => 'admin.warehouse-movements', 'pattern' => 'admin.warehouse-movements'],
                ['label' => 'Histórico de usuarios', 'route' => 'admin.user-history', 'pattern' => 'admin.user-history'],
                ['label' => 'Materiales', 'route' => 'admin.materials', 'pattern' => 'admin.materials'],
                ['label' => 'Crear material', 'route' => 'admin.materials.create', 'pattern' => 'admin.materials.create'],
                ['label' => 'Órdenes de trabajo', 'route' => 'admin.work-orders.index', 'pattern' => 'admin.work-orders.*'],
                ['label' => 'Personal', 'route' => 'admin.personnel', 'pattern' => 'admin.personnel*'],
                ['label' => 'Transferencias', 'route' => 'admin.transfers', 'pattern' => 'admin.transfers'],
                ['label' => 'Técnicos', 'route' => 'admin.technicians.overview', 'pattern' => ['admin.technicians.overview', 'admin.technicians.stock.overview', 'admin.technicians.transfers.history', 'admin.technicians.returns.history']],
            ],
            'admin' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard'],
                ['label' => 'Histórico de almacén', 'route' => 'admin.warehouse-movements', 'pattern' => 'admin.warehouse-movements'],
                ['label' => 'Histórico de usuarios', 'route' => 'admin.user-history', 'pattern' => 'admin.user-history'],
                ['label' => 'Materiales', 'route' => 'admin.materials', 'pattern' => 'admin.materials'],
                ['label' => 'Órdenes de trabajo', 'route' => 'admin.work-orders.index', 'pattern' => 'admin.work-orders.*'],
                ['label' => 'Personal', 'route' => 'admin.personnel', 'pattern' => 'admin.personnel*'],
                ['label' => 'Técnicos', 'route' => 'admin.technicians.overview', 'pattern' => ['admin.technicians.overview', 'admin.technicians.stock.overview', 'admin.technicians.transfers.history']],
            ],
            'logistics' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard'],
                ['label' => 'Devoluciones', 'route' => 'admin.returns', 'pattern' => 'admin.returns'],
                ['label' => 'Histórico de almacén', 'route' => 'admin.warehouse-movements', 'pattern' => 'admin.warehouse-movements'],
                ['label' => 'Histórico de devoluciones', 'route' => 'admin.returns.history', 'pattern' => 'admin.returns.history'],
                ['label' => 'Materiales', 'route' => 'admin.materials', 'pattern' => 'admin.materials'],
                ['label' => 'Crear material', 'route' => 'admin.materials.create', 'pattern' => 'admin.materials.create'],
                ['label' => 'Órdenes de trabajo', 'route' => 'admin.work-orders.index', 'pattern' => 'admin.work-orders.*'],
                ['label' => 'Transferencias', 'route' => 'admin.transfers', 'pattern' => 'admin.transfers'],
                ['label' => 'Técnicos', 'route' => 'admin.technicians.overview', 'pattern' => ['admin.technicians.overview', 'admin.technicians.stock.overview', 'admin.technicians.transfers.history', 'admin.technicians.returns.history']],
            ],
            'technician' => [
                ['label' => 'Dashboard', 'route' => 'technician.dashboard', 'pattern' => 'technician.dashboard'],
                ['label' => 'Histórico de devoluciones', 'route' => 'technician.returns.history', 'pattern' => 'technician.returns.history'],
                ['label' => 'Histórico de transferencias', 'route' => 'technician.transfers.history', 'pattern' => 'technician.transfers.history', 'params' => [auth()->user()]],
                ['label' => 'Mi stock', 'route' => 'technician.stock', 'pattern' => 'technician.stock'],
                ['label' => 'Órdenes de trabajo', 'route' => 'technician.work-orders', 'pattern' => 'technician.work-orders'],
                ['label' => 'Transferencias', 'route' => 'technician.transfers', 'pattern' => 'technician.transfers'],
            ],
        ];
        $menuItemsRaw = $menuByRole[$navRole] ?? [];
        usort($menuItemsRaw, function ($a, $b) {
            return strcmp(mb_strtolower($a['label']), mb_strtolower($b['label']));
        });
        $menuItems = $menuItemsRaw;
    @endphp

    <header class="bg-dark text-white py-2">
        <div class="container-fluid d-flex align-items-center justify-content-between">
            <a class="navbar-brand text-white fw-semibold" href="{{ route('home') }}">
                STOCKTELECOM
            </a>
            <div class="d-flex align-items-center gap-3">
                @auth
                    <button class="btn btn-outline-light btn-sm d-lg-none"
                            type="button"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#mobileSidebar"
                            aria-controls="mobileSidebar">
                        Menú
                    </button>
                    <span class="small d-none d-md-inline">Hola, {{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm">Cerrar sesión</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">Iniciar sesión</a>
                @endauth
            </div>
        </div>
    </header>

    @auth
        <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="mobileSidebarLabel">Menú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
            </div>
            <div class="offcanvas-body">
                <div class="list-group list-group-flush">
                    @foreach ($menuItems as $item)
                        @php
                            $pattern = $item['pattern'] ?? $item['route'];
                            $isActive = request()->routeIs($pattern);
                            $classes = 'list-group-item list-group-item-action';
                            if ($isActive) {
                                $classes .= ' active';
                            }
                        @endphp

                        <a href="{{ route($item['route'], $item['params'] ?? []) }}"
                           class="{{ $classes }}"
                           @if($isActive) aria-current="true" @endif>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endauth

    {{-- LAYOUT --}}
    <div class="container-fluid">
        <div class="row">
            @auth
                @php
                    $navRole = $navRole ?? (auth()->user()->role ?? null);
                    $menuItems = $menuItems ?? [];
                @endphp

                {{-- Sidebar: solo visible en escritorio (>= lg), ocupa 2/12 --}}
                <aside class="d-none d-lg-block col-lg-2 p-0">
                    @include('partials.sidebar', ['menuItems' => $menuItems, 'role' => $navRole])
                </aside>

                {{-- Contenido principal: 12/12 en móvil, 10/12 en escritorio --}}
                <main class="col-12 col-lg-10 px-4 py-4">
                    @yield('content')
                </main>
            @else
                <main class="col-12 col-md-8 offset-md-2 col-lg-6 offset-lg-3 py-5">
                    @yield('content')
                </main>
            @endauth
        </div>
    </div>

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
