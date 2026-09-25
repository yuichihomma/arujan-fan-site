<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Admin</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[#f5f5f5] text-gray-800">

<div class="min-h-screen bg-[radial-gradient(circle,#d9d9d9_1px,transparent_1px)] [background-size:24px_24px]">

    <main class="mx-auto max-w-[1280px] px-6 py-6">
        <div class="grid gap-6 xl:grid-cols-[260px_1fr]">

            {{-- 🔥 サイドバー --}}
            <aside class="rounded-3xl border border-gray-300 bg-white/70 p-6 shadow-sm backdrop-blur-sm">
                <h2 class="mb-6 text-2xl font-bold">メニュー</h2>

                <div class="space-y-6">

                    <div class="space-y-3">
                        <p class="px-1 text-xs font-bold uppercase tracking-wide text-gray-400">アーカイブ</p>

                        <a href="{{ route('admin.archives.index') }}"
                           class="block w-full rounded-2xl border border-gray-300 bg-gray-50 px-4 py-4 font-medium text-gray-700 hover:bg-gray-100">
                            カレンダーアーカイブ編集
                        </a>

                        <a href="{{ route('admin.archive-import.index') }}"
                           class="block w-full rounded-2xl border border-gray-300 bg-gray-50 px-4 py-4 font-medium text-gray-700 hover:bg-gray-100">
                            配信アーカイブ取得
                        </a>

                        <a href="{{ route('admin.members.index') }}"
                           class="block w-full rounded-2xl border border-gray-300 bg-gray-50 px-4 py-4 font-medium text-gray-700 hover:bg-gray-100">
                            メンバー追加・編集
                        </a>

                        <a href="#"
                           class="block w-full rounded-2xl border border-gray-300 bg-gray-50 px-4 py-4 font-medium text-gray-700 hover:bg-gray-100">
                            コメントの返信・削除
                        </a>
                    </div>

                    <div class="space-y-3">
                        <p class="px-1 text-xs font-bold uppercase tracking-wide text-gray-400">Among Us戦績</p>

                        <a href="{{ route('admin.stats.amongus.calendar') }}"
                           class="block w-full rounded-2xl border border-gray-300 bg-gray-50 px-4 py-4 font-medium text-gray-700 hover:bg-gray-100">
                            記録の追加・更新
                        </a>

                        <a href="{{ route('admin.stats.amongus.analysis-drafts.index') }}"
                           class="block w-full rounded-2xl border border-gray-300 bg-gray-50 px-4 py-4 font-medium text-gray-700 hover:bg-gray-100">
                            解析結果確認画面
                        </a>
                    </div>

                    <div class="space-y-3">
                        <p class="px-1 text-xs font-bold uppercase tracking-wide text-gray-400">特別回</p>

                        <a href="{{ route('admin.archives.special.index') }}"
                           class="block w-full rounded-2xl border border-gray-300 bg-gray-50 px-4 py-4 font-medium text-gray-700 hover:bg-gray-100">
                            特別回の管理
                        </a>
                    </div>

                    <a href="{{ route('admin.home') }}"
                       class="block w-full rounded-2xl border border-green-400 bg-green-500 px-4 py-4 font-medium text-white hover:bg-green-600">
                        管理画面ホーム
                    </a>

                </div>
            </aside>

            {{-- 🔥 右側の中身 --}}
            <section>
                @yield('content')
            </section>

        </div>
    </main>

</div>

</body>
</html>
