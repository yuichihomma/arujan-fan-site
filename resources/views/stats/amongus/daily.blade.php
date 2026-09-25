@extends('layouts.app')

@section('content')
<article class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
    <header class="mb-8 text-center">
        <h2 class="text-2xl font-semibold text-gray-800">among us 日別成績表</h2>
    </header>

    <form method="GET" action="{{ route('stats.amongus.daily') }}" class="mb-10">
        <fieldset class="flex flex-col gap-4 lg:flex-row lg:items-end">
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-gray-700">日付で閲覧</span>
                <input
                    type="date"
                    name="search_date"
                    value="{{ old('search_date', $searchDate ?? request('search_date')) }}"
                    class="rounded-lg border border-gray-300 px-4 py-2"
                >
            </label>

            <button
                type="submit"
                class="rounded-lg bg-slate-800 px-5 py-2 text-white hover:bg-slate-700"
            >
                検索
            </button>
        </fieldset>
    </form>

    @if ($matchDetails->isEmpty())
        <p class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-6 text-center text-gray-500">
            この日付の登録済み戦績はありません。
        </p>
    @else
        <section class="space-y-6">
            <h3 class="text-center text-lg font-bold">
                {{ $periodLabel }} の戦績
            </h3>

            <div class="overflow-x-auto">
                <table class="mx-auto min-w-[700px] border border-gray-300 text-center text-sm text-gray-700">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 px-4 py-3 font-semibold">順位</th>
                            <th class="border border-gray-300 px-4 py-3 font-semibold">ユーザー名</th>
                            <th class="border border-gray-300 px-4 py-3 font-semibold">勝利数</th>
                            <th class="border border-gray-300 px-4 py-3 font-semibold">敗北数</th>
                            <th class="border border-gray-300 px-4 py-3 font-semibold">勝率</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stats as $stat)
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-300 px-4 py-3">{{ $stat['rank'] }}位</td>
                                <td class="border border-gray-300 px-4 py-3">{{ $stat['name'] }}</td>
                                <td class="border border-gray-300 px-4 py-3">{{ $stat['win'] }}</td>
                                <td class="border border-gray-300 px-4 py-3">{{ $stat['lose'] }}</td>
                                <td class="border border-gray-300 px-4 py-3">{{ number_format($stat['rate'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="border border-gray-300 px-4 py-6 text-center text-gray-500">
                                    該当する戦績データがありません
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-10 space-y-4">
            <h3 class="text-center text-xl font-bold">試合ごとの戦績</h3>

            <nav class="flex items-center justify-center gap-4" aria-label="試合ページ切り替え">
                <button type="button" id="prev-match" class="rounded-lg border px-4 py-2">前へ</button>
                <span id="match-page-label" class="font-bold"></span>
                <button type="button" id="next-match" class="rounded-lg border px-4 py-2">次へ</button>
            </nav>

            @foreach ($matchDetails as $index => $match)
                <section class="match-page {{ $index !== 0 ? 'hidden' : '' }}" data-match-page="{{ $index }}">
                    <h4 class="mb-3 text-center text-lg font-bold">
                        第{{ $match->match_number }}試合
                    </h4>

                    <div class="overflow-x-auto">
                        <table class="mx-auto min-w-[700px] border border-gray-300 text-center text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border border-gray-300 px-4 py-3">メンバー</th>
                                    <th class="border border-gray-300 px-4 py-3">役職</th>
                                    <th class="border border-gray-300 px-4 py-3">結果</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($match->memberResults as $result)
                                    <tr>
                                        <td class="border border-gray-300 px-4 py-3">
                                            {{ $result->member->name ?? '-' }}
                                        </td>
                                        <td class="border border-gray-300 px-4 py-3">
                                            {{ $result->role->name ?? '-' }}
                                        </td>
                                        <td class="border border-gray-300 px-4 py-3">
                                            {{ $result->result ?? '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </section>
    @endif

    <footer class="mt-10 flex justify-end">
        <a
            href="{{ route('home') }}"
            class="flex h-24 w-24 items-center justify-center rounded-full border-2 border-gray-400 text-sm font-medium text-gray-700 transition hover:bg-gray-100"
        >
            TOPへ戻る
        </a>
    </footer>
</article>
@endsection

<script>
document.addEventListener('DOMContentLoaded', () => {
    const pages = document.querySelectorAll('.match-page');
    const prevBtn = document.getElementById('prev-match');
    const nextBtn = document.getElementById('next-match');
    const label = document.getElementById('match-page-label');

    let current = 0;

    function showPage(index) {
        pages.forEach((page, i) => {
            page.classList.toggle('hidden', i !== index);
        });

        if (label) {
            label.textContent = `${index + 1} / ${pages.length}`;
        }

        if (prevBtn) {
            prevBtn.disabled = index === 0;
        }

        if (nextBtn) {
            nextBtn.disabled = index === pages.length - 1;
        }
    }

    if (pages.length === 0) {
        return;
    }

    showPage(current);

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (current > 0) {
                current--;
                showPage(current);
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (current < pages.length - 1) {
                current++;
                showPage(current);
            }
        });
    }
});
</script>
