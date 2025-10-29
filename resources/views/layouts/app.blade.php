<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel Inventario FTTH') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('home') }}">
                Inventario FTTH
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
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
                                ['label' => 'Materiales', 'route' => 'admin.materials', 'pattern' => 'admin.materials'],
                                ['label' => 'Personal', 'route' => 'admin.personnel', 'pattern' => 'admin.personnel*'],
                                ['label' => 'Histórico de usuarios', 'route' => 'admin.user-history', 'pattern' => 'admin.user-history'],
                                ['label' => 'Órdenes de trabajo', 'route' => 'admin.work-orders.index', 'pattern' => 'admin.work-orders.*'],
                                ['label' => 'Histórico de almacén', 'route' => 'admin.warehouse-movements', 'pattern' => 'admin.warehouse-movements'],
                                ['label' => 'Técnicos', 'route' => 'admin.technicians.overview', 'pattern' => ['admin.technicians.overview', 'admin.technicians.stock.overview', 'admin.technicians.transfers.history']],
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
                                ['label' => 'Materiales', 'route' => 'admin.materials', 'pattern' => 'admin.materials'],
                                ['label' => 'Órdenes de trabajo', 'route' => 'admin.work-orders.index', 'pattern' => 'admin.work-orders.*'],
                                ['label' => 'Técnicos', 'route' => 'admin.technicians.overview', 'pattern' => ['admin.technicians.overview', 'admin.technicians.stock.overview', 'admin.technicians.transfers.history']],
                                ['label' => 'Histórico de almacén', 'route' => 'admin.warehouse-movements', 'pattern' => 'admin.warehouse-movements'],
                            ],
                            'technician' => [
                                ['label' => 'Dashboard', 'route' => 'technician.dashboard', 'pattern' => 'technician.dashboard'],
                                ['label' => 'Mi stock', 'route' => 'technician.stock', 'pattern' => 'technician.stock'],
                                ['label' => 'Transferencias', 'route' => 'technician.transfers', 'pattern' => 'technician.transfers'],
                                ['label' => 'Histórico de transferencias', 'route' => 'technician.transfers.history', 'pattern' => 'technician.transfers.history', 'params' => [auth()->user()]],
                                ['label' => 'Órdenes de trabajo', 'route' => 'technician.work-orders', 'pattern' => 'technician.work-orders'],
                            ],
                        ];
                        $menuItems = $menuByRole[$navRole] ?? [];
                    @endphp
                @endauth
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    @auth
                        @foreach ($menuItems as $item)
                            @php $pattern = $item['pattern'] ?? $item['route']; @endphp
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($item['route'], $item['params'] ?? []) }}">
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    @endauth
                </ul>
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

    <div class="container-fluid">
        <div class="row">
            @auth
                <aside class="col-md-3 col-lg-2 d-none d-md-block bg-white border-end min-vh-100">
                    @php
                        $navRole = $navRole ?? (auth()->user()->role ?? null);
                        $menuItems = $menuItems ?? [];
                    @endphp
                    @include('partials.sidebar', ['menuItems' => $menuItems, 'role' => $navRole])
                </aside>
                <main class="col-12 col-md-9 col-lg-10 ms-sm-auto px-4 py-4">
                    @yield('content')
                </main>
            @else
                <main class="col-12 col-md-8 offset-md-2 col-lg-6 offset-lg-3 py-5">
                    @yield('content')
                </main>
            @endauth
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
