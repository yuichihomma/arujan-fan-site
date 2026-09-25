@extends('layouts.app')

@section('content')
<article class="min-w-0 max-w-full">
    <header class="mb-8 text-center">
        <h1 class="text-4xl font-bold tracking-tight">among us 通算成績表</h1>
    </header>

    <form
        id="period-form"
        method="GET"
        action="{{ route('stats.amongus.total') }}"
        class="mb-10 rounded-[28px] border border-gray-200 bg-gray-50 p-6"
    >
        <fieldset class="grid gap-6 lg:grid-cols-[1.2fr_1fr_auto] lg:items-end">
            <section>
                <p class="mb-3 text-sm font-bold text-gray-500">集計期間</p>

                <input
                    type="hidden"
                    name="period_type"
                    id="period-type"
                    value="{{ request('period_type', 'all') }}"
                >

                <nav class="flex flex-wrap gap-3" aria-label="集計期間">
                    <button
                        type="button"
                        class="period-tab rounded-full border px-5 py-2 text-sm font-bold"
                        data-period="all"
                    >
                        全通算
                    </button>

                    <button
                        type="button"
                        class="period-tab rounded-full border px-5 py-2 text-sm font-bold"
                        data-period="year"
                    >
                        年別
                    </button>

                    <button
                        type="button"
                        class="period-tab rounded-full border px-5 py-2 text-sm font-bold"
                        data-period="month"
                    >
                        年月別
                    </button>
                </nav>

                <label id="year-select-area" class="mt-4 hidden block">
                    <span class="sr-only">年を選択</span>
                    <select
                        name="year"
                        id="year-select"
                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm"
                    >
                        @foreach ($availableYears as $year)
                            <option value="{{ $year }}" @selected(request('year') == $year)>
                                {{ $year }}年
                            </option>
                        @endforeach
                    </select>
                </label>

                <label id="month-select-area" class="mt-4 hidden block">
                    <span class="sr-only">年月を選択</span>
                    <select
                        name="month"
                        id="month-select"
                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm"
                    >
                        @foreach ($availableMonths as $month)
                            <option value="{{ $month }}" @selected(request('month') === $month)>
                                {{ \Carbon\Carbon::parse($month . '-01')->format('Y年n月') }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </section>

            <section>
                <p class="mb-3 text-sm font-bold text-gray-500">並び替え</p>

                <fieldset class="grid grid-cols-[1fr_110px] gap-3">
                    <label>
                        <span class="sr-only">並び替え項目</span>
                        <select
                            name="sort_key"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-700"
                        >
                            <option value="total_boardings" {{ $sortKey === 'total_boardings' ? 'selected' : '' }}>通算乗船数</option>
                            <option value="total_matches" {{ $sortKey === 'total_matches' ? 'selected' : '' }}>通算対戦数</option>
                            <option value="total_wins" {{ $sortKey === 'total_wins' ? 'selected' : '' }}>通算勝利数</option>
                            <option value="total_losses" {{ $sortKey === 'total_losses' ? 'selected' : '' }}>通算敗北数</option>
                            <option value="total_win_rate" {{ $sortKey === 'total_win_rate' ? 'selected' : '' }}>通算勝率</option>
                            <option value="crew_wins" {{ $sortKey === 'crew_wins' ? 'selected' : '' }}>クルー勝利数</option>
                            <option value="crew_losses" {{ $sortKey === 'crew_losses' ? 'selected' : '' }}>クルー敗北数</option>
                            <option value="crew_win_rate" {{ $sortKey === 'crew_win_rate' ? 'selected' : '' }}>クルー勝率</option>
                            <option value="impostor_wins" {{ $sortKey === 'impostor_wins' ? 'selected' : '' }}>インポスター勝利数</option>
                            <option value="impostor_losses" {{ $sortKey === 'impostor_losses' ? 'selected' : '' }}>インポスター敗北数</option>
                            <option value="impostor_win_rate" {{ $sortKey === 'impostor_win_rate' ? 'selected' : '' }}>インポスター勝率</option>
                            <option value="third_wins" {{ $sortKey === 'third_wins' ? 'selected' : '' }}>第三陣営勝利数</option>
                            <option value="third_losses" {{ $sortKey === 'third_losses' ? 'selected' : '' }}>第三陣営敗北数</option>
                            <option value="third_win_rate" {{ $sortKey === 'third_win_rate' ? 'selected' : '' }}>第三陣営勝率</option>
                        </select>
                    </label>

                    <label>
                        <span class="sr-only">並び替え順</span>
                        <select
                            name="sort_order"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-700"
                        >
                            <option value="desc" {{ $sortOrder === 'desc' ? 'selected' : '' }}>降順</option>
                            <option value="asc" {{ $sortOrder === 'asc' ? 'selected' : '' }}>昇順</option>
                        </select>
                    </label>
                </fieldset>
            </section>

            <button
                type="submit"
                class="h-[48px] rounded-xl bg-gray-900 px-8 text-sm font-bold text-white hover:bg-gray-700"
            >
                検索
            </button>
        </fieldset>
    </form>

    <section class="max-w-full overflow-x-auto">
        <table class="min-w-[1500px] table-fixed text-center text-sm text-gray-700">
            <thead class="bg-gray-100">
                <tr>
                    <th class="w-20 border border-gray-300 px-2 py-4 font-semibold whitespace-nowrap">順位</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">メンバー</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">通算乗船数</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">通算対戦数</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">通算勝利数</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">通算敗北数</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">通算勝率</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">クルー勝利数</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">クルー敗北数</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">クルー勝率</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">インポスター勝利数</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">インポスター敗北数</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">インポスター勝率</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">第三陣営勝利数</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">第三陣営敗北数</th>
                    <th class="border border-gray-300 px-3 py-4 font-semibold">第三陣営勝率</th>
                </tr>
            </thead>

            <tbody>
                @forelse($stats as $index => $stat)
                    <tr class="hover:bg-gray-50">
                        <td class="w-20 border border-gray-300 px-2 py-4 whitespace-nowrap">
                            {{ $index + 1 }}位
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['member_name'] }}
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['total_boardings'] }}
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['total_matches'] }}
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['total_wins'] }}
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['total_losses'] }}
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['total_win_rate'] }}%
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['crew_wins'] }}
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['crew_losses'] }}
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['crew_win_rate'] }}%
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['impostor_wins'] }}
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['impostor_losses'] }}
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['impostor_win_rate'] }}%
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['third_wins'] }}
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['third_losses'] }}
                        </td>
                        <td class="border border-gray-300 px-3 py-4">
                            {{ $stat['third_win_rate'] }}%
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16" class="border border-gray-300 px-3 py-8 text-center text-gray-500">
                            通算成績データがありません
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <footer class="mt-16 flex justify-end">
        <a
            href="{{ route('home') }}"
            class="inline-flex h-28 w-28 items-center justify-center rounded-full border-2 border-gray-400 text-base font-medium text-gray-700 hover:bg-gray-100"
        >
            TOPへ戻る
        </a>
    </footer>
