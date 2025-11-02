@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-center py-5">
        <div class="st-card p-4 text-center" style="max-width: 460px; width: 100%;">
            <div class="mb-3" aria-hidden="true">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando…</span>
                </div>
            </div>
            <h1 class="h5 mb-2">Redirigiendo…</h1>
            <p class="st-muted mb-3">Te estamos llevando al portal de acceso.</p>

            {{-- Enlace de respaldo por si la redirección con JS falla --}}
            <a href="{{ route('login') }}" class="btn btn-st">Ir al inicio de sesión</a>

            {{-- Aviso para usuarios sin JavaScript --}}
            <noscript>
                <div class="alert alert-warning mt-3 mb-0" role="alert">
                    La redirección automática requiere JavaScript. Haz clic en “Ir al inicio de sesión”.
                </div>
            </noscript>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Redirige con un pequeño retardo para mostrar el spinner.
        setTimeout(function () {
            window.location.replace("{{ route('login') }}");
        }, 400);
    </script>
@endpush
