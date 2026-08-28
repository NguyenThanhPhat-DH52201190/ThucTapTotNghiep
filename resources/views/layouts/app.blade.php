<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f8fafc;
        }

        a[aria-expanded="true"] .bi-chevron-down {
            transform: rotate(180deg);
            transition: 0.3s;
        }

        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: #1e293b;
        }

        .sidebar a {
            color: #cbd5f5;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            border-radius: 8px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #334155;
            color: #fff;
        }

        .topbar {
            height: 60px;
            background: #f59e0b;
        }
    </style>
</head>

<body>

    @php
    $role = auth()->user()->role;
    @endphp

    <div class="d-flex">

        <!-- SIDEBAR -->
        <div class="sidebar p-3">
            <h5 class="text-white mb-4 text-center"> GSV</h5>

            @if($role === 'admin')
            <a class="d-flex justify-content-between align-items-center text-white fw-semibold mb-2 px-3 py-2 rounded"
                data-bs-toggle="collapse" href="#adminMenu" role="button">

                <!-- LEFT -->
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-gear"></i>
                    Admin
                </span>

                <!-- RIGHT -->
                <i class="bi bi-chevron-down small"></i>
            </a>

            <div class="collapse ps-4" id="adminMenu">
                <a href="{{ route('admin.ocs.index') }}"
                class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-speedometer2"></i>
                    Order Cut Sheet
                </a>

                <a href="{{ route('admin.master-data.customers') }}" class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-people"></i>
                    Customer Master
                </a>

                <a href="{{ route('admin.master-data.materials') }}" class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-box-seam"></i>
                    Material Master
                </a>

                <a href="{{ route('admin.bom.index') }}"
                    class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-file-text"></i>
                    BOM & Tech Pack
                </a>

                <a href="{{ route('admin.masterplan.index') }}"
                    class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-people"></i>
                    Master Plan (MPS)
                </a>

                <a href="{{ route('admin.production-planning.index') }}"
                    class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-calendar-week"></i>
                    Production Planning
                </a>

                <a href="{{ route('admin.mrp.index') }}"
                    class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-calculator"></i>
                    MRP
                </a>

                <a href="{{ route('admin.procurement.index') }}"
                    class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-cart3"></i>
                    Procurement
                </a>

                <a href="{{ route('admin.inventory.index') }}"
                    class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-boxes"></i>
                    Inventory
                </a>

                <a href="{{ route('admin.shopfloor.dashboard') }}"
                    class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-gear"></i>
                    Shop Floor
                </a>

                <a href="{{ route('admin.finance.dashboard') }}"
                    class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-graph-up-arrow"></i>
                    Finance & Costing
                </a>

                <a href="{{ route('admin.revenue.index') }}"
                    class="d-flex align-items-center gap-2">
                    <i class="bi bi-bar-chart"></i>
                    Revenue
                </a>

                <a href="{{ route('admin.colors.index') }}"
                    class="d-flex align-items-center gap-2 mt-1">
                    <i class="bi bi-palette"></i>
                    Line Colors
                </a>
                <a href="{{ route('admin.audit-trails.index') }}" class="d-flex align-items-center gap-2 mt-1">
                    <i class="bi bi-clock-history"></i>
                    Audit Trail
                </a>
            </div>
            @elseif($role === 'ppic')
            <a href="{{ route('admin.masterplan.index') }}" class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-people"></i>
                Master Plan
            </a>
            <a href="{{ route('admin.mrp.index') }}" class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-calculator"></i>
                MRP & Procurement
            </a>
            @elseif($role === 'ie' || $role === 'prod')
            @if($role === 'ie')
            <a href="{{ route('admin.bom.index') }}" class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-file-text"></i>
                BOM & Tech Pack
            </a>
            @else
            <a href="{{ route('admin.shopfloor.dashboard') }}" class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-gear"></i>
                Shop Floor
            </a>
            @endif
            @if($role === 'prod')
            <a href="{{ route('revenue.view') }}" class="d-flex align-items-center gap-2">
                <i class="bi bi-bar-chart"></i>
                Revenue
            </a>
            @endif
            @elseif($role === 'warehouse')
            <a href="{{ route('admin.inventory.index') }}" class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-boxes"></i>
                Inventory & Issue
            </a>
            @elseif($role === 'accountant')
            <a href="{{ route('admin.finance.dashboard') }}" class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-graph-up-arrow"></i>
                Finance & Costing
            </a>
            @endif
        </div>

        <!-- MAIN -->
        <div class="flex-grow-1">

            <!-- TOPBAR -->
            <div class="topbar d-flex justify-content-between align-items-center px-4">
                <h6 class="mb-0 fw-bold text-dark">@yield('title', 'Dashboard')</h6>

                <div class="d-flex align-items-center gap-3">
                    <span>{{ auth()->user()->name }}</span>
                    <span class="badge bg-dark text-uppercase">{{ $role }}</span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm btn-dark">Logout</button>
                    </form>
                </div>
            </div>

            <!-- CONTENT -->
            <div class="p-4">
                @yield('content')
            </div>

        </div>
    </div>

    @stack('scripts')
</body>

</html>
