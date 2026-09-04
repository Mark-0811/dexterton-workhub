<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Dexterton WorkHub') }}</title>
    <link rel="shortcut icon" href="/vendor/mazer/assets/static/images/logo/favicon.svg" type="image/x-icon">
    <link rel="shortcut icon" href="/vendor/mazer/assets/static/images/logo/favicon.png" type="image/png">
    <script>
        document.documentElement.dataset.bsTheme = localStorage.getItem('theme') || localStorage.getItem('workhub-theme') || 'light'
    </script>
    @vite(['resources/css/app.scss', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
