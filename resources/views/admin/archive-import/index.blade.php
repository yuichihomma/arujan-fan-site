@extends('layouts.admin')

@section('content')
    <div class="w-full max-w-4xl rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
        <div class="mb-8 flex items-center justify-between">
            <h1 class="text-3xl font-bold text-gray-800">配信アーカイブ取得</h1>

            <a href="{{ route('admin.home') }}"
               class="rounded-xl border border-gray-300 px-5 py-3 text-sm font-medium hover:bg-gray-50">
                管理画面ホームへ戻る
            </a>
        </div>

        <p class="mb-6 text-sm text-gray-600">
            指定した参加者だけに絞ってYouTube・Twitch・ツイキャスのアーカイブを取得し、スプレッドシートに一時保存します（「アルジャン」タグの付いた配信のみが対象です）。
            取得後は<a href="{{ route('admin.archive-import.review') }}" class="text-green-700 underline">確認画面</a>で内容を見てからDBに保存してください。
        </p>

        @if (session('success'))
            <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 p-6">
            <h2 class="mb-1 text-lg font-bold text-gray-800">① 登録済みのセクションから取得</h2>
            <p class="mb-4 text-sm text-gray-500">
                カレンダーアーカイブ編集で参加者登録済みの日はこちら。おすすめの方法です。
            </p>

            @if ($sections->isEmpty())
                <p class="text-sm text-gray-500">
                    参加者登録済みのセクションがありません。先に<a href="{{ route('admin.archives.index') }}" class="text-green-700 underline">カレンダーアーカイブ編集</a>でその日の参加者を登録してください。
                </p>
            @else
                <form action="{{ route('admin.archive-import.fetch') }}" method="POST" class="space-y-6">
                    @csrf

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">
                            対象セクション（その日の参加者だけに絞って取得します）
                        </label>
                        <select id="section-select" name="section_id" class="w-full rounded-lg border border-gray-300 px-4 py-3" required>
                            <option value="">選択してください</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}" data-date="{{ \Carbon\Carbon::parse($section->onedayarchive->event_date)->toDateString() }}">
                                    {{ \Carbon\Carbon::parse($section->onedayarchive->event_date)->toDateString() }}
                                    {{ $sectionTypeLabels[$section->section_type] ?? $section->section_title }}
                                    （{{ $section->members->count() }}人）
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">
                            ゲームで絞り込み（任意。タイトルから判定します）
                        </label>
                        <select name="game" class="w-full rounded-lg border border-gray-300 px-4 py-3">
                            <option value="">絞り込まない</option>
                            @foreach ($games as $game)
                                <option value="{{ $game }}">{{ $game }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="text-center">
                        <button
                            type="submit"
                            class="rounded-xl bg-green-500 px-8 py-4 text-lg font-bold text-white hover:bg-green-600">
                            取得してスプシに保存
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <div class="my-6 text-center text-sm font-bold text-gray-400">または</div>

        <div class="rounded-2xl border border-gray-200 p-6">
            <h2 class="mb-1 text-lg font-bold text-gray-800">② 日付と参加者を直接指定して取得</h2>
            <p class="mb-4 text-sm text-gray-500">
                まだカレンダーにその日を登録していない場合はこちら。取得後の確認・DB保存の前に、カレンダー側の登録も忘れずに行ってください。
            </p>

            <form action="{{ route('admin.archive-import.fetch') }}" method="POST" class="space-y-6">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">開催日</label>
                    <input type="date" name="date" class="w-full rounded-lg border border-gray-300 px-4 py-3" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">参加者</label>

                    <div class="relative">
                        <input
                            type="text"
                            id="adhoc-member-search"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2"
                            placeholder="参加者名で検索"
                            autocomplete="off"
                        >

                        <div
                            id="adhoc-member-suggestions"
                            class="absolute z-10 mt-1 hidden w-full rounded-lg border border-gray-200 bg-white shadow"
                        ></div>
                    </div>

                    <div id="adhoc-member-tags" class="mt-3 flex flex-wrap gap-2"></div>
                    <div id="adhoc-member-hidden-inputs"></div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">
                        ゲームで絞り込み（任意。タイトルから判定します）
                    </label>
                    <select name="game" class="w-full rounded-lg border border-gray-300 px-4 py-3">
                        <option value="">絞り込まない</option>
                        @foreach ($games as $game)
                            <option value="{{ $game }}">{{ $game }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="text-center">
                    <button
                        type="submit"
                        class="rounded-xl bg-green-500 px-8 py-4 text-lg font-bold text-white hover:bg-green-600">
                        取得してスプシに保存
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@php
    $allMembersForJs = $members->map(fn ($member) => [
        'id' => $member->id,
        'name' => $member->name,
    ])->values();
@endphp

<script type="application/json" id="adhoc-member-data">
{!! \Illuminate\Support\Js::encode($allMembersForJs) !!}
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // カレンダー編集画面の保存完了ポップアップ「取得画面へ」から ?date=YYYY-MM-DD 付きで
    // 遷移してきた場合、該当日のセクションをあらかじめ選択しておく。
    const params = new URLSearchParams(window.location.search);
    const targetDate = params.get('date');
    const sectionSelect = document.getElementById('section-select');

    if (targetDate && sectionSelect) {
        const option = Array.from(sectionSelect.options).find(o => o.dataset.date === targetDate);

        if (option) {
            sectionSelect.value = option.value;
        }
    }

    const allMembers = JSON.parse(document.getElementById('adhoc-member-data').textContent);

    const searchInput = document.getElementById('adhoc-member-search');
    const suggestionsBox = document.getElementById('adhoc-member-suggestions');
    const tagsBox = document.getElementById('adhoc-member-tags');
    const hiddenInputsBox = document.getElementById('adhoc-member-hidden-inputs');

    let selectedMembers = {};

    function renderSuggestions(keyword) {
        const q = keyword.trim().toLowerCase();

        if (!q) {
            suggestionsBox.innerHTML = '';
            suggestionsBox.classList.add('hidden');
            return;
        }

        const filteredMembers = allMembers.filter(member => {
            return member.name.toLowerCase().includes(q) && !selectedMembers[member.id];
        });

        suggestionsBox.innerHTML = filteredMembers.length
            ? filteredMembers.map(member => `
                <button
                    type="button"
                    class="member-suggestion block w-full px-3 py-2 text-left text-sm hover:bg-gray-100"
                    data-id="${member.id}"
                    data-name="${member.name}"
                >
                    ${member.name}
                </button>
            `).join('')
            : `<div class="px-3 py-2 text-sm text-gray-500">該当する参加者がいません</div>`;

        suggestionsBox.classList.remove('hidden');
    }

    function renderSelectedMembers() {
        const membersArray = Object.values(selectedMembers);

        tagsBox.innerHTML = membersArray.map(member => `
            <div class="flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-1 text-sm">
                <span>${member.name}</span>
                <button type="button" class="remove-member text-red-500" data-id="${member.id}">×</button>
            </div>
        `).join('');

        hiddenInputsBox.innerHTML = membersArray.map(member => `
            <input type="hidden" name="member_ids[]" value="${member.id}">
        `).join('');
    }

    searchInput.addEventListener('input', function () {
        renderSuggestions(this.value);
    });

    suggestionsBox.addEventListener('click', function (e) {
        const button = e.target.closest('.member-suggestion');
        if (!button) return;

        selectedMembers[button.dataset.id] = { id: button.dataset.id, name: button.dataset.name };

        searchInput.value = '';
        suggestionsBox.classList.add('hidden');

        renderSelectedMembers();
    });

    tagsBox.addEventListener('click', function (e) {
        const button = e.target.closest('.remove-member');
        if (!button) return;

        delete selectedMembers[button.dataset.id];
        renderSelectedMembers();
    });

    document.addEventListener('click', function (e) {
        if (!suggestionsBox.contains(e.target) && e.target !== searchInput) {
            suggestionsBox.classList.add('hidden');
        }
    });
});
</script>
