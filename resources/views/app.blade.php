<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';
                if (appearance === 'system') {
                    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        <style>
            html { background-color: #fff; }
            html.dark { background-color: #0a0a0a; }
        </style>

        @if(! empty($platformBranding['favicon_url'] ?? null))
            <link rel="icon" href="{{ $platformBranding['favicon_url'] }}" sizes="any">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @endif
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Load built assets without relying on Vite font helpers / SSR. --}}
        @php
            $manifestPath = public_path('build/manifest.json');
            $manifest = is_file($manifestPath)
                ? json_decode((string) file_get_contents($manifestPath), true)
                : null;
            $cssFile = is_array($manifest) ? ($manifest['resources/css/app.css']['file'] ?? null) : null;
            $jsFile = is_array($manifest) ? ($manifest['resources/js/app.tsx']['file'] ?? null) : null;
        @endphp
        @if ($cssFile)
            <link rel="stylesheet" href="{{ asset('build/'.$cssFile) }}">
        @endif
        @if ($jsFile)
            <script type="module" src="{{ asset('build/'.$jsFile) }}"></script>
        @endif

        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
