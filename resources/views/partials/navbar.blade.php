<header class="bg-dark text-white py-2">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        <a class="navbar-brand text-white fw-semibold d-flex align-items-center" href="{{ route('home') }}">
            <i class="bi bi-box-seam me-2"></i>
            STOCKTELECOM
        </a>

        <div class="d-flex align-items-center gap-3">
            @auth
                {{-- Botón menú móvil --}}
                <button class="btn btn-outline-light btn-sm d-lg-none"
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#mobileSidebar"
                        aria-controls="mobileSidebar">
                    <i class="bi bi-list me-1"></i>
                    Menú
                </button>

                {{-- Saludo --}}
                <span class="small text-white d-none d-md-inline">
                    Hola, {{ auth()->user()->name }}
                </span>

                {{-- Perfil --}}
                <a href="{{ route('profile.show') }}" class="btn btn-accent-st btn-sm">
                    <i class="bi bi-person-circle me-1"></i>
                    Perfil
                </a>

                {{-- Logout --}}
                <form method="POST" action="{{ route('logout') }}" class="d-inline" id="logout-form">
                    @csrf
                    <button type="submit" class="btn btn-danger-st btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>
                        Cerrar sesión
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-success-st btn-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i>
                    Selección de perfil
                </a>
            @endauth
        </div>
    </div>
</header>

@auth
    {{-- Menú móvil (offcanvas) --}}
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title d-flex align-items-center" id="mobileSidebarLabel">
                <i class="bi bi-list me-2"></i>
                Menú
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <div class="list-group list-group-flush">
                @foreach ($menuItems as $item)
                    @php
                        $pattern = $item['pattern'] ?? $item['route'];
                        $isActive = request()->routeIs($pattern);
                        $classes = 'list-group-item list-group-item-action d-flex align-items-center';
                        if ($isActive) {
                            $classes .= ' active';
                        }
                    @endphp

                    <a href="{{ route($item['route'], $item['params'] ?? []) }}"
                       class="{{ $classes }}"
                       @if($isActive) aria-current="true" @endif>
                        @if (!empty($item['icon']))
                            <i class="bi bi-{{ $item['icon'] }} sidebar-icon me-2"></i>
                        @endif
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endauth

<script>
document.getElementById('logout-form').addEventListener('submit', function (e) {
    if (!confirm('¿Seguro que deseas cerrar sesión?')) {
        e.preventDefault();
    }
});
</script>
