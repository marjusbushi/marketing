@extends('_layouts.app')

@section('styles')
    @viteReactRefresh
@endsection

@section('content')
<div
    id="studio-app"
    data-props="{{ json_encode($props, JSON_THROW_ON_ERROR) }}"
    class="min-h-[calc(100vh-3rem)] w-full"
></div>
@endsection

@section('scripts')
    @vite(['resources/js/studio/main.tsx'])
@endsection
