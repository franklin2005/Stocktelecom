@extends('layouts.app')

@section('content')
    <div class="card shadow-sm mx-auto" style="max-width: 420px;">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">{{ $title }}</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('login.submit') }}">
                @csrf
                <input type="hidden" name="role_key" value="{{ $roleKey }}">

                <div class="mb-3">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Recordarme</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">Entrar</button>
            </form>

            <div class="mt-3 text-center">
                <a href="{{ route('login') }}" class="small">&larr; Volver a la selección de perfil</a>
            </div>
        </div>
    </div>
@endsection
