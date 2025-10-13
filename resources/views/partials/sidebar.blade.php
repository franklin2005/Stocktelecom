<div class="d-flex flex-column flex-grow-1 py-3">
    <h6 class="px-3 text-uppercase text-muted">Menu</h6>
    <div class="list-group list-group-flush">
        @php
            $role = auth()->user()->role ?? null;
        @endphp
        @if(in_array($role, ['admin', 'super_admin'], true))
            <a href="{{ route('admin.dashboard') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                Dashboard
            </a>
            <a href="{{ route('admin.transfers') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.transfers') ? 'active' : '' }}">
                Transferencias
            </a>
            <a href="{{ route('admin.materials') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.materials') ? 'active' : '' }}">
                Materiales
            </a>
            <a href="{{ route('admin.personnel') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.personnel*') ? 'active' : '' }}">
                Personal
            </a>
            <a href="{{ route('admin.user-history') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.user-history') ? 'active' : '' }}">
                Historico usuarios
            </a>
            <a href="{{ route('admin.work-orders.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.work-orders.*') ? 'active' : '' }}">
                Ordenes de trabajo
            </a>
            <a href="{{ route('admin.warehouse-movements') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.warehouse-movements') ? 'active' : '' }}">
                Historico almacen
            </a>
        @elseif(auth()->user()->role === 'logistics')
            <a href="{{ route('admin.dashboard') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                Dashboard
            </a>
            <a href="{{ route('admin.transfers') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.transfers') ? 'active' : '' }}">
                Transferencias
            </a>
            <a href="{{ route('admin.materials') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.materials') ? 'active' : '' }}">
                Materiales
            </a>
            <a href="{{ route('admin.work-orders.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.work-orders.*') ? 'active' : '' }}">
                Ordenes de trabajo
            </a>
            <a href="{{ route('technicians.overview') }}" class="list-group-item list-group-item-action {{ request()->routeIs('technicians.overview') ? 'active' : '' }}">
                Tecnicos
            </a>
            <a href="{{ route('admin.warehouse-movements') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.warehouse-movements') ? 'active' : '' }}">
                Historico almacen
            </a>
        @else
            <a href="{{ route('technician.dashboard') }}" class="list-group-item list-group-item-action {{ request()->routeIs('technician.dashboard') ? 'active' : '' }}">
                Dashboard
            </a>
            <a href="{{ route('technician.stock') }}" class="list-group-item list-group-item-action {{ request()->routeIs('technician.stock') ? 'active' : '' }}">
                Mi stock
            </a>
            <a href="{{ route('technician.transfers') }}" class="list-group-item list-group-item-action {{ request()->routeIs('technician.transfers') ? 'active' : '' }}">
                Transferencias
            </a>
            <a href="{{ route('technicians.transfers.history', auth()->user()) }}" class="list-group-item list-group-item-action {{ request()->routeIs('technicians.transfers.history') ? 'active' : '' }}">
                Historico transferencias
            </a>
            <a href="{{ route('technician.work-orders') }}" class="list-group-item list-group-item-action {{ request()->routeIs('technician.work-orders') ? 'active' : '' }}">
                Ordenes de trabajo
            </a>
        @endif
    </div>
</div>
