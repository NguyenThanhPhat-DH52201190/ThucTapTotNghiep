<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Auth')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        :root {
            --bs-primary: #d97706;
            --bs-primary-rgb: 217, 119, 6;
            --bs-link-color: #b45309;
            --bs-link-hover-color: #92400e;
        }
    </style>
</head>

<body class="bg-warning-subtle">
    <main class="container min-vh-100 d-flex align-items-center py-4">
        <div class="row justify-content-center w-100">
            <div class="col-12 col-md-8 col-lg-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4 p-md-5">
                        <span class="badge text-bg-warning text-dark d-block w-100 text-center fs-6 mb-3">GLOBAL SAFEWEAR VIET NAM</span>
                        <h1 class="h3 mt-3 mb-1 fw-bold">@yield('heading')</h1>
                        @hasSection('subtitle')
                            <p class="text-secondary mb-3">@yield('subtitle')</p>
                        @endif

                        @if (session('status'))
                            <div class="alert alert-warning border-0">{{ session('status') }}</div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert">
                                <div class="fw-semibold mb-1">Please check your information:</div>
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>
