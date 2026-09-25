@extends('layouts.admin')

@section('content')
    <div class="w-full max-w-7xl rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
        <div class="mb-8 flex items-center justify-between">
            <h1 class="text-3xl font-bold text-gray-800">アーカイブ編集</h1>

            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button
                    type="submit"
                    class="rounded-xl bg-red-500 px-4 py-3 text-sm font-medium text-white hover:bg-red-600"
                >
                    ログアウト
                </button>
            </form>
        </div>

        <div class="mb-8 flex items-center justify-center gap-6 text-2xl font-bold text-gray-800">
            <a href="{{ route('admin.archives.index', ['month' => $currentMonth->copy()->subMonth()->format('Y-m')]) }}"
               class="hover:text-gray-500">
                前月
            </a>

            <span>{{ $currentMonth->format('Y/m') }}</span>

            <a href="{{ route('admin.archives.index', ['month' => $currentMonth->copy()->addMonth()->format('Y-m')]) }}"
               class="hover:text-gray-500">
                次月
            </a>
        </div>

        <form action="{{ route('admin.archives.index') }}" method="GET" class="mb-8 flex items-center justify-center gap-3 text-sm">
            <select name="year" class="rounded-lg border border-gray-300 px-3 py-2">
                @foreach(range($minYear, $maxYear) as $year)
                    <option value="{{ $year }}" @selected($year === (int) $currentMonth->format('Y'))>
                        {{ $year }}年
                    </option>
                @endforeach
            </select>

            <select name="monthOnly" class="rounded-lg border border-gray-300 px-3 py-2">
                @foreach(range(1, 12) as $m)
                    <option value="{{ $m }}" @selected($m === (int) $currentMonth->format('n'))>
                        {{ $m }}月
                    </option>
                @endforeach
            </select>

            <button
                type="submit"
                class="rounded-lg border border-gray-300 bg-gray-50 px-4 py-2 font-medium hover:bg-gray-100">
                この月へ移動
            </button>
        </form>

        @php
            $monthNeedsReviewTotal = 0;
            $monthUnregisteredGameTotal = 0;

            foreach ($calendarDays as $day) {
                if (!$day['archive'] || $day['archive']->no_stream) {
                    continue;
                }

                foreach ($day['archive']->sections as $section) {
                    foreach ($section->members as $member) {
                        if ($member->pivot->needs_review ?? false) {
                            $monthNeedsReviewTotal++;
                        }
                    }

                    if ($section->game_genre && !$registeredGameNames->contains($section->game_genre)) {
                        $monthUnregisteredGameTotal++;
                    }
                }
            }
        @endphp

        @if($monthNeedsReviewTotal > 0 || $monthUnregisteredGameTotal > 0)
            <div class="mb-6 flex items-center justify-center gap-3">
                @if($monthNeedsReviewTotal > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
                        ⚠ URL要確認 {{ $monthNeedsReviewTotal }}件
                    </span>
                @endif
                @if($monthUnregisteredGameTotal > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700">
                        ⚠ ゲーム未登録 {{ $monthUnregisteredGameTotal }}件
                    </span>
                @endif
            </div>
        @endif

        <div class="mb-3 grid grid-cols-7 gap-3 text-center text-sm font-bold">
            <div class="text-red-500">日</div>
            <div>月</div>
            <div>火</div>
            <div>水</div>
            <div>木</div>
            <div>金</div>
            <div class="text-blue-500">土</div>
        </div>

        <div class="grid grid-cols-7 gap-4">
            {{-- 月初までの空白 --}}
            @for ($i = 0; $i < $startWeekday; $i++)
                <div class="min-h-[170px] rounded-xl border border-transparent"></div>
            @endfor

            {{-- 日付マス --}}
            @foreach ($calendarDays as $day)
                @php
                    $isNoStream = $day['archive'] && $day['archive']->no_stream;
                    $status = $day['archive']->status ?? null;
                    $dayNeedsReviewCount = 0;
                    $dayHasUnregisteredGame = false;

                    if ($day['archive'] && !$isNoStream) {
                        foreach ($day['archive']->sections as $section) {
                            foreach ($section->members as $member) {
                                if ($member->pivot->needs_review ?? false) {
                                    $dayNeedsReviewCount++;
                                }
                            }

                            if ($section->game_genre && !$registeredGameNames->contains($section->game_genre)) {
                                $dayHasUnregisteredGame = true;
                            }
                        }
                    }

                    $cellColorClass = match (true) {
                        $isNoStream => 'border-red-200 bg-red-50',
                        $status === 'done' => 'border-green-300 bg-green-50',
                        $status === 'tentative' => 'border-amber-300 bg-amber-50',
                        default => 'border-gray-300 bg-gray-50',
                    };
                @endphp
                <div class="flex min-h-[170px] flex-col rounded-xl border p-4 transition hover:border-green-400 hover:bg-white {{ $cellColorClass }}">
                    <a href="{{ route('admin.archives.edit', ['date' => $day['date']]) }}" class="flex-1">
                        <div class="mb-2 flex items-center justify-between gap-1">
                            <span class="text-xl font-bold {{ $isNoStream ? 'text-red-400' : ($day['archive'] ? 'text-gray-800' : 'text-gray-400') }}">
                                {{ $day['day'] }}日
                            </span>
                            <span class="flex flex-wrap items-center justify-end gap-1">
                                @if(!$isNoStream && $status === 'done')
                                    <span class="inline-flex items-center whitespace-nowrap rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700">
                                        ✓ 完了
                                    </span>
                                @elseif(!$isNoStream && $status === 'tentative')
                                    <span class="inline-flex items-center whitespace-nowrap rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">
                                        仮完了
                                    </span>
                                @endif
                                @if($dayNeedsReviewCount > 0 || $dayHasUnregisteredGame)
                                    <span
                                        class="inline-flex items-center whitespace-nowrap rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-bold text-orange-700"
                                        title="{{ trim(($dayNeedsReviewCount > 0 ? "URL要確認 {$dayNeedsReviewCount}件" : '') . ($dayNeedsReviewCount > 0 && $dayHasUnregisteredGame ? ' / ' : '') . ($dayHasUnregisteredGame ? 'ゲーム未登録あり' : '')) }}"
                                    >
                                        ⚠ 要確認
                                    </span>
                                @endif
                            </span>
                        </div>

                        @if ($isNoStream)
                            <p class="text-sm font-bold text-red-400">配信なし</p>
                        @elseif ($day['archive'])
                            @php
                                $officialEditedTitle = trim((string) $day['archive']->official_edited_title);
                                $hasOfficialEditedTitle = $officialEditedTitle !== '';
                                $sectionOrder = [
                                    'pre' => '0次会',
                                    'primary' => '1次会',
                                    'secondary' => '2次会',
                                    'third' => '3次会',
                                    'fourth' => '4次会',
                                    'special' => '特別回',
                                ];
                                $sectionsByType = $day['archive']->sections->keyBy('section_type');
                            @endphp
                            <div class="space-y-2 text-xs text-gray-700">
                                <div class="min-w-0 rounded-md bg-white px-2.5 py-2 font-semibold">
                                    <div class="mb-1 text-[11px] leading-none text-gray-500">公式編集</div>
                                    <span class="min-w-0 flex-1 {{ $hasOfficialEditedTitle || !empty($day['archive']->official_edited_video_url) ? 'text-gray-800' : 'text-gray-400' }}">
                                        <span class="block truncate" title="{{ $hasOfficialEditedTitle ? $officialEditedTitle : (!empty($day['archive']->official_edited_video_url) ? '動画あり' : '未登録') }}">
                                            {{ $hasOfficialEditedTitle ? $officialEditedTitle : (!empty($day['archive']->official_edited_video_url) ? '動画あり' : '未登録') }}
                                        </span>
                                    </span>
                                </div>

                                @foreach ($sectionOrder as $sectionType => $sectionLabel)
                                    @php
                                        $section = $sectionsByType->get($sectionType);
                                        $hasContent = $section && $section->game_genre;
                                    @endphp
                                    @if($hasContent)
                                        <div class="min-w-0 rounded-md bg-white px-2.5 py-2">
                                            <div class="mb-1 text-[11px] font-semibold leading-none text-gray-500">{{ $sectionLabel }}</div>
                                            <span
                                                class="block min-w-0 truncate text-sm font-semibold leading-tight text-gray-800"
                                                title="{{ $section->game_genre }}"
                                            >
                                                {{ $section->game_genre }}
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400">未登録</p>
                        @endif
                    </a>

                    @if ($isNoStream)
                        <form action="{{ route('admin.archives.mark-no-stream', ['date' => $day['date']]) }}" method="POST" class="mt-2">
                            @csrf
                            <input type="hidden" name="no_stream" value="0">
                            <input type="hidden" name="month" value="{{ $month }}">
                            <button type="submit" class="w-full rounded-lg border border-red-300 bg-white px-2 py-1 text-xs text-red-500 hover:bg-red-50">
                                取り消す
                            </button>
                        </form>
                    @elseif (!$day['archive'])
                        <form action="{{ route('admin.archives.mark-no-stream', ['date' => $day['date']]) }}" method="POST" class="mt-2">
                            @csrf
                            <input type="hidden" name="no_stream" value="1">
                            <input type="hidden" name="month" value="{{ $month }}">
                            <button type="submit" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-600 hover:bg-gray-50">
                                配信なしにする
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-8 text-center">
            <a href="{{ route('admin.home') }}"
               class="inline-block rounded-full border border-gray-400 px-6 py-3 hover:bg-gray-50">
                TOPに戻る
            </a>
        </div>
    </div>
@endsection
