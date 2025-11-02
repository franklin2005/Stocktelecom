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
    {{-- NAVBAR (menú superior para móvil/tablet y también visible en escritorio) --}}
    <nav class="navbar navbar-expand-lg navbar-dark st-navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('home') }}">
                STOCKTELECOM
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                    aria-controls="mainNavbar" aria-expanded="false" aria-label="Abrir navegación">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                @auth
                    @php
                        $navRole = auth()->user()->role ?? null;
                        $menuByRole = [
                            'super_admin' => [
                                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard'],
                                ['label' => 'Transferencias', 'route' => 'admin.transfers', 'pattern' => 'admin.transfers'],
                                ['label' => 'Devoluciones', 'route' => 'admin.returns', 'pattern' => 'admin.returns'],
                                ['label' => 'Histórico de devoluciones', 'route' => 'admin.returns.history', 'pattern' => 'admin.returns.history'],
                                ['label' => 'Materiales', 'route' => 'admin.materials', 'pattern' => 'admin.materials'],
                                ['label' => 'Crear material', 'route' => 'admin.materials.create', 'pattern' => 'admin.materials.create'],
                                ['label' => 'Personal', 'route' => 'admin.personnel', 'pattern' => 'admin.personnel*'],
                                ['label' => 'Histórico de usuarios', 'route' => 'admin.user-history', 'pattern' => 'admin.user-history'],
                                ['label' => 'Órdenes de trabajo', 'route' => 'admin.work-orders.index', 'pattern' => 'admin.work-orders.*'],
                                ['label' => 'Histórico de almacén', 'route' => 'admin.warehouse-movements', 'pattern' => 'admin.warehouse-movements'],
                                ['label' => 'Técnicos', 'route' => 'admin.technicians.overview', 'pattern' => ['admin.technicians.overview', 'admin.technicians.stock.overview', 'admin.technicians.transfers.history', 'admin.technicians.returns.history']],
                            ],
                            'admin' => [
                                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard'],
                                ['label' => 'Materiales', 'route' => 'admin.materials', 'pattern' => 'admin.materials'],
                                ['label' => 'Personal', 'route' => 'admin.personnel', 'pattern' => 'admin.personnel*'],
                                ['label' => 'Histórico de usuarios', 'route' => 'admin.user-history', 'pattern' => 'admin.user-history'],
                                ['label' => 'Órdenes de trabajo', 'route' => 'admin.work-orders.index', 'pattern' => 'admin.work-orders.*'],
                                ['label' => 'Histórico de almacén', 'route' => 'admin.warehouse-movements', 'pattern' => 'admin.warehouse-movements'],
                                ['label' => 'Técnicos', 'route' => 'admin.technicians.overview', 'pattern' => ['admin.technicians.overview', 'admin.technicians.stock.overview', 'admin.technicians.transfers.history']],
                            ],
                            'logistics' => [
                                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard'],
                                ['label' => 'Transferencias', 'route' => 'admin.transfers', 'pattern' => 'admin.transfers'],
                                ['label' => 'Devoluciones', 'route' => 'admin.returns', 'pattern' => 'admin.returns'],
                                ['label' => 'Histórico de devoluciones', 'route' => 'admin.returns.history', 'pattern' => 'admin.returns.history'],
                                ['label' => 'Materiales', 'route' => 'admin.materials', 'pattern' => 'admin.materials'],
                                ['label' => 'Crear material', 'route' => 'admin.materials.create', 'pattern' => 'admin.materials.create'],
                                ['label' => 'Órdenes de trabajo', 'route' => 'admin.work-orders.index', 'pattern' => 'admin.work-orders.*'],
                                ['label' => 'Técnicos', 'route' => 'admin.technicians.overview', 'pattern' => ['admin.technicians.overview', 'admin.technicians.stock.overview', 'admin.technicians.transfers.history', 'admin.technicians.returns.history']],
                                ['label' => 'Histórico de almacén', 'route' => 'admin.warehouse-movements', 'pattern' => 'admin.warehouse-movements'],
                            ],
                            'technician' => [
                                ['label' => 'Dashboard', 'route' => 'technician.dashboard', 'pattern' => 'technician.dashboard'],
                                ['label' => 'Mi stock', 'route' => 'technician.stock', 'pattern' => 'technician.stock'],
                                ['label' => 'Transferencias', 'route' => 'technician.transfers', 'pattern' => 'technician.transfers'],
                                ['label' => 'Histórico de transferencias', 'route' => 'technician.transfers.history', 'pattern' => 'technician.transfers.history', 'params' => [auth()->user()]],
                                ['label' => 'Histórico de devoluciones', 'route' => 'technician.returns.history', 'pattern' => 'technician.returns.history'],
                                ['label' => 'Órdenes de trabajo', 'route' => 'technician.work-orders', 'pattern' => 'technician.work-orders'],
                            ],
                        ];
                        $menuItems = $menuByRole[$navRole] ?? [];
                    @endphp
                @endauth

                {{-- Navegación izquierda (items del menú) --}}
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    @auth
                        @foreach ($menuItems as $item)
                            @php
                                $pattern   = $item['pattern'] ?? $item['route'];
                                $isActive  = request()->routeIs($pattern);
                                $isCta     = !empty($item['cta']);
                                $linkClasses = $isCta
                                    ? 'btn btn-cta btn-sm' . ($isActive ? ' active' : '')
                                    : 'nav-link' . ($isActive ? ' active' : '');
                            @endphp
                            <li class="nav-item {{ $isCta ? 'ms-lg-2 mt-2 mt-lg-0' : '' }}">
                                <a class="{{ $linkClasses }}"
                                   href="{{ route($item['route'], $item['params'] ?? []) }}"
                                   @if($isActive) aria-current="page" @endif>
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    @endauth
                </ul>

                {{-- Navegación derecha (usuario) --}}
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    @auth
                        <li class="nav-item d-flex align-items-center text-white me-3">
                            <span class="small">Hola, {{ auth()->user()->name }}</span>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-light btn-sm">Cerrar sesión</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Iniciar sesión</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

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
