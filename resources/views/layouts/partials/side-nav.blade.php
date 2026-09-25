<aside class="rounded-2xl border border-gray-200 bg-white/60 p-5 shadow-sm backdrop-blur-sm">
    <div class="mb-5">
        <h2 class="mb-3 text-lg font-bold">メニュー</h2>
        <input
            type="text"
            placeholder="検索"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-violet-500"
        >
    </div>

    <div class="space-y-3">
        <a href="{{ route('home') }}"
            class="block w-full rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-left font-medium hover:bg-gray-100">
            TOP
        </a>

        <a href="{{ route('calendar.index') }}"
            class="block w-full rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-left font-medium hover:bg-gray-100">
            アーカイブ検索
        </a>

        <a href="{{ route('stats.amongus.daily') }}"
            class="block w-full rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-left font-medium hover:bg-gray-100">
            among us の日別成績記録
        </a>

        <a href="{{ route('stats.amongus.total') }}"
            class="block w-full rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-left font-medium hover:bg-gray-100">
            among us の通算成績記録
        </a>

        <a href="{{ route('stats.other-games.index') }}"
            class="block w-full rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-left font-medium hover:bg-gray-100">
            他ゲームの参加記録
        </a>

        <a href="{{ route('members.index') }}"
            class="block w-full rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-left font-medium hover:bg-gray-100">
            参加者一覧
        </a>
    </div>
</aside>
