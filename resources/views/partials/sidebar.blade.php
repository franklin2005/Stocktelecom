@php
    $role = $role ?? (auth()->user()->role ?? null);
    $menuItems = $menuItems ?? [];
@endphp

<div class="d-flex flex-column flex-grow-1 py-3">
    <h6 class="px-3 text-uppercase text-muted">Menú</h6>
    <div class="list-group list-group-flush">
        @forelse ($menuItems as $item)
            @php
                $pattern = $item['pattern'] ?? $item['route'];
                $isActive = request()->routeIs($pattern);
                $isCta = ! empty($item['cta']);
                $baseClass = 'list-group-item list-group-item-action';
                if ($isCta) {
                    $classes = $baseClass . ' list-group-item-primary fw-semibold text-primary-emphasis';
                    if ($isActive) {
                        $classes .= ' active bg-success border-success text-white';
                    }
                } else {
                    $classes = $baseClass . ($isActive ? ' active' : '');
                }
            @endphp
            <a href="{{ route($item['route'], $item['params'] ?? []) }}" class="{{ $classes }}">
                {{ $item['label'] }}
            </a>
        @empty
            <span class="list-group-item text-muted">Sin opciones disponibles.</span>
        @endforelse
    </div>
</div>
