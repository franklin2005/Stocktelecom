<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>STOCKTELECOM</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    {{-- Bootstrap + jQuery --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    {{-- Estilos del proyecto --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/mainStyle.css') }}">

</head>
<body class="bg-light">

    @php
        $navRole = auth()->user()->role ?? null;

        // ----------------------------
        //   MENÚ POR ROL + ICONOS
        // ----------------------------

        $menuByRole = [
            'super_admin' => [
                ['label' => 'Devoluciones',               'route' => 'admin.returns',            'pattern' => 'admin.returns',             'icon' => 'arrow-left-right'],
                ['label' => 'Histórico de devoluciones',  'route' => 'admin.returns.history',    'pattern' => 'admin.returns.history',     'icon' => 'clock-history'],
                ['label' => 'Histórico de almacén',       'route' => 'admin.warehouse-movements','pattern' => 'admin.warehouse-movements', 'icon' => 'clock-history'],
                ['label' => 'Histórico de usuarios',      'route' => 'admin.user-history',       'pattern' => 'admin.user-history',        'icon' => 'clock-history'],
                ['label' => 'Materiales',                 'route' => 'admin.materials',          'pattern' => 'admin.materials',           'icon' => 'box-seam'],
                ['label' => 'Crear material',             'route' => 'admin.materials.create',   'pattern' => 'admin.materials.create',    'icon' => 'plus-circle'],
                ['label' => 'Órdenes de trabajo',         'route' => 'admin.work-orders.index',  'pattern' => 'admin.work-orders.*',       'icon' => 'clipboard-check'],
                ['label' => 'Personal',                   'route' => 'admin.personnel',          'pattern' => 'admin.personnel*',          'icon' => 'people'],
                ['label' => 'Transferencias',             'route' => 'admin.transfers',          'pattern' => 'admin.transfers',           'icon' => 'arrow-repeat'],
                ['label' => 'Técnicos',                   'route' => 'admin.technicians.overview','pattern' => ['admin.technicians.overview', 'admin.technicians.stock.overview', 'admin.technicians.transfers.history', 'admin.technicians.returns.history'], 'icon' => 'person-badge'],
            ],

            'admin' => [
                ['label' => 'Histórico de almacén',       'route' => 'admin.warehouse-movements','pattern' => 'admin.warehouse-movements', 'icon' => 'clock-history'],
                ['label' => 'Histórico de usuarios',      'route' => 'admin.user-history',       'pattern' => 'admin.user-history',        'icon' => 'clock-history'],
                ['label' => 'Materiales',                 'route' => 'admin.materials',          'pattern' => 'admin.materials',           'icon' => 'box-seam'],
                ['label' => 'Órdenes de trabajo',         'route' => 'admin.work-orders.index',  'pattern' => 'admin.work-orders.*',       'icon' => 'clipboard-check'],
                ['label' => 'Personal',                   'route' => 'admin.personnel',          'pattern' => 'admin.personnel*',          'icon' => 'people'],
                ['label' => 'Técnicos',                   'route' => 'admin.technicians.overview','pattern' => ['admin.technicians.overview', 'admin.technicians.stock.overview', 'admin.technicians.transfers.history'], 'icon' => 'person-badge'],
            ],

            'logistics' => [
                ['label' => 'Devoluciones',               'route' => 'admin.returns',            'pattern' => 'admin.returns',             'icon' => 'arrow-left-right'],
                ['label' => 'Histórico de almacén',       'route' => 'admin.warehouse-movements','pattern' => 'admin.warehouse-movements', 'icon' => 'clock-history'],
                ['label' => 'Histórico de devoluciones',  'route' => 'admin.returns.history',    'pattern' => 'admin.returns.history',     'icon' => 'clock-history'],
                ['label' => 'Materiales',                 'route' => 'admin.materials',          'pattern' => 'admin.materials',           'icon' => 'box-seam'],
                ['label' => 'Crear material',             'route' => 'admin.materials.create',   'pattern' => 'admin.materials.create',    'icon' => 'plus-circle'],
                ['label' => 'Órdenes de trabajo',         'route' => 'admin.work-orders.index',  'pattern' => 'admin.work-orders.*',       'icon' => 'clipboard-check'],
                ['label' => 'Transferencias',             'route' => 'admin.transfers',          'pattern' => 'admin.transfers',           'icon' => 'arrow-repeat'],
                ['label' => 'Técnicos',                   'route' => 'admin.technicians.overview','pattern' => ['admin.technicians.overview', 'admin.technicians.stock.overview', 'admin.technicians.transfers.history', 'admin.technicians.returns.history'], 'icon' => 'person-badge'],
            ],

            'technician' => [
                ['label' => 'Histórico de devoluciones',  'route' => 'technician.returns.history','pattern' => 'technician.returns.history','icon' => 'clock-history'],
                ['label' => 'Histórico de transferencias','route' => 'technician.transfers.history', 'pattern' => 'technician.transfers.history', 'params' => [auth()->user()], 'icon' => 'clock-history'],
                ['label' => 'Mi stock',                   'route' => 'technician.stock',         'pattern' => 'technician.stock',          'icon' => 'boxes'],
                ['label' => 'Órdenes de trabajo',         'route' => 'technician.work-orders',   'pattern' => 'technician.work-orders',    'icon' => 'clipboard-check'],
                ['label' => 'Transferencias',             'route' => 'technician.transfers',     'pattern' => 'technician.transfers',      'icon' => 'arrow-repeat'],
            ],
        ];

        $menuItemsRaw = $menuByRole[$navRole] ?? [];
        usort($menuItemsRaw, fn($a, $b) => strcmp(mb_strtolower($a['label']), mb_strtolower($b['label'])));
        $menuItems = $menuItemsRaw;

    @endphp

    {{-- NAVBAR + MENÚ MÓVIL --}}
    @include('partials.navbar', ['menuItems' => $menuItems, 'navRole' => $navRole])


    {{-- LAYOUT --}}
    <div class="container-fluid">
        <div class="row">

            @auth
                {{-- Sidebar escritorio --}}
                <aside class="d-none d-lg-block col-lg-2 p-0">
                    @include('partials.sidebar', ['menuItems' => $menuItems, 'role' => $navRole])
                </aside>

                {{-- Contenido principal --}}
                <main class="col-12 col-lg-10 px-4 py-4">
                    @yield('content')
                </main>

            @else
                {{-- Vista invitado --}}
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
