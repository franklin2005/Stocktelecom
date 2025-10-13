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
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    @auth
                        @php $navRole = auth()->user()->role ?? null; @endphp
                        @if(in_array($navRole, ['admin', 'super_admin'], true))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.dashboard') }}">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.transfers') }}">Transferencias</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.materials') }}">Materiales</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.personnel') }}">Personal</a>
                            </li>
                        @elseif(auth()->user()->role === 'logistics')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.dashboard') }}">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.transfers') }}">Transferencias</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.materials') }}">Materiales</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('technicians.overview') }}">Tecnicos</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.warehouse-movements') }}">Historico almacen</a>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('technician.dashboard') }}">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('technician.stock') }}">Mi Stock</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('technician.transfers') }}">Transferencias</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('technicians.transfers.history', auth()->user()) }}">Historico de transferencias</a>
                            </li>
                        @endif
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
                                <button type="submit" class="btn btn-outline-light btn-sm">Cerrar sesion</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Iniciar sesion</a>
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
                    @include('partials.sidebar')
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
