<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - @yield('title', 'Dashboard')</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; min-height: 100vh; }
        .topbar { background: #fff; border-bottom: 1px solid #e5e7eb; padding: .75rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .topbar h1 { font-size: 1.125rem; color: #111827; }
        .topbar .user-info { display: flex; align-items: center; gap: .75rem; font-size: .875rem; color: #374151; }
        .avatar { width: 32px; height: 32px; border-radius: 50%; background: #6366f1; color: #fff; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 600; }
        .logout-btn { background: none; border: 1px solid #d1d5db; border-radius: 6px; padding: .375rem .75rem; font-size: .8125rem; color: #374151; cursor: pointer; }
        .logout-btn:hover { background: #f9fafb; }
        .content { padding: 2rem; max-width: 1200px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>ZA Marketing</h1>
        <div class="user-info">
            <div class="avatar">{{ auth()->user()->initials }}</div>
            <span>{{ auth()->user()->full_name }}</span>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="logout-btn">Dil</button>
            </form>
        </div>
    </div>

    <div class="content">
        @yield('content')
    </div>
</body>
</html>
