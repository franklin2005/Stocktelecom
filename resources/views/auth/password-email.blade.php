@extends('layouts.app')

@section('content')
    <div class="st-card mx-auto p-4" style="max-width: 420px;">
        <h4 class="mb-3 text-center fw-semibold text-primary">
            <i class="bi bi-envelope-open me-2"></i>
            ¿Olvidaste tu contraseña?
        </h4>

        <p class="st-muted small text-center mb-3">
            Ingresa tu correo electrónico y te enviaremos un enlace seguro para crear una nueva contraseña.
        </p>

        @if (session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Ocurrió un error:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Correo electrónico</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="form-control @error('email') is-invalid @enderror"
                    required
                    autofocus
                    placeholder="tu@correo.com"
                >
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-st w-100">
                <i class="bi bi-send me-1"></i>
                Enviar enlace de recuperación
            </button>
        </form>

        <div class="mt-3 text-center">
            <a href="{{ route('login') }}" class="small text-decoration-none st-muted">
                &larr; Volver al inicio de sesión
            </a>
        </div>
    </div>
@endsection
