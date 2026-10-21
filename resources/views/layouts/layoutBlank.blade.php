<!DOCTYPE html>
<html lang="en" class="layout-wide customizer-hide" dir="ltr" data-bs-theme="light">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'Authentication') - WeSarafi</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />

    <!-- Vite Assets (CSS + JS) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Font Awesome -->

    <!-- Extra CSS -->
    @yield('vendor-style')
    @yield('page-style')

    <style>
        .bg-authentication {
            position: relative;
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url({{ asset('assets/img/background.webp') }});
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-card {
            width: 100%;
            max-width: 420px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            background-color: rgba(255, 255, 255, 0.95);
            border: none;
            animation: fadeInUp 0.5s ease;
        }

        .auth-card .card-body {
            padding: 2rem;
        }

        .app-brand {
            margin-bottom: 1.5rem;
        }

        .app-brand-logo img {
            height: 48px;
            transition: transform 0.3s ease;
        }

        .form-control {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #7367f0;
            box-shadow: 0 0 0 3px rgba(115, 103, 240, 0.1);
        }

        .btn-primary {
            background-color: #7367f0;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: #5d52d1;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(115, 103, 240, 0.2);
        }

        .input-group-text {
            background-color: transparent;
            border-color: #e0e0e0;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: #9e9e9e;
            font-size: 13px;
        }

        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #eee;
        }

        .social-login {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 1rem;
        }

        .social-login a {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #5d52d1;
            border: 1px solid #e0e0e0;
            transition: all 0.2s;
        }

        .social-login a:hover {
            background-color: #f5f3ff;
            transform: translateY(-2px);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="bg-authentication">
    @yield('content')

    <!-- Scripts -->
    @yield('vendor-script')
    @yield('page-script')
</body>
</html>
