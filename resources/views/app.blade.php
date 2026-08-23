<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="shopify-api-key" content="{{ config('shopify-app.api_key') }}" />
        <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>

        <title>{{ config('app.name', 'Buy Now Later') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts and Styles -->
        @php
            $hotFile = public_path('hot');
            if (file_exists($hotFile) && (app()->environment('production') || !app()->environment('local'))) {
                @unlink($hotFile);
            }
        @endphp
        @env('local')
            @viteReactRefresh
            @vite(['resources/js/app.jsx'])
        @else
            @php
                $manifestPath = public_path('build/manifest.json');
                $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];
                $appJs = $manifest['resources/js/app.jsx']['file'] ?? null;
                $appCss = $manifest['resources/js/app.jsx']['css'][0] ?? null;
            @endphp
            @if($appCss)
                <link rel="stylesheet" href="{{ asset('build/' . $appCss) }}">
            @endif
            @if($appJs)
                <script type="module" src="{{ asset('build/' . $appJs) }}"></script>
            @else
                @vite(['resources/js/app.jsx'])
            @endif
        @endenv
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
