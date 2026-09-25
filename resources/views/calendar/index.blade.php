@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold text-gray-800">
                配信アーカイブカレンダー
            </h1>

            <div class="text-lg font-semibold text-gray-600">
                {{ $currentMonth->format('Y年n月') }}
            </div>
        </div>

        {{-- 月送り --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('calendar.index', ['month' => $currentMonth->copy()->subMonth()->format('Y-m')]) }}"
               class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50">
                ← 前の月
            </a>

            <a href="{{ route('calendar.index', ['month' => now()->format('Y-m')]) }}"
               class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50">
                今月
            </a>

            <a href="{{ route('calendar.index', ['month' => $currentMonth->copy()->addMonth()->format('Y-m')]) }}"
               class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50">
                次の月 →
            </a>
        </div>

        {{-- 曜日 --}}
        <div class="grid grid-cols-7 gap-4 text-center text-sm font-bold text-gray-500">
            <div class="text-red-500">日</div>
            <div>月</div>
            <div>火</div>
            <div>水</div>
            <div>木</div>
            <div>金</div>
            <div class="text-blue-500">土</div>
        </div>

        {{-- カレンダー本体 --}}
        <div class="grid grid-cols-7 gap-4">
            @foreach($calendarDays as $day)
                @if(is_null($day))
                    <div></div>
                @else
                    <a href="{{ route('calendar.show', ['date' => $day['date']]) }}"
                       class="block min-h-[140px] rounded-xl border p-3 transition hover:shadow-sm
                            {{ $day['isToday'] ? 'border-violet-400 bg-violet-50' : 'border-gray-200 bg-white' }}">

                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-lg font-bold text-gray-800">
                                {{ $day['day'] }}
                            </span>

                            @if($day['streams']->isNotEmpty())
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-600">
                                    {{ $day['streams']->count() }}件
                                </span>
                            @endif
                        </div>

                        <div class="space-y-1">
                            @foreach($day['streams']->take(3) as $stream)
                                @php
                                    $preSection = $stream->sections->firstWhere('section_type', 'pre');
                                    $primarySection = $stream->sections->firstWhere('section_type', 'primary');
                                    $secondarySection = $stream->sections->firstWhere('section_type', 'secondary');
                                    $thirdSection = $stream->sections->firstWhere('section_type', 'third');
                                    $fourthSection = $stream->sections->firstWhere('section_type', 'fourth');
                                    $specialSection = $stream->sections->firstWhere('section_type', 'special');
                                @endphp

                                @if(!empty($stream->official_edited_title) || !empty($stream->official_edited_video_url))
                                    <div class="truncate rounded bg-green-50 px-2 py-1 text-xs text-green-700" title="公式編集: {{ !empty($stream->official_edited_video_url) ? '動画あり' : 'なし' }}">
                                        公式編集: {{ !empty($stream->official_edited_video_url) ? '動画あり' : 'なし' }}
                                    </div>
                                @endif

                                @php
                                    $memberArchiveCount = $stream->sections
                                        ->flatMap(fn ($section) => $section->members)
                                        ->filter(fn ($member) => !empty($member->pivot->video_url))
                                        ->count();
                                @endphp

                                @if($memberArchiveCount > 0)
                                    <div class="truncate rounded bg-blue-50 px-2 py-1 text-xs text-blue-700" title="メンバー動画: {{ $memberArchiveCount }}件">
                                        メンバー動画: {{ $memberArchiveCount }}件
                                    </div>
                                @endif

                                @if($preSection)
                                    <div class="truncate rounded bg-gray-100 px-2 py-1 text-xs text-gray-700" title="0次会: {{ $preSection->game_genre ?: '未設定' }}">
                                        0次会: {{ $preSection->game_genre ?: '未設定' }}
                                    </div>
                                @endif

                                @if($primarySection)
                                    <div class="truncate rounded bg-gray-100 px-2 py-1 text-xs text-gray-700" title="1次会: {{ $primarySection->game_genre ?: '未設定' }}">
                                        1次会: {{ $primarySection->game_genre ?: '未設定' }}
                                    </div>
                                @endif

                                @if($secondarySection)
                                    <div class="truncate rounded bg-gray-100 px-2 py-1 text-xs text-gray-700" title="2次会: {{ $secondarySection->game_genre ?: '未設定' }}">
                                        2次会: {{ $secondarySection->game_genre ?: '未設定' }}
                                    </div>
                                @endif

                                @if($thirdSection)
                                    <div class="truncate rounded bg-gray-100 px-2 py-1 text-xs text-gray-700" title="3次会: {{ $thirdSection->game_genre ?: '未設定' }}">
                                        3次会: {{ $thirdSection->game_genre ?: '未設定' }}
                                    </div>
                                @endif

                                @if($fourthSection)
                                    <div class="truncate rounded bg-gray-100 px-2 py-1 text-xs text-gray-700" title="4次会: {{ $fourthSection->game_genre ?: '未設定' }}">
                                        4次会: {{ $fourthSection->game_genre ?: '未設定' }}
                                    </div>
                                @endif

                                @if($specialSection)
                                    <div class="truncate rounded bg-gray-100 px-2 py-1 text-xs text-gray-700" title="特別回: {{ $specialSection->game_genre ?: '未設定' }}">
                                        特別回: {{ $specialSection->game_genre ?: '未設定' }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </a>
                @endif
            @endforeach
        </div>

        {{-- 選択日の一覧 --}}
        @if(!empty($selectedDate))
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-xl font-bold text-gray-800">
                    {{ \Carbon\Carbon::parse($selectedDate)->format('Y年n月j日') }} の配信一覧
                </h2>

                @php
                    $selectedDay = collect($calendarDays)->first(function ($day) use ($selectedDate) {
                        return $day && $day['date'] === $selectedDate;
                    });

                    $selectedStreams = $selectedDay['streams'] ?? collect();
                @endphp

                @if($selectedStreams->isEmpty())
                    <p class="text-gray-500">この日の配信は見つかりませんでした。</p>
                @else
                    <div class="space-y-3">
                        @foreach($selectedStreams as $stream)
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">

                                <p class="font-semibold text-gray-800">
                                    {{ $stream->title ?? 'タイトル未設定' }}
                                </p>

                                @if(!empty($stream->event_date))
                                    <p class="mt-1 text-sm text-gray-600">
                                        日付: {{ \Carbon\Carbon::parse($stream->event_date)->format('Y年n月j日') }}
                                    </p>
                                @endif

                                @if($stream->sections->isNotEmpty())
                                    <div class="mt-3 space-y-2">
                                        @foreach($stream->sections as $section)
                                            <div class="rounded-lg border border-gray-200 bg-white p-3">
                                                <p class="font-medium text-gray-700">
                                                    {{ $section->section_name ?? 'セクション名未設定' }}
                                                </p>

                                                @if($section->members->isNotEmpty())
                                                    <div class="mt-2 flex flex-wrap gap-2">
                                                        @foreach($section->members as $member)
                                                            <span class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700">
                                                                {{ $member->name }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
