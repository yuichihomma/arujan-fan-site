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
            <section class="mb-6 hidden xl:block">
                <div class="relative h-[600px] w-full overflow-hidden rounded-2xl">
                    <img
                        src="{{ asset('images/main-visual.jpeg') }}"
                        alt="メインビジュアル背景"
                        class="absolute inset-0 h-full w-full scale-110 object-cover blur-sm"
                    >

                    <img
                        src="{{ asset('images/main-visual.jpeg') }}"
                        alt="メインビジュアル"
                        class="relative z-10 h-full w-full object-contain"
                    >
                </div>
            </section>

            <div class="hidden gap-6 xl:grid xl:grid-cols-[260px_minmax(0,1fr)_260px]">
                @include('layouts.partials.side-nav')

                <section class="min-w-0 rounded-2xl border border-gray-200 bg-white/90 p-6 shadow-sm">
                    @yield('content')
                </section>

                @include('layouts.partials.fan-sidebar')
            </div>

            <div class="space-y-6 xl:hidden">
                <div class="overflow-hidden rounded-2xl border border-gray-200 shadow-sm">
                    <img
                        src="{{ asset('images/main-visual.jpeg') }}"
                        alt="メインビジュアル"
                        class="h-[260px] w-full object-cover md:h-[380px]"
                    >
                </div>

                @include('layouts.partials.side-nav')

                <section class="min-w-0 rounded-2xl border border-gray-200 bg-white/90 p-6 shadow-sm">
                    @yield('content')
                </section>

                @include('layouts.partials.fan-sidebar')
            </div>
        </main>

        @include('layouts.partials.site-footer')
    </div>
</body>
</html>
