<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'アルジャンアーカイブ') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f5f5] text-gray-800">
    <div class="min-h-screen bg-[radial-gradient(circle,#d9d9d9_1px,transparent_1px)] [background-size:16px_16px]">
        @include('layouts.partials.site-header')

        <main class="mx-auto max-w-[1500px] px-6 py-6">
            <div class="hidden gap-6 md:grid md:grid-cols-[260px_minmax(0,1fr)]">
                @include('layouts.partials.side-nav')

                <section class="min-w-0 rounded-2xl border border-gray-200 bg-white/90 p-6 shadow-sm">
                    @yield('content')
                </section>
            </div>

            <div class="space-y-6 md:hidden">
                @include('layouts.partials.side-nav')

                <section class="min-w-0 rounded-2xl border border-gray-200 bg-white/90 p-6 shadow-sm space-y-6">
                    @yield('content')
                </section>
            </div>
        </main>

        @include('layouts.partials.site-footer')
    </div>
</body>
</html>
