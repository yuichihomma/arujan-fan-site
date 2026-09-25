@extends('layouts.admin')

@section('content')
    <div class="w-full max-w-7xl rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
        <div class="mb-8 flex items-center justify-between">
            <h1 class="text-3xl font-bold text-gray-800">配信アーカイブ取得結果の確認</h1>

            <div class="flex gap-3">
                <a href="{{ route('admin.archive-import.index') }}"
                   class="rounded-xl border border-gray-300 px-5 py-3 text-sm font-medium hover:bg-gray-50">
                    取得画面へ戻る
                </a>
                <a href="{{ route('admin.home') }}"
                   class="rounded-xl border border-gray-300 px-5 py-3 text-sm font-medium hover:bg-gray-50">
                    管理画面ホームへ
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700">
                <span>{{ session('success') }}</span>

                @if (session('committed_date'))
                    <a href="{{ route('admin.archives.edit', ['date' => session('committed_date')]) }}"
                       class="rounded-lg border border-green-400 bg-white px-4 py-2 text-sm font-bold text-green-700 hover:bg-green-100">
                        {{ \Carbon\Carbon::parse(session('committed_date'))->format('Y年n月j日') }}の管理画面で確認する
                    </a>
                @endif
            </div>
        @endif

        @php $fetchResult = session('fetch_result'); @endphp

        @if ($fetchResult)
            <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                <p class="font-bold">{{ $fetchResult['message'] ?? '' }}</p>
                <p>取得件数: {{ $fetchResult['fetched'] ?? $fetchResult['staged'] ?? 0 }} / スプシ保存件数: {{ $fetchResult['staged'] ?? 0 }}</p>

                @if (!empty($fetchResult['errors']))
                    <ul class="mt-2 list-disc pl-5 text-red-700">
                        @foreach ($fetchResult['errors'] as $error)
                            <li>{{ $error['member'] }} ({{ $error['platform'] }}): {{ $error['message'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500">
                スプシに未処理の候補行がありません。「取得画面」からデータを取得してください。
                （Google Sheets未設定の場合は、スプシへの保存自体がスキップされます）
            </p>
        @else
            <form id="bulk-commit-form" action="{{ route('admin.archive-import.bulk-commit') }}" method="POST"
                  class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4">
                @csrf
                <span class="text-sm font-medium text-gray-700">一括操作:</span>
                <button type="button" id="bulk-select-all" class="rounded-lg border border-gray-300 bg-white px-3 py-1 text-sm hover:bg-gray-100">
                    全選択
                </button>
                <button type="button" id="bulk-deselect-all" class="rounded-lg border border-gray-300 bg-white px-3 py-1 text-sm hover:bg-gray-100">
                    全解除
                </button>
                <select name="section_id" id="bulk-section-select" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">保存先セクションを選択</option>
                    @foreach ($bulkSections->sortByDesc(fn ($section) => $section->onedayarchive->event_date) as $section)
                        <option value="{{ $section->id }}" @selected(($fetchResult['section_id'] ?? null) == $section->id)>
                            {{ \Carbon\Carbon::parse($section->onedayarchive->event_date)->toDateString() }}
                            {{ $sectionTypeLabels[$section->section_type] ?? $section->section_title }}
                        </option>
                    @endforeach
                </select>
                <button type="button" id="bulk-commit-btn" class="rounded-lg bg-green-500 px-4 py-2 text-sm font-bold text-white hover:bg-green-600">
                    選択した行を一括保存
                </button>
                <span id="bulk-selected-count" class="text-sm text-gray-500">0件選択中</span>
                <div id="bulk-hidden-inputs"></div>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-300 bg-gray-50 text-left">
                            <th class="p-3">選択</th>
                            <th class="p-3">プラットフォーム</th>
                            <th class="p-3">メンバー</th>
                            <th class="p-3">日付</th>
                            <th class="p-3">タイトル / URL</th>
                            <th class="p-3">長さ</th>
                            <th class="p-3">保存 / 削除</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $candidateSections = $sections->whereIn('id', $row['candidate_section_ids']);
                            @endphp
                            <tr class="border-b border-gray-200 align-top">
                                <td class="p-3">
                                    <input type="checkbox" class="bulk-row-checkbox" value="{{ $row['sheet_row'] }}">
                                </td>
                                <td class="p-3">{{ $row['platform'] }}</td>
                                <td class="p-3">{{ $row['member_name'] }}</td>
                                <td class="p-3">{{ $row['date'] }}</td>
                                <td class="p-3 max-w-xs">
                                    <a href="{{ $row['url'] }}" target="_blank" rel="noopener" class="block truncate text-green-700 underline" title="{{ $row['title'] }}">
                                        {{ $row['title'] ?: $row['url'] }}
                                    </a>
                                </td>
                                <td class="p-3">
                                    {{ $row['duration_seconds'] ? gmdate('H:i:s', (int) $row['duration_seconds']) : '-' }}
                                </td>
                                <td class="p-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($candidateSections->isNotEmpty())
                                            <form action="{{ route('admin.archive-import.commit') }}" method="POST" class="flex items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="sheet_row" value="{{ $row['sheet_row'] }}">
                                                <input type="hidden" name="member_id" value="{{ $row['member_id'] }}">
                                                <select name="section_id" class="rounded-lg border border-gray-300 px-2 py-1">
                                                    @foreach ($candidateSections as $section)
                                                        <option value="{{ $section->id }}">
                                                            {{ $sectionTypeLabels[$section->section_type] ?? $section->section_title }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="rounded-lg bg-green-500 px-3 py-1 font-bold text-white hover:bg-green-600">
                                                    保存
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-red-600">該当日のセクションなし</span>
                                        @endif

                                        <form action="{{ route('admin.archive-import.discard') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="sheet_row" value="{{ $row['sheet_row'] }}">
                                            <button type="submit" class="rounded-lg border border-gray-300 px-3 py-1 text-gray-700 hover:bg-gray-50">
                                                削除
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = () => document.querySelectorAll('.bulk-row-checkbox');
    const countEl = document.getElementById('bulk-selected-count');

    if (!countEl) {
        return;
    }

    function updateCount() {
        const checked = document.querySelectorAll('.bulk-row-checkbox:checked').length;
        countEl.textContent = checked + '件選択中';
    }

    checkboxes().forEach(cb => cb.addEventListener('change', updateCount));

    document.getElementById('bulk-select-all').addEventListener('click', function () {
        checkboxes().forEach(cb => { cb.checked = true; });
        updateCount();
    });

    document.getElementById('bulk-deselect-all').addEventListener('click', function () {
        checkboxes().forEach(cb => { cb.checked = false; });
        updateCount();
    });

    document.getElementById('bulk-commit-btn').addEventListener('click', function () {
        const sectionSelect = document.getElementById('bulk-section-select');
        const checked = Array.from(document.querySelectorAll('.bulk-row-checkbox:checked'));

        if (!sectionSelect.value) {
            alert('保存先セクションを選択してください');
            return;
        }

        if (checked.length === 0) {
            alert('保存する行を選択してください');
            return;
        }

        const hiddenInputsBox = document.getElementById('bulk-hidden-inputs');
        hiddenInputsBox.innerHTML = checked.map(cb => `
            <input type="hidden" name="sheet_rows[]" value="${cb.value}">
        `).join('');

        document.getElementById('bulk-commit-form').submit();
    });
});
</script>
