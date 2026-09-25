@extends('layouts.admin')

@section('content')
<div class="w-full max-w-6xl rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
    <div class="mb-8 flex items-center justify-between">
        <h1 class="text-4xl font-bold text-gray-800">
            {{ \Carbon\Carbon::parse($date)->format('Y年n月j日') }} の編集
        </h1>

        <div class="flex items-center gap-3">
            <button type="button" id="compute-participants-btn"
                    data-url="{{ route('admin.archives.compute-participants', ['date' => $date]) }}"
                    data-status-url-template="{{ route('admin.archives.compute-participants.status', ['date' => $date, 'token' => '__TOKEN__']) }}"
                    class="rounded-xl border border-blue-400 bg-blue-50 px-5 py-3 text-sm font-medium text-blue-700 hover:bg-blue-100">
                ゲーム内容と参加メンバーを抽出する
            </button>

            <a href="{{ route('admin.archives.index', ['month' => \Carbon\Carbon::parse($date)->format('Y-m')]) }}"
               class="rounded-xl border border-gray-300 px-5 py-3 text-sm font-medium hover:bg-gray-50">
                一覧へ戻る
            </a>
        </div>
    </div>

    <div id="compute-participants-message" class="mb-6 hidden rounded-xl border px-4 py-3 text-sm font-bold"></div>

    @if(session('success') === '更新しました。')
        <div id="save-success-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="mb-2 text-lg font-bold text-gray-800">保存しました</h3>
                <p class="mb-6 text-sm text-gray-600">続けてこの日の配信アーカイブを取得しますか？</p>
                <div class="flex justify-end gap-3">
                    <button type="button" id="save-success-close"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">
                        閉じる
                    </button>
                    <a href="{{ route('admin.archive-import.index', ['date' => $date]) }}"
                       class="rounded-lg bg-green-500 px-4 py-2 text-sm font-bold text-white hover:bg-green-600">
                        取得画面へ
                    </a>
                </div>
            </div>
        </div>
        <script>
            document.getElementById('save-success-close')?.addEventListener('click', function () {
                document.getElementById('save-success-modal')?.remove();
            });
        </script>
    @elseif(session('success'))
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form action="{{ route('admin.archives.update', ['date' => $date]) }}" method="POST" class="space-y-8">
        @csrf

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6">
            <h2 class="mb-4 text-xl font-bold text-gray-800">公式編集動画</h2>

            <input type="hidden" name="official_title" value="{{ old('official_title', $archive->official_title) }}">
            <input type="hidden" name="official_video_url" value="{{ old('official_video_url', $archive->official_video_url) }}">

            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <div class="grid gap-5 lg:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">動画タイトル</label>
                        <input
                            type="text"
                            id="official-edited-title-input"
                            name="official_edited_title"
                            value="{{ old('official_edited_title', $archive->official_edited_title) }}"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3"
                            placeholder="公式編集動画のタイトル"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">動画URL</label>
                        <input
                            type="text"
                            id="official-edited-url-input"
                            name="official_edited_video_url"
                            value="{{ old('official_edited_video_url', $archive->official_edited_video_url) }}"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3"
                            placeholder="公式編集動画のURL"
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-300">
            <div class="grid grid-cols-4 bg-gray-50 text-center text-sm font-bold text-gray-700">
                <div class="border-r border-gray-300 p-4">区分</div>
                <div class="border-r border-gray-300 p-4">タイトル / ジャンル</div>
                <div class="border-r border-gray-300 p-4">参加者</div>
                <div class="p-4">動画URL</div>
            </div>

            @foreach($sectionConfigs as $type => $label)
                @php
                    $section = $sectionsByType[$type];
                @endphp

                <div class="grid grid-cols-4 border-t border-gray-300">
                    <div class="border-r border-gray-300 p-5 font-bold text-gray-800">
                        {{ $label }}
                    </div>

                    <div class="border-r border-gray-300 p-5">
                        @php
                            $selectedGenre = old("sections.$type.genre", $section->game_genre);
                        @endphp

                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ $label }}のゲーム</label>
                        <select
                            id="{{ $type }}-genre-select"
                            name="sections[{{ $type }}][genre]"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2"
                        >
                            <option value="">未設定</option>
                            @if($selectedGenre && !$games->contains('name', $selectedGenre))
                                <option value="{{ $selectedGenre }}" selected>{{ $selectedGenre }}</option>
                            @endif
                            @foreach($games as $game)
                                <option value="{{ $game->name }}" @selected($selectedGenre === $game->name)>
                                    {{ $game->name }}
                                </option>
                            @endforeach
                        </select>
                        <div id="{{ $type }}-genre-badge" class="mt-2 hidden items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700">
                                ⚠ ゲーム未登録
                            </span>
                            <button
                                type="button"
                                class="register-game-btn rounded-lg border border-amber-400 bg-white px-2 py-1 text-xs font-bold text-amber-700 hover:bg-amber-50"
                                data-section-type="{{ $type }}"
                            >
                                このゲームを登録する
                            </button>
                        </div>
                    </div>

                    <div class="border-r border-gray-300 p-5">
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ $label }}の参加者</label>

                        <div class="relative">
                            <input
                                type="text"
                                id="{{ $type }}-member-search"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2"
                                placeholder="参加者名で検索"
                                autocomplete="off"
                            >

                            <div
                                id="{{ $type }}-member-suggestions"
                                class="absolute z-10 mt-1 hidden w-full rounded-lg border border-gray-200 bg-white shadow"
                            ></div>
                        </div>

                        <div id="{{ $type }}-member-tags" class="mt-3 space-y-2"></div>
                        <div id="{{ $type }}-member-hidden-inputs"></div>
                    </div>

                    <div class="p-5">
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ $label }}の動画URL</label>
                        <div id="{{ $type }}-member-url-inputs" class="space-y-3"></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rounded-2xl border border-gray-200 p-6">
            <h2 class="mb-4 text-lg font-bold text-gray-800">この日はどんなことが起きてた？</h2>
            <textarea
                name="description"
                rows="5"
                class="w-full rounded-lg border border-gray-300 px-4 py-3"
                placeholder="その日の補足メモ"
            >{{ old('description', $archive->description) }}</textarea>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('admin.archives.edit', ['date' => \Carbon\Carbon::parse($date)->copy()->subDay()->format('Y-m-d')]) }}"
               class="rounded-lg border border-gray-300 px-4 py-2 hover:bg-gray-50">
                前日
            </a>

            <a href="{{ route('admin.archives.index', ['month' => \Carbon\Carbon::parse($date)->format('Y-m')]) }}"
               class="rounded-lg border border-gray-300 px-4 py-2 hover:bg-gray-50">
                カレンダーに戻る
            </a>

            <a href="{{ route('admin.archives.edit', ['date' => \Carbon\Carbon::parse($date)->copy()->addDay()->format('Y-m-d')]) }}"
               class="rounded-lg border border-gray-300 px-4 py-2 hover:bg-gray-50">
                翌日
            </a>
        </div>

        <div class="text-center">
            <button
                type="submit"
                formaction="{{ route('admin.archives.autofill-games', ['date' => $date]) }}"
                formmethod="POST"
                class="mr-3 rounded-xl border border-green-500 px-8 py-4 text-lg font-bold text-green-700 hover:bg-green-50">
                ゲーム自動入力
            </button>

            <button
                type="submit"
                class="rounded-xl bg-green-500 px-8 py-4 text-lg font-bold text-white hover:bg-green-600">
                保存する
            </button>
        </div>
    </form>

    @php
        $hasSavedContent = $archive->exists
            && $archive->sections->contains(fn ($section) => $section->members->isNotEmpty());
    @endphp

    <div class="mt-4 flex items-center justify-center gap-3">
        <button type="button" class="mark-status-btn rounded-xl border px-6 py-3 text-sm font-bold {{ $archive->status === 'tentative' ? 'border-amber-500 bg-amber-100 text-amber-800' : 'border-amber-300 text-amber-700 hover:bg-amber-50' }}"
                data-url="{{ route('admin.archives.mark-status', ['date' => $date]) }}"
                data-status="tentative">
            {{ $archive->status === 'tentative' ? '仮完了を解除' : '仮完了にする' }}
        </button>

        <button type="button" class="mark-status-btn rounded-xl border px-6 py-3 text-sm font-bold {{ $archive->status === 'done' ? 'border-green-500 bg-green-100 text-green-800' : 'border-green-300 text-green-700 hover:bg-green-50' }}"
                data-url="{{ route('admin.archives.mark-status', ['date' => $date]) }}"
                data-status="done">
            {{ $archive->status === 'done' ? '完了を解除' : '完了にする' }}
        </button>
    </div>

    <div class="mt-4 text-center">
        <button type="button" id="mark-no-stream-btn"
                data-has-content="{{ $hasSavedContent ? '1' : '0' }}"
                data-url="{{ route('admin.archives.mark-no-stream', ['date' => $date]) }}"
                data-month="{{ \Carbon\Carbon::parse($date)->format('Y-m') }}"
                class="rounded-xl border border-gray-400 px-8 py-4 text-lg font-bold text-gray-600 hover:bg-gray-50">
            この日は配信なしで保存
        </button>
    </div>
