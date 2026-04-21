@if(($embedded ?? false))
    <!DOCTYPE html>
    <html lang="sq">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $pageTitle ?? 'Visual Studio' }}</title>
        @viteReactRefresh
        @vite(['resources/js/studio/main.tsx'])
        <style>
            html, body { margin: 0; padding: 0; height: 100%; background: #09090b; }
            #studio-app { height: 100%; width: 100%; }
        </style>
    </head>
    <body>
        <div
            id="studio-app"
            data-props="{{ json_encode($props, JSON_THROW_ON_ERROR) }}"
        ></div>
    </body>
    </html>
@else
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
@endif
