@extends('layouts.app')

@php
    $tabs = [
        'technicians' => 'Tecnicos',
        'logistics' => 'Logistica',
        'admins' => 'Administradores',
        'super_admins' => 'Super Administradores',
    ];
@endphp

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h2 class="mb-0">Gestion de personal</h2>
    </div>

    @if ($errors->getBag('deleteStaff')->has('general'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ $errors->getBag('deleteStaff')->first('general') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    <ul class="nav nav-pills mb-4">
        @foreach ($tabs as $tabKey => $tabLabel)
            <li class="nav-item me-2">
                <a href="{{ route('admin.personnel', ['tab' => $tabKey]) }}" class="nav-link {{ $activeTab === $tabKey ? 'active' : '' }}">
                    {{ $tabLabel }}
                </a>
            </li>
        @endforeach
    </ul>

    @if ($activeTab === 'technicians')
        @include('admin.personnel.tabs.technicians')
    @elseif ($activeTab === 'logistics')
        @include('admin.personnel.tabs.logistics')
    @elseif ($activeTab === 'admins')
        @include('admin.personnel.tabs.admins')
    @elseif ($activeTab === 'super_admins')
        @include('admin.personnel.tabs.super_admins')
    @endif
@endsection