</div>
@endsection

@php
    $archiveMemberDataForJs = [
        'allMembers' => $allMembersForJs,
        'sections' => $initialSectionMembersForJs,
        'sectionTypes' => array_keys($sectionConfigs),
        'markAbsentUrl' => route('admin.archives.mark-absent', ['date' => $date]),
        'removeMemberUrl' => route('admin.archives.remove-member', ['date' => $date]),
        'extractFromVideoUrl' => route('admin.archives.extract-participants-from-video', ['date' => $date]),
        'registeredGameNames' => $registeredGameNamesForJs,
        'registerGameUrl' => route('admin.games.store'),
    ];
@endphp

<script type="application/json" id="archive-member-data">
{!! \Illuminate\Support\Js::encode($archiveMemberDataForJs) !!}
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const archiveMemberData = JSON.parse(document.getElementById('archive-member-data').textContent);
    const allMembers = archiveMemberData.allMembers;

    function setupMemberSelector(config) {
        const searchInput = document.getElementById(config.searchId);
        const suggestionsBox = document.getElementById(config.suggestionsId);
        const tagsBox = document.getElementById(config.tagsId);
        const hiddenInputsBox = document.getElementById(config.hiddenInputsId);
        const urlInputsBox = document.getElementById(config.urlInputsId);

        if (!searchInput || !suggestionsBox || !tagsBox || !hiddenInputsBox || !urlInputsBox) {
            return;
        }

        let selectedMembers = {};

        config.initialMembers.forEach(member => {
            selectedMembers[member.id] = member;
        });

        function renderSuggestions(keyword) {
            const q = keyword.trim().toLowerCase();

            if (!q) {
                suggestionsBox.innerHTML = '';
                suggestionsBox.classList.add('hidden');
                return;
            }

            const filteredMembers = allMembers.filter(member => {
                return member.name.toLowerCase().includes(q)
                    && !selectedMembers[member.id];
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
                <div class="flex items-center justify-between rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <span>${member.name}</span>
                    <span class="flex items-center gap-2">
                        <button type="button" class="mark-absent-member text-xs text-orange-600" data-id="${member.id}">
                            急遽不参加
                        </button>
                        <button type="button" class="remove-member text-red-500" data-id="${member.id}">
                            削除
                        </button>
                    </span>
                </div>
            `).join('');

            hiddenInputsBox.innerHTML = membersArray.map(member => `
                <input type="hidden" name="${config.inputName}[${member.id}][selected]" value="1">
            `).join('');

            urlInputsBox.innerHTML = membersArray.map(member => `
                <div class="member-url-row" data-id="${member.id}">
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        ${member.name} の動画URL
                    </label>
                    <input
                        type="text"
                        class="member-video-url-input w-full rounded-lg border px-3 py-2 ${(member.no_stream || member.url_expired) ? 'border-gray-200 bg-gray-100 text-gray-400' : (member.needs_review ? 'border-red-400' : 'border-gray-300')}"
                        name="${config.inputName}[${member.id}][video_url]"
                        value="${member.video_url ?? ''}"
                        ${(member.no_stream || member.url_expired) ? 'disabled' : ''}
                        data-id="${member.id}"
                        placeholder="${member.no_stream ? '配信なし' : (member.url_expired ? 'URL有効期限切れ' : member.name + ' の動画URL')}"
                    >
                    <button type="button"
                            class="extract-from-video mt-1 block text-xs font-medium text-indigo-600 hover:underline ${member.video_url ? '' : 'hidden'}"
                            data-id="${member.id}">
                        この動画から参加者を抽出する
                    </button>
                    <label class="no-stream-label mt-1 flex items-center gap-1 text-xs text-gray-600 ${member.video_url ? 'hidden' : ''}">
                        <input
                            type="checkbox"
                            class="no-stream-checkbox"
                            data-id="${member.id}"
                            ${member.no_stream ? 'checked' : ''}
                        >
                        配信なしで参加（URL未登録ではなく確認済み）
                    </label>
                    <label class="url-expired-label mt-1 flex items-center gap-1 text-xs text-gray-600 ${member.video_url ? 'hidden' : ''}">
                        <input
                            type="checkbox"
                            class="url-expired-checkbox"
                            data-id="${member.id}"
                            ${member.url_expired ? 'checked' : ''}
                        >
                        配信はしたがURL有効期限切れでアーカイブなし（Twitch等）
                    </label>
                    <input type="hidden" name="${config.inputName}[${member.id}][no_stream]" value="${member.no_stream ? '1' : '0'}">
                    <input type="hidden" name="${config.inputName}[${member.id}][url_expired]" value="${member.url_expired ? '1' : '0'}">
                    ${member.needs_review ? `
                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
                            ⚠ URL要確認: ${member.review_reason ?? ''}
                        </span>
                    ` : ''}
                </div>
            `).join('');
        }

        searchInput.addEventListener('input', function () {
            renderSuggestions(this.value);
        });

        suggestionsBox.addEventListener('click', function (e) {
            const button = e.target.closest('.member-suggestion');
            if (!button) return;

            selectedMembers[button.dataset.id] = {
                id: button.dataset.id,
                name: button.dataset.name,
                video_url: '',
            };

            searchInput.value = '';
            suggestionsBox.classList.add('hidden');

            renderSelectedMembers();
        });

        tagsBox.addEventListener('click', function (e) {
            const removeButton = e.target.closest('.remove-member');

            if (removeButton) {
                const member = selectedMembers[removeButton.dataset.id];
                if (!member) return;

                removeButton.disabled = true;

                const csrfToken = document.querySelector('input[name="_token"]').value;

                fetch(config.removeMemberUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        section_type: config.sectionType,
                        member_id: removeButton.dataset.id,
                    }),
                })
                    .then(response => {
                        if (!response.ok) throw new Error('failed');
                        delete selectedMembers[removeButton.dataset.id];
                        renderSelectedMembers();
                    })
                    .catch(() => {
                        removeButton.disabled = false;
                        alert('削除に失敗しました。時間を置いて再度お試しください。');
                    });

                return;
            }

            const absentButton = e.target.closest('.mark-absent-member');

            if (!absentButton) return;

            const member = selectedMembers[absentButton.dataset.id];
            if (!member) return;

            if (!confirm(`${member.name}さんを「急遽不参加」として記録します。以後この日この区分の抽出結果からも除外されます。よろしいですか？`)) {
                return;
            }

            absentButton.disabled = true;

            const csrfToken = document.querySelector('input[name="_token"]').value;

            fetch(config.markAbsentUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    section_type: config.sectionType,
                    member_id: absentButton.dataset.id,
                }),
            })
                .then(response => {
                    if (!response.ok) throw new Error('failed');
                    delete selectedMembers[absentButton.dataset.id];
                    renderSelectedMembers();
                })
                .catch(() => {
                    absentButton.disabled = false;
                    alert('急遽不参加の記録に失敗しました。時間を置いて再度お試しください。');
                });
        });

        urlInputsBox.addEventListener('change', function (e) {
            const noStreamCheckbox = e.target.closest('.no-stream-checkbox');
            const urlExpiredCheckbox = e.target.closest('.url-expired-checkbox');

            if (noStreamCheckbox) {
                const member = selectedMembers[noStreamCheckbox.dataset.id];
                if (!member) return;

                member.no_stream = noStreamCheckbox.checked;

                // 「配信なし」と「URL有効期限切れ」は同時に成立しない状態のため排他にする。
                if (member.no_stream) {
                    member.video_url = '';
                    member.url_expired = false;
                }

                renderSelectedMembers();
                return;
            }

            if (urlExpiredCheckbox) {
                const member = selectedMembers[urlExpiredCheckbox.dataset.id];
                if (!member) return;

                member.url_expired = urlExpiredCheckbox.checked;

                if (member.url_expired) {
                    member.video_url = '';
                    member.no_stream = false;
                }

                renderSelectedMembers();
            }
        });

        urlInputsBox.addEventListener('input', function (e) {
            const input = e.target.closest('.member-video-url-input');
            if (!input) return;

            const row = input.closest('.member-url-row');
            const noStreamLabel = row?.querySelector('.no-stream-label');
            const urlExpiredLabel = row?.querySelector('.url-expired-label');
            const extractButton = row?.querySelector('.extract-from-video');
            if (!noStreamLabel && !urlExpiredLabel && !extractButton) return;

            // URLが入力されたら「配信なしで参加」「URL有効期限切れ」チェックボックスは不要なので隠す。
            const hasValue = input.value.trim() !== '';
            noStreamLabel?.classList.toggle('hidden', hasValue);
            urlExpiredLabel?.classList.toggle('hidden', hasValue);
            extractButton?.classList.toggle('hidden', !hasValue);

            const member = selectedMembers[input.dataset.id];
            if (member) {
                member.video_url = input.value;
            }
        });

        // 既存の選択はそのまま残し、新しく検出された分だけ追加する。
        // membersの各要素にvideo_urlが含まれていれば（本人動画で裏付け済みの場合）そのまま使う。
        // 既に選択済みのメンバーでも、URL欄が空で新しくURLが検出できた場合だけ補完する
        // （手動入力済みのURLは上書きしない）。
        function addMembers(members) {
            members.forEach(member => {
                const existing = selectedMembers[member.id];

                if (!existing) {
                    selectedMembers[member.id] = { id: String(member.id), name: member.name, video_url: member.video_url || '' };
                } else if (!existing.video_url && member.video_url) {
                    existing.video_url = member.video_url;
                }
            });
            renderSelectedMembers();
        }

        urlInputsBox.addEventListener('click', function (e) {
            const button = e.target.closest('.extract-from-video');
            if (!button) return;

            const member = selectedMembers[button.dataset.id];
            const videoUrl = (member?.video_url || '').trim();
            if (!videoUrl) return;

            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = '抽出中...';

            const csrfToken = document.querySelector('input[name="_token"]').value;

            fetch(config.extractFromVideoUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    section_type: config.sectionType,
                    member_id: button.dataset.id,
                    video_url: videoUrl,
                    existing_member_ids: Object.keys(selectedMembers),
                }),
            })
                .then(async function (response) {
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(data.message || '抽出に失敗しました。時間を置いて再度お試しください。');
                    }

                    return data;
                })
                .then(function (data) {
                    if (data.members && data.members.length) {
                        addMembers(data.members);
                    } else {
                        const note = (data.unverified_names && data.unverified_names.length)
                            ? `\n（概要欄に名前はありましたが、本人の配信での参加は確認できませんでした: ${data.unverified_names.map(u => u.name).join('、')}）`
                            : '';
                        alert(`この動画からは、参加が確認できる他の参加者が見つかりませんでした。${note}`);
                    }
                })
                .catch(function (error) {
                    alert(error.message || '抽出に失敗しました。時間を置いて再度お試しください。');
                })
                .finally(function () {
                    button.disabled = false;
                    button.textContent = originalText;
                });
        });

        renderSelectedMembers();

        return { addMembers };
    }

    const sectionControllers = {};

    archiveMemberData.sectionTypes.forEach(type => {
        sectionControllers[type] = setupMemberSelector({
            searchId: `${type}-member-search`,
            suggestionsId: `${type}-member-suggestions`,
            tagsId: `${type}-member-tags`,
            hiddenInputsId: `${type}-member-hidden-inputs`,
            urlInputsId: `${type}-member-url-inputs`,
            inputName: `sections[${type}][members]`,
            initialMembers: archiveMemberData.sections[type] ?? [],
            sectionType: type,
            markAbsentUrl: archiveMemberData.markAbsentUrl,
            removeMemberUrl: archiveMemberData.removeMemberUrl,
            extractFromVideoUrl: archiveMemberData.extractFromVideoUrl,
        });
    });

    const registeredGameNames = new Set(archiveMemberData.registeredGameNames.map(name => name.toLowerCase()));

    function updateGenreBadge(type) {
        const select = document.getElementById(`${type}-genre-select`);
        const badge = document.getElementById(`${type}-genre-badge`);
        if (!select || !badge) return;

        const value = select.value.trim();
        const isUnregistered = value !== '' && !registeredGameNames.has(value.toLowerCase());

        badge.classList.toggle('hidden', !isUnregistered);
        badge.classList.toggle('flex', isUnregistered);
    }

    archiveMemberData.sectionTypes.forEach(type => {
        updateGenreBadge(type);
        document.getElementById(`${type}-genre-select`)
            ?.addEventListener('change', () => updateGenreBadge(type));
    });

    document.querySelectorAll('.register-game-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const type = button.dataset.sectionType;
            const select = document.getElementById(`${type}-genre-select`);
            const name = select?.value.trim();
            if (!name) return;

            const csrfToken = document.querySelector('input[name="_token"]').value;
            button.disabled = true;

            fetch(archiveMemberData.registerGameUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ name }),
            })
                .then(response => {
                    if (!response.ok) throw new Error('failed');
                    return response.json();
                })
                .then(data => {
                    registeredGameNames.add(data.name.toLowerCase());
                    updateGenreBadge(type);
                })
                .catch(() => {
                    alert('ゲームの登録に失敗しました。時間を置いて再度お試しください。');
                })
                .finally(() => {
                    button.disabled = false;
                });
        });
    });

    const computeBtn = document.getElementById('compute-participants-btn');
    const computeMessage = document.getElementById('compute-participants-message');

    // 古い日付ほど抽出に数分かかることがあり、ジョブキュー化して非同期実行している。
    // ここではジョブを開始してから、完了/失敗が確定するまで数秒おきに状態をポーリングする。
    async function pollComputeParticipantsStatus(statusUrl, onProgress) {
        const pollIntervalMs = 3000;
        const maxWaitMs = 10 * 60 * 1000;
        const startedAt = Date.now();

        while (true) {
            const response = await fetch(statusUrl, {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok && response.status !== 404) {
                let message = '抽出処理の状態確認に失敗しました。時間を置いて再度お試しください。';

                try {
                    const errorData = await response.json();
                    if (errorData && errorData.message) {
                        message = errorData.message;
                    }
                } catch (parseError) {
                    // JSONで返ってこなかった場合はデフォルトメッセージのまま。
                }

                throw new Error(message);
            }

            const data = await response.json();

            if (data.status === 'done') {
                return data;
            }

            if (data.status === 'failed') {
                throw new Error(data.message || '抽出処理でエラーが発生しました。');
            }

            if (Date.now() - startedAt > maxWaitMs) {
                throw new Error('抽出処理が長時間終わりません。処理自体はサーバー側で継続しているため、少し時間を置いてから画面を再読み込みして確認してください。');
            }

            onProgress(Math.floor((Date.now() - startedAt) / 1000));
            await new Promise(resolve => setTimeout(resolve, pollIntervalMs));
        }
    }

    if (computeBtn) {
        computeBtn.addEventListener('click', async function () {
            const csrfToken = document.querySelector('input[name="_token"]').value;

            computeBtn.disabled = true;
            computeBtn.textContent = '抽出を開始しています...';
            computeMessage.classList.add('hidden');

            try {
                const startResponse = await fetch(computeBtn.dataset.url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });

                if (!startResponse.ok) {
                    let message = '抽出の開始に失敗しました。時間を置いて再度お試しください。';

                    try {
                        const errorData = await startResponse.json();
                        if (errorData && errorData.message) {
                            message = errorData.message;
                        }
                    } catch (parseError) {
                        // JSONで返ってこなかった場合はデフォルトメッセージのまま。
                    }

                    throw new Error(message);
                }

                const { token } = await startResponse.json();
                const statusUrl = computeBtn.dataset.statusUrlTemplate.replace('__TOKEN__', token);

                const data = await pollComputeParticipantsStatus(statusUrl, function (elapsedSeconds) {
                    computeBtn.textContent = `抽出中...(${elapsedSeconds}秒経過)`;
                });

                let addedCount = 0;

                Object.entries(data.sections || {}).forEach(([type, section]) => {
                    const controller = sectionControllers[type];

                    if (controller && section.members && section.members.length) {
                        controller.addMembers(section.members);
                        addedCount += section.members.length;
                    }

                    if (section.genre) {
                        const genreSelect = document.querySelector(`select[name="sections[${type}][genre]"]`);

                        // 既に手動で選ばれているものは上書きしない。
                        if (genreSelect && !genreSelect.value) {
                            let option = Array.from(genreSelect.options).find(o => o.value === section.genre);

                            if (!option) {
                                option = new Option(section.genre, section.genre);
                                genreSelect.add(option);
                            }

                            genreSelect.value = section.genre;
                        }

                        updateGenreBadge(type);
                    }
                });

                let officialFilled = false;
                const officialTitleInput = document.getElementById('official-edited-title-input');
                const officialUrlInput = document.getElementById('official-edited-url-input');

                // 既に手動で入力済みの場合は上書きしない。
                if (data.official && officialTitleInput && officialUrlInput
                    && !officialTitleInput.value && !officialUrlInput.value) {
                    officialTitleInput.value = data.official.title || '';
                    officialUrlInput.value = data.official.url || '';
                    officialFilled = true;
                }

                if (addedCount > 0 || officialFilled) {
                    computeMessage.textContent = '参加者・ゲーム内容・公式編集動画の候補を反映しました（保存する前に内容を確認してください）。';
                    computeMessage.className = 'mb-6 rounded-xl border px-4 py-3 text-sm font-bold border-blue-200 bg-blue-50 text-blue-800';
                } else {
                    computeMessage.classList.add('hidden');
                    alert('この日は「アルジャン」タグ付きの配信が見つかりませんでした。\n（対象メンバーの登録漏れ、または実際にこの日は配信が無かった可能性があります）');
                }
            } catch (error) {
                computeMessage.textContent = error.message || '抽出に失敗しました。時間を置いて再度お試しください。';
                computeMessage.className = 'mb-6 rounded-xl border px-4 py-3 text-sm font-bold border-red-200 bg-red-50 text-red-700';
            } finally {
                computeBtn.disabled = false;
                computeBtn.textContent = 'ゲーム内容と参加メンバーを抽出する';
            }
        });
    }

    const markNoStreamBtn = document.getElementById('mark-no-stream-btn');

    if (markNoStreamBtn) {
        markNoStreamBtn.addEventListener('click', function () {
            const hasContent = this.dataset.hasContent === '1';

            if (hasContent && !confirm('この日は既に参加者・URLなどの内容が保存されています。配信なしとして扱ってよろしいですか？（保存済みのデータ自体は削除されません）')) {
                return;
            }

            const csrfToken = document.querySelector('input[name="_token"]').value;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = this.dataset.url;
            form.style.display = 'none';
            form.innerHTML = `
                <input type="hidden" name="_token" value="${csrfToken}">
                <input type="hidden" name="no_stream" value="1">
                <input type="hidden" name="month" value="${this.dataset.month}">
            `;
            document.body.appendChild(form);
            form.submit();
        });
    }

    document.querySelectorAll('.mark-status-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const csrfToken = document.querySelector('input[name="_token"]').value;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = this.dataset.url;
            form.style.display = 'none';
            form.innerHTML = `
                <input type="hidden" name="_token" value="${csrfToken}">
                <input type="hidden" name="status" value="${this.dataset.status}">
            `;
            document.body.appendChild(form);
            form.submit();
        });
    });
});
</script>
