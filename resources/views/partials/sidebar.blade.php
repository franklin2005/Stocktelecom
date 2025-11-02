@php
    $role = $role ?? (auth()->user()->role ?? null);
    $menuItems = $menuItems ?? [];

    // Traducción de roles
    $roleLabels = [
        'technician'   => 'Técnico',
        'logistics'    => 'Logística',
        'admin'        => 'Administrador',
        'super_admin'  => 'Super Administrador',
    ];

    $roleLabel = $roleLabels[$role] ?? 'Usuario';
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
                @endphp

                <a href="{{ route($item['route'], $item['params'] ?? []) }}" class="{{ $classes }}">
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
