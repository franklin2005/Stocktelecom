@extends('layouts.app')

@section('content')
    <div class="text-center py-5">
        <div class="spinner-border text-primary mb-3" role="status">
            <span class="visually-hidden">Redirigiendo...</span>
        </div>
        <p class="text-muted">Redirigiendo al portal de acceso...</p>
    </div>
@endsection

@push('scripts')
    <script>
        window.location.href = "{{ route('login') }}";
    </script>
@endpush
