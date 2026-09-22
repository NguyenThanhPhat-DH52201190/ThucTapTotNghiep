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

        .module-back-bar {
            margin-bottom: 1rem;
        }
    </style>
    <link href="{{ asset('css/responsive.css') }}?v={{ filemtime(public_path('css/responsive.css')) }}" rel="stylesheet">
    <script src="{{ asset('js/responsive.js') }}?v={{ filemtime(public_path('js/responsive.js')) }}" defer></script>
</head>

<body>

    @php
    $role = auth()->user()->role;
    @endphp

    <div class="d-flex app-shell">

        <!-- SIDEBAR -->
        <nav class="sidebar offcanvas-lg offcanvas-start" id="appSidebar" tabindex="-1" aria-labelledby="sidebarTitle">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title text-white" id="sidebarTitle">GSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close navigation"></button>
            </div>
            <div class="offcanvas-body d-block p-3">
            <h5 class="text-white mb-4 text-center d-none d-lg-block" aria-hidden="true">GSV</h5>

            @if($role === 'admin')
            <a class="d-flex justify-content-between align-items-center text-white fw-semibold mb-2 px-3 py-2 rounded"
                data-bs-toggle="collapse" href="#adminMenu" role="button" aria-expanded="true" aria-controls="adminMenu">

                <!-- LEFT -->
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-gear"></i>
                    Admin
                </span>

                <!-- RIGHT -->
                <i class="bi bi-chevron-down small"></i>
            </a>

            <div class="collapse show ps-3" id="adminMenu">
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

                <a class="d-flex justify-content-between align-items-center gap-2 mb-1"
                    data-bs-toggle="collapse" href="#normMenu" role="button" aria-expanded="{{ request()->routeIs('admin.norm.*') ? 'true' : 'false' }}">
                    <span class="d-flex align-items-center gap-2"><i class="bi bi-rulers"></i>NORM</span>
                    <i class="bi bi-chevron-down small"></i>
                </a>
                <div class="collapse ps-3 {{ request()->routeIs('admin.norm.*') ? 'show' : '' }}" id="normMenu">
                    <a href="{{ route('admin.norm.materials') }}" class="d-flex align-items-center gap-2 mb-1 {{ request()->routeIs('admin.norm.materials*') ? 'active' : '' }}">
                        <i class="bi bi-box-seam"></i>
                        Materials
                    </a>
                </div>

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
            @if(in_array($role, ['admin', 'ppic', 'warehouse']))
            <a href="{{ route('admin.stock-records.index') }}" class="d-flex align-items-center gap-2 mt-1 {{ request()->routeIs('admin.stock-records.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i> Stock Records
            </a>
            @endif
            </div>
        </nav>

        <!-- MAIN -->
        <div class="flex-grow-1 app-main">

            <!-- TOPBAR -->
            <div class="topbar d-flex justify-content-between align-items-center px-4">
                <div class="topbar-heading d-flex align-items-center gap-2">
                    <button class="btn btn-dark d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
                    <h6 class="mb-0 fw-bold text-dark">@yield('title', 'Dashboard')</h6>
                </div>

                <div class="topbar-account d-flex align-items-center gap-3">
                    <span class="account-name">{{ auth()->user()->name }}</span>
                    <span class="badge bg-dark text-uppercase">{{ $role }}</span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm btn-dark">Logout</button>
                    </form>
                </div>
            </div>

            <!-- CONTENT -->
            <main class="p-4 app-content" id="mainContent">
                @php
                    $moduleBack = null;

                    if (request()->routeIs('admin.ocs.*') && !request()->routeIs('admin.ocs.index')) {
                        $moduleBack = ['route' => 'admin.ocs.index', 'label' => 'Order Cut Sheet'];
                    } elseif (request()->routeIs('admin.bom.*') && !request()->routeIs('admin.bom.index')) {
                        $moduleBack = ['route' => 'admin.bom.index', 'label' => 'BOM & Tech Pack'];
                    } elseif (request()->routeIs('admin.masterplan.*') && !request()->routeIs('admin.masterplan.index')) {
                        $moduleBack = ['route' => 'admin.masterplan.index', 'label' => 'Master Plan'];
                    } elseif (request()->routeIs('masterplan.fabric.*')) {
                        $moduleBack = ['route' => 'masterplan.view', 'label' => 'Master Plan'];
                    } elseif (request()->routeIs('admin.mrp.*') && !request()->routeIs('admin.mrp.index')) {
                        $moduleBack = ['route' => 'admin.mrp.index', 'label' => 'MRP'];
                    } elseif (request()->routeIs('admin.procurement.*') && !request()->routeIs('admin.procurement.index')) {
                        $moduleBack = ['route' => 'admin.procurement.index', 'label' => 'Procurement'];
                    } elseif (request()->routeIs('admin.inventory.*') && !request()->routeIs('admin.inventory.index')) {
                        $moduleBack = ['route' => 'admin.inventory.index', 'label' => 'Inventory'];
                    } elseif (request()->routeIs('admin.shopfloor.*') && !request()->routeIs('admin.shopfloor.dashboard')) {
                        $moduleBack = ['route' => 'admin.shopfloor.dashboard', 'label' => 'Shop Floor'];
                    } elseif (request()->routeIs('admin.finance.*') && !request()->routeIs('admin.finance.dashboard')) {
                        $moduleBack = ['route' => 'admin.finance.dashboard', 'label' => 'Finance & Costing'];
                    } elseif (request()->routeIs('admin.revenue.*') && !request()->routeIs('admin.revenue.index')) {
                        $moduleBack = ['route' => 'admin.revenue.index', 'label' => 'Revenue'];
                    } elseif (request()->routeIs('revenue.*') && !request()->routeIs('revenue.view')) {
                        $moduleBack = ['route' => 'revenue.view', 'label' => 'Revenue'];
                    } elseif (request()->routeIs('admin.colors.*') && !request()->routeIs('admin.colors.index')) {
                        $moduleBack = ['route' => 'admin.colors.index', 'label' => 'Line Colors'];
                    } elseif (request()->routeIs('admin.holidays.*')) {
                        $moduleBack = ['route' => 'admin.masterplan.index', 'label' => 'Master Plan'];
                    }
                @endphp

                @if($moduleBack)
                    <div class="module-back-bar">
                        <a href="{{ route($moduleBack['route']) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>
                            Back to {{ $moduleBack['label'] }}
                        </a>
                    </div>
                @endif
                @yield('content')
            </main>

        </div>
    </div>

    @stack('scripts')
</body>

</html>
