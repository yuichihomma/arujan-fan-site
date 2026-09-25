{{-- resources/views/stats/other-games/show.blade.php --}}

@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-50 px-6 py-10">
    <div class="mx-auto max-w-5xl rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">

        {{-- タイトル --}}
        <div class="mb-10 text-center">
            <p class="mb-2 text-sm font-semibold text-slate-500">
                他ゲーム参加記録（詳細）
            </p>

            <h1 class="text-3xl font-bold text-slate-800">
                {{ $game }}
            </h1>

            <p class="mt-3 text-sm text-slate-500">
                このゲームが遊ばれた配信アーカイブ一覧です
            </p>
        </div>

        {{-- アーカイブ一覧 --}}
        <section class="mx-auto max-w-3xl">
            <h2 class="mb-5 text-xl font-bold text-slate-800">
                最新アーカイブ
            </h2>

            <div class="space-y-4">
                @foreach($archives as $archive)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                            <div>
                                <p class="text-sm font-semibold text-slate-500">
                                    日付
                                </p>

                                <p class="mt-1 text-lg font-bold text-slate-800">
                                    {{ \Carbon\Carbon::parse($archive->onedayarchive->event_date)->format('Y/m/d') }}
                                </p>
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-slate-500">
                                    配信区分
                                </p>

                                <p class="mt-1 font-bold text-slate-800">
                                    {{ match ($archive->section_type) {
                                        'pre' => '0次会',
                                        'primary' => '1次会',
                                        'secondary' => '2次会',
                                        'third' => '3次会',
                                        'fourth' => '4次会',
                                        'special' => '特別回',
                                        default => '区分未設定',
                                    } }}
                                </p>
                            </div>

                            <div class="flex gap-3">
                                @if($archive->video_url)
                                    <a href="{{ $archive->video_url }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="rounded-full border border-slate-300 px-5 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100">
                                        動画を見る
                                    </a>
                                @endif

                                <a href="{{ route('calendar.show', $archive->onedayarchive->event_date) }}"
                                   class="rounded-full bg-slate-900 px-5 py-2 text-sm font-bold text-white transition hover:bg-slate-700">
                                    アーカイブへ
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- 戻る --}}
        <div class="mt-10 flex justify-end">
            <a href="{{ route('stats.other-games.index') }}"
               class="rounded-full border border-slate-300 px-8 py-3 font-bold text-slate-700 transition hover:bg-slate-100">
                戻る
            </a>
        </div>
    </div>
</div>
@endsection
