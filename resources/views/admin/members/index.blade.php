{{-- resources/views/admin/members/index.blade.php --}}

@extends('layouts.admin')

@section('content')
<div class="min-h-screen bg-slate-50 px-6 py-10">
    <div class="mx-auto max-w-5xl rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">

        <div class="mb-10 text-center">
            <h1 class="text-3xl font-bold text-slate-800">参加メンバー管理</h1>
            <p class="mt-3 text-sm text-slate-500">
                このページ内でメンバー情報の検索・編集・新規登録を行います
            </p>
        </div>

        @if ($errors->any())
            <div class="mb-8 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <p class="font-bold">入力内容を確認してください</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('success'))
            <div class="mb-8 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="mb-8 rounded-2xl border border-yellow-200 bg-yellow-50 p-4 text-sm font-bold text-yellow-800">
                {{ session('warning') }}
            </div>
        @endif

        {{-- 検索 --}}
        <section class="mb-10">
            <h2 class="mb-4 text-xl font-bold text-slate-800">メンバー検索</h2>

            <form method="GET" action="{{ route('admin.members.index') }}" class="flex gap-3">
                <input
                    type="text"
                    name="keyword"
                    value="{{ $keyword ?? '' }}"
                    placeholder="メンバー名で検索"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3"
                >

                <button
                    type="submit"
                    class="rounded-xl bg-slate-900 px-6 py-3 font-bold text-white transition hover:bg-slate-700"
                >
                    検索
                </button>
            </form>
        </section>

        {{-- メンバー編集 --}}
        @if($editingMember)
            <section class="mb-12">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 class="text-xl font-bold text-slate-800">メンバー編集</h2>

                    <a
                        href="{{ route('admin.members.index', array_filter(['keyword' => $keyword])) }}"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
                    >
                        編集をやめる
                    </a>
                </div>

                <form method="POST" action="{{ route('admin.members.update', $editingMember) }}">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-4 rounded-2xl border border-blue-200 bg-blue-50/40 p-5 md:grid-cols-2">
                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            メンバー名
                            <input
                                type="text"
                                name="name"
                                value="{{ old('name', $editingMember->name) }}"
                                class="rounded-xl border border-slate-300 px-4 py-3 font-normal"
                                required
                            >
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            アイコン画像URL
                            <input
                                type="text"
                                name="avatar"
                                value="{{ old('avatar', $editingMember->avatar) }}"
                                class="rounded-xl border border-slate-300 px-4 py-3 font-normal"
                            >
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            YouTube URL
                            <input
                                type="text"
                                name="youtube_url"
                                value="{{ old('youtube_url', $editingMember->youtube_url) }}"
                                class="rounded-xl border border-slate-300 px-4 py-3 font-normal"
                            >
                            <span class="text-xs font-normal text-slate-500">
                                保存時にチャンネルIDとアイコンを自動更新します
                            </span>
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            YouTube チャンネルID
                            <input
                                type="text"
                                name="youtube_channel_id"
                                value="{{ old('youtube_channel_id', $editingMember->youtube_channel_id) }}"
                                placeholder="UC..."
                                class="rounded-xl border border-slate-300 px-4 py-3 font-normal"
                            >
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            アルジャン配信チャンネルURL
                            <input
                                type="text"
                                name="arujan_youtube_url"
                                value="{{ old('arujan_youtube_url', $editingMember->arujan_youtube_url) }}"
                                placeholder="https://www.youtube.com/@..."
                                class="rounded-xl border border-slate-300 px-4 py-3 font-normal"
                            >
                            <span class="text-xs font-normal text-slate-500">
                                メインと別チャンネルでアルジャンを配信する場合に登録。アーカイブ抽出時にこのチャンネルも自動で検索します
                            </span>
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            アルジャン配信チャンネルID
                            <input
                                type="text"
                                name="arujan_youtube_channel_id"
                                value="{{ old('arujan_youtube_channel_id', $editingMember->arujan_youtube_channel_id) }}"
                                placeholder="UC...（URLから自動取得されます）"
                                class="rounded-xl border border-slate-300 px-4 py-3 font-normal"
                            >
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            X URL
                            <input
                                type="text"
                                name="x_url"
                                value="{{ old('x_url', $editingMember->x_url) }}"
                                class="rounded-xl border border-slate-300 px-4 py-3 font-normal"
                            >
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            Twitch URL
                            <input
                                type="text"
                                name="twitch_url"
                                value="{{ old('twitch_url', $editingMember->twitch_url) }}"
                                class="rounded-xl border border-slate-300 px-4 py-3 font-normal"
                            >
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            その他配信枠
                            <select
                                name="other_platform"
                                class="rounded-xl border border-slate-300 px-4 py-3 font-normal"
                            >
                                @php
                                    $selectedOtherPlatform = old('other_platform', $editingMember->other_platform);
                                    $otherPlatforms = ['ツイキャス', 'Twitchサブ', 'YouTubeサブ', 'ニコ生', 'OPENREC', 'その他'];
                                @endphp
                                <option value="">未設定</option>
                                @foreach($otherPlatforms as $platform)
                                    <option value="{{ $platform }}" @selected($selectedOtherPlatform === $platform)>
                                        {{ $platform }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            その他URL
                            <input
                                type="text"
                                name="other_url"
                                value="{{ old('other_url', $editingMember->other_url) }}"
                                placeholder="https://twitcasting.tv/..."
                                class="rounded-xl border border-slate-300 px-4 py-3 font-normal"
                            >
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-700 md:col-span-2">
                            キャラ説明
                            <textarea
                                name="description"
                                rows="4"
                                class="rounded-xl border border-slate-300 px-4 py-3 font-normal"
                            >{{ old('description', $editingMember->description) }}</textarea>
                        </label>
                    </div>

                    <div class="mt-5 flex justify-center">
                        <button
                            type="submit"
                            class="w-full max-w-md rounded-xl bg-green-600 px-16 py-4 text-lg font-bold text-white shadow-sm transition hover:bg-green-700"
                        >
                            更新
                        </button>
                    </div>
                </form>
            </section>
        @endif

        {{-- 登録済みメンバー一覧 --}}
        <section class="mb-12">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">登録済みメンバー</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        YouTube API同期はチャンネルID登録済みのメンバーが対象です
                    </p>
                </div>

                <div class="flex gap-2 text-xs font-bold">
                    <span class="rounded-full bg-red-50 px-3 py-1 text-red-700">
                        YouTube URL: {{ $members->filter(fn ($member) => !empty($member->youtube_url))->count() }}件
                    </span>
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-blue-700">
                        チャンネルID: {{ $members->filter(fn ($member) => !empty($member->youtube_channel_id))->count() }}件
                    </span>
                </div>
            </div>

            <div class="space-y-3">
                @forelse($members as $member)
                    <div class="flex items-center justify-between rounded-2xl border {{ optional($editingMember)->id === $member->id ? 'border-blue-300 bg-blue-50' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
                        <div class="flex items-center gap-4">
                            @if($member->avatar)
                                <img
                                    src="{{ $member->avatar }}"
                                    alt="{{ $member->name }}"
                                    class="h-12 max-h-12 min-h-12 w-12 min-w-12 max-w-12 shrink-0 rounded-full object-cover"
                                >
                            @else
                                <div class="flex h-12 max-h-12 min-h-12 w-12 min-w-12 max-w-12 shrink-0 items-center justify-center rounded-full bg-slate-200 font-bold text-slate-700">
                                    {{ mb_substr($member->name, 0, 1) }}
                                </div>
                            @endif

                            <div class="min-w-0">
                                <p class="font-bold text-slate-800">
                                    {{ $member->name }}
                                </p>

                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                    @if($member->youtube_url)
                                        <a
                                            href="{{ $member->youtube_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="rounded-full bg-red-50 px-3 py-1 font-bold text-red-700 underline"
                                        >
                                            YouTube URL登録済み
                                        </a>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-3 py-1 font-bold text-slate-500">
                                            YouTube URL未登録
                                        </span>
                                    @endif

                                    @if($member->youtube_channel_id)
                                        <span class="rounded-full bg-blue-50 px-3 py-1 font-bold text-blue-700">
                                            {{ $member->youtube_channel_id }}
                                        </span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-3 py-1 font-bold text-slate-500">
                                            チャンネルID未登録
                                        </span>
                                    @endif

                                    @if($member->arujan_youtube_channel_id)
                                        <a
                                            href="{{ $member->arujan_youtube_url ?: 'https://www.youtube.com/channel/' . $member->arujan_youtube_channel_id }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="rounded-full bg-purple-50 px-3 py-1 font-bold text-purple-700 underline"
                                        >
                                            アルジャン配信チャンネル
                                        </a>
                                    @endif

                                    @if($member->other_url)
                                        <a
                                            href="{{ $member->other_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="rounded-full bg-emerald-50 px-3 py-1 font-bold text-emerald-700 underline"
                                        >
                                            {{ $member->other_platform ?: 'その他' }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <a
                            href="{{ route('admin.members.index', array_filter(['keyword' => $keyword, 'edit' => $member->id])) }}"
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
                        >
                            編集
                        </a>
                    </div>
                @empty
                    <p class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500">
                        該当するメンバーがいません
                    </p>
                @endforelse
            </div>
        </section>

        {{-- 新規メンバー追加 --}}
        <section>
            <h2 class="mb-5 text-xl font-bold text-slate-800">新規メンバー追加</h2>

            <form method="POST" action="{{ route('admin.members.store') }}">
                @csrf

                <div class="grid gap-4 rounded-2xl border border-slate-200 p-5 md:grid-cols-2">
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="メンバー名"
                        class="rounded-xl border border-slate-300 px-4 py-3"
                        required
                    >

                    <input
                        type="text"
                        name="avatar"
                        value="{{ old('avatar') }}"
                        placeholder="アイコン画像URL"
                        class="rounded-xl border border-slate-300 px-4 py-3"
                    >

                    <input
                        type="text"
                        name="youtube_url"
                        value="{{ old('youtube_url') }}"
                        placeholder="YouTube URL"
                        class="rounded-xl border border-slate-300 px-4 py-3"
                    >

                    <input
                        type="text"
                        name="youtube_channel_id"
                        value="{{ old('youtube_channel_id') }}"
                        placeholder="YouTube チャンネルID（UC...）"
                        class="rounded-xl border border-slate-300 px-4 py-3"
                    >

                    <input
                        type="text"
                        name="arujan_youtube_url"
                        value="{{ old('arujan_youtube_url') }}"
                        placeholder="アルジャン配信チャンネルURL（別チャンネルで配信する場合）"
                        class="rounded-xl border border-slate-300 px-4 py-3"
                    >

                    <input
                        type="text"
                        name="arujan_youtube_channel_id"
                        value="{{ old('arujan_youtube_channel_id') }}"
                        placeholder="アルジャン配信チャンネルID（URLから自動取得）"
                        class="rounded-xl border border-slate-300 px-4 py-3"
                    >

                    <input
                        type="text"
                        name="x_url"
                        value="{{ old('x_url') }}"
                        placeholder="X URL"
                        class="rounded-xl border border-slate-300 px-4 py-3"
                    >

                    <input
                        type="text"
                        name="twitch_url"
                        value="{{ old('twitch_url') }}"
                        placeholder="Twitch URL"
                        class="rounded-xl border border-slate-300 px-4 py-3"
                    >

                    <select
                        name="other_platform"
                        class="rounded-xl border border-slate-300 px-4 py-3"
                    >
                        @php
                            $selectedOtherPlatform = old('other_platform');
                            $otherPlatforms = ['ツイキャス', 'Twitchサブ', 'YouTubeサブ', 'ニコ生', 'OPENREC', 'その他'];
                        @endphp
                        <option value="">その他配信枠（未設定）</option>
                        @foreach($otherPlatforms as $platform)
                            <option value="{{ $platform }}" @selected($selectedOtherPlatform === $platform)>
                                {{ $platform }}
                            </option>
                        @endforeach
                    </select>

                    <input
                        type="text"
                        name="other_url"
                        value="{{ old('other_url') }}"
                        placeholder="その他URL"
                        class="rounded-xl border border-slate-300 px-4 py-3"
                    >

                    <textarea
                        name="description"
                        placeholder="キャラ説明"
                        rows="3"
                        class="rounded-xl border border-slate-300 px-4 py-3 md:col-span-2"
                    >{{ old('description') }}</textarea>
                </div>

                <div class="mt-5 flex justify-center">
                    <button
                        type="submit"
                        class="w-full max-w-md rounded-xl bg-green-600 px-16 py-4 text-lg font-bold text-white shadow-sm transition hover:bg-green-700"
                    >
                        登録
                    </button>
                </div>
            </form>
        </section>

        <div class="mt-10 flex justify-end">
            <a
                href="{{ route('admin.home') }}"
                class="rounded-full border border-slate-300 px-8 py-3 font-bold text-slate-700 transition hover:bg-slate-100"
            >
                管理画面ホームへ戻る
            </a>
        </div>

    </div>
</div>
@endsection
