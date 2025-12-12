@php
    $role = $role ?? (auth()->user()->role ?? null);
    $menuItems = $menuItems ?? [];

    // Separar históricos y resto, y ordenar alfabéticamente cada grupo
    $historyItems = array_filter($menuItems, function ($item) {
        $label = $item['label'] ?? '';
        return stripos($label, 'Hist') === 0;
    });
    $nonHistoryItems = array_filter($menuItems, function ($item) use ($historyItems) {
        return ! in_array($item, $historyItems, true);
    });

    $sortFn = function (&$items) {
        usort($items, function ($a, $b) {
            return strcmp(mb_strtolower($a['label']), mb_strtolower($b['label']));
        });
    };
    $sortFn($historyItems);
    $sortFn($nonHistoryItems);

    $historyActive = collect($historyItems)->contains(function ($item) {
        $pattern = $item['pattern'] ?? $item['route'] ?? null;
        return $pattern ? request()->routeIs($pattern) : false;
    });

    // Traducción de roles
    $roleLabels = [
        'technician'   => 'Técnico',
        'logistics'    => 'Logística',
        'admin'        => 'Administrador',
        'super_admin'  => 'Super Administrador',
    ];

    $roleLabel = $roleLabels[$role] ?? 'Usuario';

    /**
     * Devuelve el icono (Bootstrap Icons) según el label del item.
     */
    function guessMenuIcon(array $item): ?string {
        $label = mb_strtolower(trim($item['label'] ?? ''));

        // Quitar posibles tildes "rotas" por si acaso
        $label = str_replace(
            ['á','é','í','ó','ú','Á','É','Í','Ó','Ú'],
            ['a','e','i','o','u','A','E','I','O','U'],
            $label
        );

        return match ($label) {
            'crear material'           => 'plus-circle',
            'dashboard'                => 'speedometer2',
            'devoluciones'             => 'arrow-left-right',

            'historico de almacen',
            'historico de almacén'     => 'clock-history',

            'historico de devoluciones'=> 'clock-history',

            'historico de transferencias' => 'clock-history',
            'historico de usuarios'       => 'clock-history',

            'materiales'              => 'box-seam',
            'transferencias'          => 'arrow-repeat',
            'tecnicos',
            'técnicos'                => 'person-badge',

            'ordenes de trabajo',
            'órdenes de trabajo'      => 'clipboard-check',

            'mi stock'                => 'boxes',
            'personal'                => 'people',

            default                   => null,
        };
    }
@endphp

<aside class="d-none d-lg-block bg-white border-end vh-100 shadow-sm">
    <div class="d-flex flex-column h-100 py-3">
        <h6 class="px-3 text-uppercase st-muted small mb-3">Menú principal</h6>

        <div class="list-group list-group-flush flex-grow-1">
            @forelse ($nonHistoryItems as $item)
                @php
                    $pattern = $item['pattern'] ?? $item['route'];
                    $isActive = request()->routeIs($pattern);
                    $isCta = !empty($item['cta']);

                    $classes = 'list-group-item list-group-item-action border-0 rounded-0 py-2 px-3';

                    if ($isCta) {
                        $classes .= ' text-center fw-semibold st-accent';
                        if ($isActive) {
                            $classes .= ' active bg-accent text-white';
                        }
                    } else {
                        $classes .= $isActive
                            ? ' active bg-light text-primary fw-semibold'
                            : ' text-body';
                    }

                    $icon = $item['icon'] ?? guessMenuIcon($item);
                @endphp

                <a href="{{ route($item['route'], $item['params'] ?? []) }}" class="{{ $classes }}">
                    @if (!empty($icon))
                        <i class="bi bi-{{ $icon }} sidebar-icon me-2"></i>
                    @endif
                    {{ $item['label'] }}
                </a>
            @empty
                <span class="list-group-item text-muted">Sin opciones disponibles</span>
            @endforelse

            @if (!empty($historyItems))
                @php $collapseId = 'sidebarHistoricos'; @endphp
                <div class="list-group-item border-0 rounded-0 py-0 px-0">
                    <button class="w-100 text-start btn btn-link px-3 py-2 text-decoration-none {{ $historyActive ? 'fw-semibold text-primary' : 'text-body' }}"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $collapseId }}"
                            aria-expanded="{{ $historyActive ? 'true' : 'false' }}"
                            aria-controls="{{ $collapseId }}">
                        <i class="bi bi-clock-history sidebar-icon me-2"></i>Históricos
                        <i class="bi bi-chevron-down float-end small"></i>
                    </button>
                    <div class="collapse {{ $historyActive ? 'show' : '' }}" id="{{ $collapseId }}">
                        <div class="list-group list-group-flush">
                            @foreach ($historyItems as $item)
                                @php
                                    $pattern = $item['pattern'] ?? $item['route'];
                                    $isActive = request()->routeIs($pattern);
                                    $icon = $item['icon'] ?? guessMenuIcon($item);
                                @endphp
                                <a href="{{ route($item['route'], $item['params'] ?? []) }}" class="list-group-item list-group-item-action border-0 px-4 py-1 {{ $isActive ? 'text-primary fw-semibold' : 'text-body' }}">
                                    @if (!empty($icon))
                                        <i class="bi bi-{{ $icon }} sidebar-icon me-2"></i>
                                    @endif
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="mt-auto px-3 pt-3 border-top">
            <small class="st-muted d-block">{{ $roleLabel }}</small>
        </div>
    </div>
</aside>
