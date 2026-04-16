@extends('layouts.guest')

@section('title', 'Hyr ne Flare')

@section('content')
<div class="card">
    {{-- Flare branding --}}
    <div style="text-align:center; margin-bottom:24px;">
        <div style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,#fb923c,#e11d48,#9333ea); display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
            <svg style="width:28px; height:28px; color:#fff;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 0012 18z" />
            </svg>
        </div>
        <h2 style="margin:0; font-size:22px; font-weight:800; color:#0f172a; letter-spacing:-0.5px;">Flare</h2>
        <p class="subtitle" style="margin:4px 0 0; font-size:12px; color:#94a3b8;">Fashion Launch Analytics & Reach Engine</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   placeholder="email@zeroabsolute.al"
                   style="{{ $errors->has('email') ? 'border-color:#ef4444' : '' }}">
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password">Fjalekalimi</label>
            <input id="password" type="password" name="password" required placeholder="••••••••">
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <label class="remember">
            <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
            Me mbaj mend
        </label>

        <button type="submit" class="btn">Hyr ne Flare</button>

        @error('credentials')
            <p class="error" style="margin-top: 1rem; text-align: center;">{{ $message }}</p>
        @enderror
    </form>

    <p style="text-align:center; margin-top:20px; font-size:11px; color:#cbd5e1;">by Zero Absolute</p>
</div>
@endsection
