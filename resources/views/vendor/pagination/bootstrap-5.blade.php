@if ($paginator->hasPages())
    <nav class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        {{-- Texto "Mostrando X a Y de Z resultados" --}}
        <div>
            <p class="small text-muted mb-0">
                Mostrando
                <span class="fw-semibold">{{ $paginator->firstItem() }}</span>
                a
                <span class="fw-semibold">{{ $paginator->lastItem() }}</span>
                de
                <span class="fw-semibold">{{ $paginator->total() }}</span>
                resultados
            </p>
        </div>

        {{-- Paginador --}}
        <div>
            <ul class="pagination mb-0">
                {{-- Botón Anterior --}}
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true" aria-label="Anterior">
                        <span class="page-link small">Anterior</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link small" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Anterior">
                            Anterior
                        </a>
                    </li>
                @endif

                {{-- Números de página --}}
                @foreach ($elements as $element)
                    {{-- "Tres puntos" --}}
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true">
                            <span class="page-link small">{{ $element }}</span>
                        </li>
                    @endif

                    {{-- Links individuales --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active" aria-current="page">
                                    <span class="page-link small">{{ $page }}</span>
                                </li>
                            @else
                                <li class="page-item">
                                    <a class="page-link small" href="{{ $url }}">{{ $page }}</a>
                                </li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Botón Siguiente --}}
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link small" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Siguiente">
                            Siguiente
                        </a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true" aria-label="Siguiente">
                        <span class="page-link small">Siguiente</span>
                    </li>
                @endif
            </ul>
        </div>
    </nav>
@endif
