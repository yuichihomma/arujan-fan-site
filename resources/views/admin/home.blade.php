@extends('layouts.admin')

@section('content')
    <div class="w-full max-w-4xl rounded-2xl border border-gray-200 bg-white p-10 shadow-sm">
        <div class="mb-10 flex items-center justify-between">
            <h1 class="text-3xl font-bold text-gray-800">アルジャンファンサイト　管理者ホーム</h1>

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

        <div class="space-y-8">
            <div class="space-y-6">
                <p class="text-sm font-bold uppercase tracking-wide text-gray-400">アーカイブ</p>

                <a href="{{ route('admin.archives.index') }}"
                   class="flex h-24 items-center justify-center rounded-2xl border-4 border-green-600 bg-green-400 text-2xl font-bold text-gray-900 shadow-sm transition hover:scale-[1.01] hover:bg-green-500">
                    カレンダーアーカイブ編集
                </a>

                <a href="{{ route('admin.archive-import.index') }}"
                   class="flex h-24 items-center justify-center rounded-2xl border-4 border-green-600 bg-green-400 text-2xl font-bold text-gray-900 shadow-sm transition hover:scale-[1.01] hover:bg-green-500">
                    配信アーカイブ取得（スプシ経由）
                </a>

                <a href="{{ route('admin.members.index') }}"
                   class="flex h-24 items-center justify-center rounded-2xl border-4 border-green-600 bg-green-400 text-2xl font-bold text-gray-900 shadow-sm transition hover:scale-[1.01] hover:bg-green-500">
                    メンバーの追加・編集
                </a>

                <a href="#"
                   class="flex h-24 items-center justify-center rounded-2xl border-4 border-green-600 bg-green-400 text-2xl font-bold text-gray-900 shadow-sm transition hover:scale-[1.01] hover:bg-green-500">
                    コメントの返信・削除
                </a>
            </div>

            <div class="space-y-6">
                <p class="text-sm font-bold uppercase tracking-wide text-gray-400">Among Us戦績</p>

                <a href="{{ route('admin.stats.amongus.calendar') }}"
                   class="flex h-24 items-center justify-center rounded-2xl border-4 border-green-600 bg-green-400 text-2xl font-bold text-gray-900 shadow-sm transition hover:scale-[1.01] hover:bg-green-500">
                    記録の追加・更新
                </a>

                <a href="{{ route('admin.stats.amongus.analysis-drafts.index') }}"
                   class="flex h-24 items-center justify-center rounded-2xl border-4 border-green-600 bg-green-400 text-2xl font-bold text-gray-900 shadow-sm transition hover:scale-[1.01] hover:bg-green-500">
                    解析結果確認画面
                </a>
            </div>

            <div class="space-y-6">
                <p class="text-sm font-bold uppercase tracking-wide text-gray-400">特別回</p>

                <a href="{{ route('admin.archives.special.index') }}"
                   class="flex h-24 items-center justify-center rounded-2xl border-4 border-green-600 bg-green-400 text-2xl font-bold text-gray-900 shadow-sm transition hover:scale-[1.01] hover:bg-green-500">
                    特別回の管理
                </a>
            </div>
        </div>
    </div>
@endsection