@extends('layouts.admin')

@section('content')
<div class="rounded-[32px] border border-gray-200 bg-white/80 p-8 shadow-sm backdrop-blur-sm">
    <div class="mb-8">
        <h1 class="text-4xl font-bold tracking-tight">among us の戦績追加</h1>
        <p class="mt-2 text-lg text-gray-500">
            日付を選択して戦績登録ページへ進みます。
        </p>
    </div>

    <div class="rounded-[28px] border border-gray-200 bg-white p-8 shadow-sm">
        <h2 class="mb-8 text-center text-2xl font-bold">among us の戦績追加</h2>

        {{-- 年月移動 --}}
        <div class="mb-8">
            <div class="flex flex-wrap items-center justify-center gap-3 text-sm font-bold">
                <a href="{{ route('admin.stats.amongus.calendar', ['month' => $prevYear]) }}"
                   class="rounded-xl border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100">
                    前年
                </a>

                <a href="{{ route('admin.stats.amongus.calendar', ['month' => $prevMonth]) }}"
                   class="rounded-xl border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100">
                    前月
                </a>

                <div class="px-4 text-center text-2xl font-bold text-gray-900">
                    {{ $currentMonth->format('Y/m') }}
                </div>

                <a href="{{ route('admin.stats.amongus.calendar', ['month' => $nextMonth]) }}"
                   class="rounded-xl border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100">
                    次月
                </a>

                <a href="{{ route('admin.stats.amongus.calendar', ['month' => $nextYear]) }}"
                   class="rounded-xl border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100">
                    翌年
                </a>
            </div>

            <form method="GET" action="{{ route('admin.stats.amongus.calendar') }}" class="mt-5 flex flex-wrap justify-center gap-3">
                <input
                    type="month"
                    name="month"
                    value="{{ $currentMonth->format('Y-m') }}"
                    class="rounded-xl border border-slate-300 px-4 py-2 font-bold"
                >

                <button
                    type="submit"
                    class="rounded-xl bg-slate-900 px-5 py-2 font-bold text-white hover:bg-slate-700"
                >
                    移動
                </button>

                <a href="{{ route('admin.stats.amongus.calendar') }}"
                   class="rounded-xl border border-slate-300 px-5 py-2 font-bold text-slate-700 hover:bg-slate-100">
                    今月
                </a>
            </form>
        </div>

        {{-- カレンダー --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] table-fixed border-collapse text-center">
                <thead>
                    <tr class="bg-gray-100 text-sm font-bold">
                        <th class="border border-gray-300 px-2 py-4 text-red-500">日</th>
                        <th class="border border-gray-300 px-2 py-4">月</th>
                        <th class="border border-gray-300 px-2 py-4">火</th>
                        <th class="border border-gray-300 px-2 py-4">水</th>
                        <th class="border border-gray-300 px-2 py-4">木</th>
                        <th class="border border-gray-300 px-2 py-4">金</th>
                        <th class="border border-gray-300 px-2 py-4 text-blue-500">土</th>
                    </tr>
                </thead>

                <tbody>
    @foreach ($calendarWeeks as $week)
        <tr>
            @foreach ($week as $day)
                @php
                    $status = $dayStatuses[$day['date']] ?? [
                        'label' => '編集できません',
                        'class' => 'disabled',
                    ];
                @endphp

                <td class="align-top border border-gray-300 px-3 py-3
                    {{ $day['isCurrentMonth'] ? '' : 'bg-gray-50 text-gray-300' }}">

                    <div class="flex min-h-[180px] flex-col gap-2">
                        <div class="flex items-center justify-between">
                            @if($day['isCurrentMonth'] && $status['class'] !== 'disabled')
                                <a href="{{ route('admin.stats.amongus.create', ['date' => $day['date']]) }}"
                                   class="text-lg font-semibold hover:text-violet-600
                                        {{ $day['isSunday'] ? 'text-red-500' : '' }}
                                        {{ $day['isSaturday'] ? 'text-blue-500' : '' }}">
                                    {{ $day['day'] }}
                                </a>
                            @else
                                <span class="text-lg font-semibold
                                    {{ $day['isSunday'] ? 'text-red-500' : '' }}
                                    {{ $day['isSaturday'] ? 'text-blue-500' : '' }}">
                                    {{ $day['day'] }}
                                </span>
                            @endif

                            @if($day['isCurrentMonth'] && $status['class'] !== 'disabled')
                                <a href="{{ route('admin.stats.amongus.create', ['date' => $day['date']]) }}"
                                   class="rounded-lg bg-gray-800 px-3 py-1 text-xs font-medium text-white hover:bg-gray-700">
                                    追加
                                </a>
                            @endif
                        </div>

                        @if($day['isCurrentMonth'])
                            <div class="flex flex-1 flex-col gap-2 text-left">
                                @if($status['class'] === 'completed')
                                    <a href="{{ route('admin.stats.amongus.create', ['date' => $day['date']]) }}"
                                    class="block rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-xs font-medium text-green-700 hover:bg-green-100">
                                        詳細確認・再編集
                                    </a>
                                @elseif($status['class'] === 'disabled')
                                    <div class="block rounded-xl border border-gray-200 bg-gray-100 px-3 py-2 text-xs text-gray-400">
                                        編集できません
                                    </div>
                                @else
                                    <a href="{{ route('admin.stats.amongus.create', ['date' => $day['date']]) }}"
                                        class="block rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600 hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700">
                                            日付をクリックすると<br>戦績追加ページへ移動
                                    </a>
                                @endif

                                <div
                                    class="rounded-xl px-3 py-2 text-xs font-medium
                                    @if($status['class'] === 'disabled')
                                        border border-gray-200 bg-gray-100 text-gray-400
                                    @elseif($status['class'] === 'empty')
                                        border border-dashed border-gray-300 bg-white text-gray-400
                                    @elseif($status['class'] === 'draft')
                                        border border-orange-200 bg-orange-50 text-orange-600
                                    @elseif($status['class'] === 'completed')
                                        border border-green-200 bg-green-50 text-green-600
                                    @endif"
                                >
                                    {{ $status['label'] }}
                                </div>
                            </div>
                        @endif
                    </div>
                </td>
            @endforeach
        </tr>
    @endforeach
</tbody>
            </table>
        </div>
        <div class="mt-10 flex justify-end">
            <a href="{{ route('admin.home') }}"
               class="inline-flex h-24 w-24 items-center justify-center rounded-full border-2 border-gray-400 text-sm font-medium text-gray-700 hover:bg-gray-100">
                戻る
            </a>
        </div>
    </div>
</div>
@endsection
