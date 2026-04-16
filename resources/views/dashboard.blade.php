@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div style="background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.06); padding:2rem;">
    <h2 style="font-size:1.25rem; color:#111827; margin-bottom:.5rem;">
        Mire se erdhet, {{ auth()->user()->first_name }}!
    </h2>
    <p style="color:#6b7280; font-size:.875rem; margin-bottom:1.5rem;">
        Kjo eshte faqja kryesore e Marketing. Modulet e Content Planner dhe Influencer do shtohen se shpejti.
    </p>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
        <div style="background:#f0fdf4; border-radius:8px; padding:1.25rem;">
            <p style="font-size:.75rem; text-transform:uppercase; color:#16a34a; font-weight:600; margin-bottom:.25rem;">Statusi</p>
            <p style="font-size:1.5rem; font-weight:700; color:#15803d;">Aktiv</p>
        </div>
        <div style="background:#eff6ff; border-radius:8px; padding:1.25rem;">
            <p style="font-size:.75rem; text-transform:uppercase; color:#2563eb; font-weight:600; margin-bottom:.25rem;">Roli</p>
            <p style="font-size:1rem; font-weight:600; color:#1d4ed8;">Marketing User</p>
        </div>
    </div>
</div>
@endsection
