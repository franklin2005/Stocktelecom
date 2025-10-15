@php
    $role = $role ?? (auth()->user()->role ?? null);
    $menuItems = $menuItems ?? [];
@endphp

<div class="d-flex flex-column flex-grow-1 py-3">
    <h6 class="px-3 text-uppercase text-muted">Menú</h6>
    <div class="list-group list-group-flush">
        @forelse ($menuItems as $item)
            @php $pattern = $item['pattern'] ?? $item['route']; @endphp
            <a href="{{ route($item['route'], $item['params'] ?? []) }}"
               class="list-group-item list-group-item-action {{ request()->routeIs($pattern) ? 'active' : '' }}">
                {{ $item['label'] }}
            </a>
        @empty
            <span class="list-group-item text-muted">Sin opciones disponibles.</span>
        @endforelse
    </div>
</div>