</article>
@endsection

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('period-form');
    const periodButtons = document.querySelectorAll('.period-tab');
    const yearSelectArea = document.getElementById('year-select-area');
    const monthSelectArea = document.getElementById('month-select-area');
    const periodTypeInput = document.getElementById('period-type');
    const yearSelect = document.getElementById('year-select');
    const monthSelect = document.getElementById('month-select');

    if (!form || !periodTypeInput || !yearSelectArea || !monthSelectArea) {
        return;
    }

    function activatePeriod(period) {
        periodTypeInput.value = period;

        periodButtons.forEach(button => {
            button.classList.remove('bg-gray-800', 'text-white');
            button.classList.add('bg-white', 'text-gray-700');

            if (button.dataset.period === period) {
                button.classList.remove('bg-white', 'text-gray-700');
                button.classList.add('bg-gray-800', 'text-white');
            }
        });

        yearSelectArea.classList.add('hidden');
        monthSelectArea.classList.add('hidden');

        if (period === 'year') {
            yearSelectArea.classList.remove('hidden');
        }

        if (period === 'month') {
            monthSelectArea.classList.remove('hidden');
        }
    }

    activatePeriod(periodTypeInput.value || 'all');

    periodButtons.forEach(button => {
        button.addEventListener('click', () => {
            activatePeriod(button.dataset.period);
        });
    });

    if (yearSelect) {
        yearSelect.addEventListener('change', () => {
            periodTypeInput.value = 'year';
            form.submit();
        });
    }

    if (monthSelect) {
        monthSelect.addEventListener('change', () => {
            periodTypeInput.value = 'month';
            form.submit();
        });
    }
});
</script>
