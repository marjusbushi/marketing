<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1f2937">
    <link rel="manifest" href="{{ asset('production-manifest.json') }}">
    <title>{{ $title ?? 'Prodhimi' }} — Zero Absolute</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, system-ui, sans-serif; background: #fafafa; color: #111827; min-height: 100vh; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; }
    </style>
    @stack('head')
</head>
<body>
    @yield('content')
</body>
</html>
