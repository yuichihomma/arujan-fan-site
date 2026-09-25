{{-- resources/views/stats/other-games/index.blade.php --}}

@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-50 px-6 py-10">
    <div class="mx-auto max-w-5xl rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">

        {{-- タイトル --}}
        <div class="mb-10 text-center">
            <h1 class="text-2xl font-bold text-slate-800">
                他ゲーム参加記録
            </h1>
            <p class="mt-2 text-sm text-slate-500">
                Among Us以外で遊ばれたゲームの参加記録をまとめています
            </p>
        </div>

        {{-- 上段カード --}}
        <div class="grid gap-6 md:grid-cols-2">

            {{-- 一番遊んだゲーム --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-bold text-slate-800">
                    一番遊んだゲーム
                </h2>

                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <div class="grid grid-cols-3 border-b border-slate-200 bg-slate-50">
                        <div class="p-3 font-bold">順位</div>
                        <div class="p-3 font-bold">ゲーム名</div>
                        <div class="p-3 font-bold text-right">回数</div>
                    </div>

                   @foreach($popularGames as $index => $game)
    <div class="grid grid-cols-3 border-b border-slate-200">
        <div class="p-3">
            {{ $index + 1 }}位
        </div>

        <div class="p-3 font-semibold">
            {{ $game['game'] }}
        </div>

        <div class="p-3 text-right">
            {{ $game['count'] }}回
        </div>
    </div>
@endforeach
                </div>
            </section>

            {{-- 最近遊んだゲーム --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-bold text-slate-800">
                    最近遊んだゲーム
                </h2>

                @foreach($recentGames as $recentGame)
    <a href="{{ route('calendar.show', $recentGame['date']) }}"
       class="block rounded-xl border border-slate-200 p-4 transition hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-sm">
        <div class="flex items-center justify-between">
            <span class="font-bold text-slate-800">
                {{ $recentGame['game'] }}
            </span>
            <span class="text-sm text-slate-500">
                {{ \Carbon\Carbon::parse($recentGame['date'])->format('Y/m/d') }}
            </span>
        </div>
    </a>
@endforeach
            </section>
        </div>

        {{-- ゲーム一覧 --}}
        <section class="mt-10">
            <h2 class="mb-4 text-xl font-bold text-slate-800">
                今まで遊んだゲーム一覧
            </h2>

            <div class="mt-4 flex flex-wrap gap-3">
                @foreach($gameArchives as $game)
                    <a href="{{ route('stats.other-games.show', ['game' => $game]) }}"
                        class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-500 hover:bg-slate-50 hover:shadow-md">
                        {{ $game }}
                    </a>
                @endforeach
            </div>
        </section>

        {{-- 戻る --}}
        <div class="mt-10 flex justify-end">
            <a href="{{ route('home') }}"
               class="rounded-full border border-slate-300 px-8 py-3 font-bold text-slate-700 transition hover:bg-slate-100">
                TOPへ戻る
            </a>
        </div>
    </div>
</div>
@endsection