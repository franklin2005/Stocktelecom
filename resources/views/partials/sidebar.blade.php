@php
    $role = $role ?? (auth()->user()->role ?? null);
    $menuItems = $menuItems ?? [];

    // Ordenar menú alfabéticamente
    usort($menuItems, function ($a, $b) {
        return strcmp(mb_strtolower($a['label']), mb_strtolower($b['label']));
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
            @forelse ($menuItems as $item)
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

                    // Si el item tiene 'icon' definido, lo usamos; si no, lo deducimos por label
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
        </div>

        <div class="mt-auto px-3 pt-3 border-top">
            <small class="st-muted d-block">{{ $roleLabel }}</small>
        </div>
    </div>
</aside>
