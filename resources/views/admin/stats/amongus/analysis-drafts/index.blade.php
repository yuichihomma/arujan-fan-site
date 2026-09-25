@extends('layouts.admin')

@section('content')
<div class="rounded-[28px] border border-gray-200 bg-white/90 p-8 shadow-sm">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Among Us 解析結果確認画面</h1>
            <p class="mt-2 text-sm text-gray-500">一次会 Among Us の動画解析候補を確認して、本登録します。</p>
        </div>

        <a href="{{ route('admin.stats.amongus.calendar') }}"
           class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-100">
            戦績カレンダー
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if(session('warnings'))
        <div class="mb-4 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-700">
            @foreach(session('warnings') as $warning)
                <div>{{ $warning }}</div>
            @endforeach
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.stats.amongus.analysis-drafts.index') }}"
           class="rounded-xl px-4 py-2 text-sm font-bold {{ empty($status) ? 'bg-slate-900 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-100' }}">
            全て
        </a>
        @foreach($statusLabels as $key => $label)
            <a href="{{ route('admin.stats.amongus.analysis-drafts.index', ['status' => $key]) }}"
               class="rounded-xl px-4 py-2 text-sm font-bold {{ $status === $key ? 'bg-slate-900 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-100' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[1300px] border-collapse text-left text-sm">
            <thead>
                <tr class="bg-gray-100 text-xs font-bold text-gray-600">
                    <th class="border border-gray-200 px-3 py-3">選択</th>
                    <th class="border border-gray-200 px-3 py-3">日付</th>
                    <th class="border border-gray-200 px-3 py-3">動画</th>
                    <th class="border border-gray-200 px-3 py-3">時刻</th>
                    <th class="border border-gray-200 px-3 py-3">戦績候補</th>
                    <th class="border border-gray-200 px-3 py-3">根拠</th>
                    <th class="border border-gray-200 px-3 py-3">状態</th>
                    <th class="border border-gray-200 px-3 py-3">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse($drafts as $draft)
                    @php
                        $date = $draft->archiveSection?->onedayarchive?->event_date;
                        $statusLabel = $statusLabels[$draft->status] ?? $draft->status;
                    @endphp
                    <tr class="align-top">
                        <td class="border border-gray-200 px-3 py-3">
                            <input
                                type="checkbox"
                                name="draft_ids[]"
                                value="{{ $draft->id }}"
                                form="bulk-apply-form"
                                class="h-4 w-4 rounded border-gray-300"
                                {{ $draft->status !== 'approved' ? 'disabled' : '' }}
                            >
                        </td>
                        <td class="border border-gray-200 px-3 py-3 font-bold">
                            {{ $date ? \Carbon\Carbon::parse($date)->format('Y/m/d') : '-' }}
                            <div class="mt-1 text-xs font-normal text-gray-500">
                                {{ $draft->archiveSection?->section_title ?: '1次会' }}
                            </div>
                        </td>
                        <td class="border border-gray-200 px-3 py-3">
                            <div class="font-bold">{{ $draft->member?->name ?: '配信者不明' }}</div>
                            <a href="{{ $draft->timestampUrl() }}" target="_blank" rel="noopener noreferrer"
                               class="mt-1 block max-w-[260px] truncate text-blue-600 underline">
                                {{ $draft->video_url }}
                            </a>
                        </td>
                        <td class="border border-gray-200 px-3 py-3">
                            <div>動画: {{ $draft->video_timestamp_label ?: '-' }}</div>
                            <div class="mt-1">実時間: {{ $draft->estimated_real_time?->format('Y/m/d H:i') ?: '-' }}</div>
                        </td>
                        <td class="border border-gray-200 px-3 py-3">
                            <form id="draft-update-{{ $draft->id }}" method="POST" action="{{ route('admin.stats.amongus.analysis-drafts.update', $draft) }}" class="space-y-2">
                                @csrf
                                @method('PUT')
                                <input name="match_number" value="{{ old('match_number', $draft->match_number) }}" placeholder="試合番号" class="w-24 rounded-lg border border-gray-300 px-2 py-1">
                                <input name="member_name" value="{{ old('member_name', $draft->member_name) }}" placeholder="参加者名" class="w-full rounded-lg border border-gray-300 px-2 py-1">
                                <input name="role_name" value="{{ old('role_name', $draft->role_name) }}" placeholder="役職" class="w-full rounded-lg border border-gray-300 px-2 py-1">
                                <div class="flex gap-2">
                                    <select name="result" class="w-full rounded-lg border border-gray-300 px-2 py-1">
                                        <option value="">勝敗</option>
                                        <option value="win" {{ $draft->result === 'win' ? 'selected' : '' }}>win</option>
                                        <option value="lose" {{ $draft->result === 'lose' ? 'selected' : '' }}>lose</option>
                                    </select>
                                    <input name="win_side" value="{{ old('win_side', $draft->win_side) }}" placeholder="勝利陣営" class="w-full rounded-lg border border-gray-300 px-2 py-1">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input name="video_timestamp_seconds" value="{{ old('video_timestamp_seconds', $draft->video_timestamp_seconds) }}" placeholder="秒" class="rounded-lg border border-gray-300 px-2 py-1">
                                    <input name="video_timestamp_label" value="{{ old('video_timestamp_label', $draft->video_timestamp_label) }}" placeholder="00:00:00" class="rounded-lg border border-gray-300 px-2 py-1">
                                </div>
                                <input name="estimated_real_time" value="{{ old('estimated_real_time', $draft->estimated_real_time?->format('Y-m-d H:i:s')) }}" placeholder="YYYY-MM-DD HH:MM:SS" class="w-full rounded-lg border border-gray-300 px-2 py-1">
                                <input name="confidence" value="{{ old('confidence', $draft->confidence) }}" placeholder="信頼度" class="w-24 rounded-lg border border-gray-300 px-2 py-1">
                                <select name="status" class="w-full rounded-lg border border-gray-300 px-2 py-1">
                                    @foreach($statusLabels as $key => $label)
                                        <option value="{{ $key }}" {{ $draft->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="border border-gray-200 px-3 py-3">
                            <textarea form="draft-update-{{ $draft->id }}" name="evidence_text" rows="4" class="w-full rounded-lg border border-gray-300 px-2 py-1">{{ old('evidence_text', $draft->evidence_text) }}</textarea>
                            <textarea form="draft-update-{{ $draft->id }}" name="memo" rows="2" placeholder="メモ" class="mt-2 w-full rounded-lg border border-gray-300 px-2 py-1">{{ old('memo', $draft->memo) }}</textarea>
                        </td>
                        <td class="border border-gray-200 px-3 py-3">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold
                                @if($draft->status === 'approved') bg-green-100 text-green-700
                                @elseif($draft->status === 'rejected') bg-red-100 text-red-700
                                @else bg-orange-100 text-orange-700
                                @endif">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="border border-gray-200 px-3 py-3">
                            <div class="flex flex-col gap-2">
                                <button form="draft-update-{{ $draft->id }}" type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-700">
                                    更新
                                </button>
                                <form method="POST" action="{{ route('admin.stats.amongus.analysis-drafts.approve', $draft) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-lg bg-green-600 px-3 py-2 text-xs font-bold text-white hover:bg-green-700">承認</button>
                                </form>
                                <form method="POST" action="{{ route('admin.stats.amongus.analysis-drafts.reject', $draft) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700">不採用</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="border border-gray-200 px-4 py-10 text-center text-gray-500">
                            下書きはありません。
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form id="bulk-apply-form" method="POST" action="{{ route('admin.stats.amongus.analysis-drafts.apply') }}" class="mt-4 flex flex-wrap items-center gap-3">
        @csrf
        <button type="submit" class="rounded-xl bg-green-600 px-5 py-2 text-sm font-bold text-white hover:bg-green-700">
            チェックした承認済みを本登録
        </button>
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="overwrite" value="1" class="h-4 w-4 rounded border-gray-300">
            既存の試合結果を上書き
        </label>
    </form>

    <div class="mt-6">
        {{ $drafts->links() }}
    </div>
</div>
@endsection
