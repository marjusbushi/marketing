<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - @yield('title', 'Login')</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.5rem; width: 100%; max-width: 420px; }
        .card h2 { font-size: 1.25rem; color: #111827; margin-bottom: .25rem; }
        .card .subtitle { font-size: .875rem; color: #6b7280; margin-bottom: 1.5rem; }
        label { display: block; font-size: .875rem; font-weight: 500; color: #374151; margin-bottom: .25rem; }
        input[type=email], input[type=password] { width: 100%; padding: .625rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: .875rem; transition: border-color .15s; }
        input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
        .field { margin-bottom: 1rem; }
        .error { color: #ef4444; font-size: .75rem; margin-top: .25rem; }
        .btn { width: 100%; padding: .625rem 1rem; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: .875rem; font-weight: 500; cursor: pointer; transition: background .15s; }
        .btn:hover { background: #4f46e5; }
        .remember { display: flex; align-items: center; gap: .5rem; margin-bottom: 1.5rem; font-size: .875rem; color: #6b7280; }
        .remember input { width: 1rem; height: 1rem; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
