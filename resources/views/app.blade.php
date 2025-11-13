<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <base href="{{ \Illuminate\Support\Facades\Request::getBasePath() }}">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- SEO Meta Tags --}}
        @php
            $seoSettings = settings();
        @endphp
        <!-- Debug: {{ json_encode($seoSettings) }} -->
        @if(!empty($seoSettings['metaKeywords']))
            <meta name="keywords" content="{{ $seoSettings['metaKeywords'] }}">
        @endif
        @if(!empty($seoSettings['metaDescription']))
            <meta name="description" content="{{ $seoSettings['metaDescription'] }}">
        @endif
        @if(!empty($seoSettings['metaImage']))
            <meta property="og:image" content="{{ str_starts_with($seoSettings['metaImage'], 'http') ? $seoSettings['metaImage'] : url($seoSettings['metaImage']) }}">
        @endif
        <meta property="og:title" content="{{ config('app.name', 'Laravel') }}">
        <meta property="og:type" content="website">
        <meta name="twitter:card" content="summary_large_image">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <style type="text/css">html{background-color:oklch(1 0 0)}html.dark{background-color:oklch(.145 0 0)}</style>
        <script src="{{ asset('js/jquery.min.js') }}"></script>
        @routes
        @if (app()->environment('local') && file_exists(public_path('hot')))
            @viteReactRefresh
        @endif
        <script type="text/javascript">(function(){const appearance='{{ $appearance ?? "system" }}';if(appearance==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.classList.add('dark');})()</script>
        <script type="text/javascript">window.baseUrl = '{{ url('/') }}';</script>
        @vite(['resources/js/app.tsx'])
        @inertiaHead
        @PwaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        @RegisterServiceWorkerScript
    </body>
</html>