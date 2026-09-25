@extends('layouts.admin')

@section('content')
<div class="w-full max-w-6xl rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
    <div class="mb-8 flex items-center justify-between">
        <h1 class="text-4xl font-bold text-gray-800">特別回の管理</h1>

        <a href="{{ route('admin.archives.index') }}"
           class="rounded-xl border border-gray-300 px-5 py-3 text-sm font-medium hover:bg-gray-50">
            カレンダーへ戻る
        </a>
    </div>

    <p class="mb-6 text-sm text-gray-500">
        特別回はアルジャン企画に限らず、他コミュニティとの対抗戦などイレギュラーな内容のため、時間帯による自動判定の対象外にしています。ここで手動で動画URLを設定してください。
    </p>

    @if(session('success'))
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

    <div class="mb-10 overflow-hidden rounded-2xl border border-gray-300">
        <div class="bg-gray-50 p-5 text-lg font-bold text-gray-800">新しい特別回を登録</div>

        <form action="{{ route('admin.archives.special.store') }}" method="POST" class="space-y-5 p-5">
            @csrf

            <div class="grid gap-5 lg:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">日付</label>
                    <input
                        type="date"
                        id="new-special-event-date"
                        name="event_date"
                        value="{{ old('event_date') }}"
                        class="w-full rounded-lg border border-gray-300 px-4 py-3"
                        required
                    >
                    <p class="mt-1 text-xs text-gray-500">
                        通常アーカイブとして既に参加者が登録されている日付は選べません。
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">ゲームジャンル</label>
                    <select id="new-special-game-genre" name="game_genre" class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3">
                        <option value="">未設定</option>
                        @foreach($games as $game)
                            <option value="{{ $game->name }}" @selected(old('game_genre') === $game->name)>
                                {{ $game->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">参加メンバー</label>

                <div class="relative">
                    <input
                        type="text"
                        id="new-special-member-search"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        placeholder="参加者名で検索"
                        autocomplete="off"
                    >

                    <div
                        id="new-special-member-suggestions"
                        class="absolute z-10 mt-1 hidden w-full rounded-lg border border-gray-200 bg-white shadow"
                    ></div>
                </div>

                <div id="new-special-member-tags" class="mt-3 space-y-2"></div>
                <div id="new-special-member-hidden-inputs"></div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">参加メンバーの動画URL</label>
                <div id="new-special-member-url-inputs" class="space-y-3"></div>
            </div>

            <div class="text-right">
                <button
                    type="submit"
                    class="rounded-xl bg-green-500 px-6 py-3 text-sm font-bold text-white hover:bg-green-600">
                    特別回を登録
                </button>
            </div>
        </form>
    </div>

    @forelse($sections as $section)
        <div class="mb-6 overflow-hidden rounded-2xl border border-gray-300">
            <form action="{{ route('admin.archives.special.update', ['section' => $section->id]) }}" method="POST">
                @csrf

                <div class="flex items-center justify-between bg-gray-50 p-5">
                    <div>
                        <div class="text-lg font-bold text-gray-800">
                            {{ \Carbon\Carbon::parse($section->onedayarchive?->event_date)->format('Y年n月j日') }}
                            の特別回
                        </div>
                        <a href="{{ route('admin.archives.edit', ['date' => $section->onedayarchive?->event_date]) }}"
                           class="text-xs text-gray-500 underline hover:text-gray-700">
                            この日のカレンダー編集画面を開く
                        </a>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">ゲームジャンル</label>
                        <input
                            type="text"
                            id="special-genre-input-{{ $section->id }}"
                            name="game_genre"
                            value="{{ old('game_genre', $section->game_genre) }}"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            placeholder="例: 対抗戦"
                        >
                        <div id="special-genre-badge-{{ $section->id }}" class="mt-2 hidden items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700">
                                ⚠ ゲーム未登録
                            </span>
                            <button
                                type="button"
                                class="register-game-btn rounded-lg border border-amber-400 bg-white px-2 py-1 text-xs font-bold text-amber-700 hover:bg-amber-50"
                                data-section-id="{{ $section->id }}"
                            >
                                このゲームを登録する
                            </button>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-gray-200">
                    @foreach($section->members as $member)
                        @php
                            $needsReview = (bool) ($member->pivot->needs_review ?? false);
                        @endphp

                        <div class="grid grid-cols-[160px_1fr] items-start gap-4 p-5">
                            <div class="pt-2 font-medium text-gray-800">{{ $member->name }}</div>

                            <div>
                                <input
                                    type="text"
                                    id="special-video-url-{{ $section->id }}-{{ $member->id }}"
                                    name="members[{{ $member->id }}][video_url]"
                                    value="{{ old("members.{$member->id}.video_url", $member->pivot->video_url) }}"
                                    class="w-full rounded-lg border {{ $needsReview ? 'border-red-400' : 'border-gray-300' }} px-3 py-2"
                                    placeholder="{{ $member->name }} の動画URL"
                                >

                                <button type="button"
                                        id="special-extract-btn-{{ $section->id }}-{{ $member->id }}"
                                        class="extract-special-btn mt-1 block text-xs font-medium text-indigo-600 hover:underline {{ $member->pivot->video_url ? '' : 'hidden' }}"
                                        data-url="{{ route('admin.archives.special.extract-participants-from-video', ['section' => $section->id]) }}"
                                        data-video-url-id="special-video-url-{{ $section->id }}-{{ $member->id }}"
                                        data-genre-input="special-genre-input-{{ $section->id }}"
                                        data-member-id="{{ $member->id }}">
                                    この動画から参加者を抽出する
                                </button>

                                @if($needsReview)
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
                                        ⚠ URL要確認: {{ $member->pivot->review_reason }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-between bg-gray-50 p-4">
                    <button
                        type="button"
                        class="delete-special-btn rounded-xl border border-red-300 px-6 py-3 text-sm font-bold text-red-600 hover:bg-red-50"
                        data-url="{{ route('admin.archives.special.destroy', ['section' => $section->id]) }}">
                        この特別回を削除
                    </button>

                    <button
                        type="submit"
                        class="rounded-xl bg-green-500 px-6 py-3 text-sm font-bold text-white hover:bg-green-600">
                        この特別回を保存
                    </button>
                </div>
            </form>
        </div>
    @empty
        <p class="text-gray-500">参加者が登録されている特別回はまだありません。</p>
    @endforelse
</div>

<script type="application/json" id="all-members-data">
{!! \Illuminate\Support\Js::encode($allMembersForJs) !!}
</script>

<script type="application/json" id="special-game-data">
{!! \Illuminate\Support\Js::encode([
    'registeredGameNames' => $registeredGameNamesForJs,
    'registerGameUrl' => route('admin.games.store'),
    'extractForNewRegistrationUrl' => route('admin.archives.special.extract-participants-from-video-new'),
]) !!}
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const allMembers = JSON.parse(document.getElementById('all-members-data').textContent);
    const specialGameData = JSON.parse(document.getElementById('special-game-data').textContent);
    const registeredGameNames = new Set(specialGameData.registeredGameNames.map(name => name.toLowerCase()));

    function updateSpecialGenreBadge(sectionId) {
        const input = document.getElementById(`special-genre-input-${sectionId}`);
        const badge = document.getElementById(`special-genre-badge-${sectionId}`);
        if (!input || !badge) return;

        const value = input.value.trim();
        const isUnregistered = value !== '' && !registeredGameNames.has(value.toLowerCase());

        badge.classList.toggle('hidden', !isUnregistered);
        badge.classList.toggle('flex', isUnregistered);
    }

    document.querySelectorAll('[id^="special-genre-input-"]').forEach(function (input) {
        const sectionId = input.id.replace('special-genre-input-', '');
        updateSpecialGenreBadge(sectionId);
        input.addEventListener('input', () => updateSpecialGenreBadge(sectionId));
    });

    document.querySelectorAll('input[id^="special-video-url-"]').forEach(function (input) {
        const suffix = input.id.replace('special-video-url-', '');
        const button = document.getElementById(`special-extract-btn-${suffix}`);
        if (!button) return;

        input.addEventListener('input', function () {
            button.classList.toggle('hidden', input.value.trim() === '');
        });
    });

    document.querySelectorAll('.register-game-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const sectionId = button.dataset.sectionId;
            const input = document.getElementById(`special-genre-input-${sectionId}`);
            const name = input?.value.trim();
            if (!name) return;

            const csrfToken = document.querySelector('input[name="_token"]').value;
            button.disabled = true;

            fetch(specialGameData.registerGameUrl, {
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
                    updateSpecialGenreBadge(sectionId);
                })
                .catch(() => {
                    alert('ゲームの登録に失敗しました。時間を置いて再度お試しください。');
                })
                .finally(() => {
                    button.disabled = false;
                });
        });
    });

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
                    <button type="button" class="remove-member text-red-500" data-id="${member.id}">
                        削除
                    </button>
                </div>
            `).join('');

            hiddenInputsBox.innerHTML = membersArray.map(member => `
                <input type="hidden" name="${config.inputName}[${member.id}][selected]" value="1">
            `).join('');

            urlInputsBox.innerHTML = membersArray.map(member => `
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        ${member.name} の動画URL
                    </label>
                    <input
                        type="text"
                        name="${config.inputName}[${member.id}][video_url]"
                        value="${member.video_url ?? ''}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        placeholder="${member.name} の動画URL"
                        data-id="${member.id}"
                    >
                    ${config.extractFromVideoUrl ? `
                        <button type="button"
                                class="extract-new-special-btn mt-1 block text-xs font-medium text-indigo-600 hover:underline ${member.video_url ? '' : 'hidden'}"
                                data-id="${member.id}">
                            この動画から参加者を抽出する
                        </button>
                    ` : ''}
                </div>
            `).join('');
        }

        // 検出された確認済みメンバーだけを追加する（要確認候補はアラートで知らせるのみ、
        // 通常アーカイブ編集画面のextractFromVideoと同じ「保存前の候補提示」の考え方）。
        // 既存の選択は上書きしない。
        function addExtractedMembers(members) {
            members.forEach(member => {
                if (!selectedMembers[member.id]) {
                    selectedMembers[member.id] = { id: String(member.id), name: member.name, video_url: member.video_url || '' };
                }
            });
            renderSelectedMembers();
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
            const button = e.target.closest('.remove-member');
            if (!button) return;

            delete selectedMembers[button.dataset.id];
            renderSelectedMembers();
        });

        // メンバーの追加・削除でrenderSelectedMembers()が再描画されても、既に入力済みの
        // 他メンバーのURLが消えないよう、入力のたびにselectedMembers側の状態へ同期する。
        urlInputsBox.addEventListener('input', function (e) {
            const input = e.target.closest('input[type="text"]');
            if (!input) return;

            const member = selectedMembers[input.dataset.id];
            if (member) {
                member.video_url = input.value;
            }

            const button = urlInputsBox.querySelector(`.extract-new-special-btn[data-id="${input.dataset.id}"]`);
            button?.classList.toggle('hidden', input.value.trim() === '');
        });

        if (config.extractFromVideoUrl) {
            urlInputsBox.addEventListener('click', function (e) {
                const button = e.target.closest('.extract-new-special-btn');
                if (!button) return;

                const member = selectedMembers[button.dataset.id];
                const videoUrl = (member?.video_url || '').trim();
                if (!videoUrl) return;

                const dateInput = document.getElementById(config.dateInputId);
                const eventDate = dateInput ? dateInput.value : '';
                if (!eventDate) {
                    alert('先に日付を入力してください。');
                    return;
                }

                const genreInput = document.getElementById(config.genreInputId);

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
                        event_date: eventDate,
                        member_id: button.dataset.id,
                        video_url: videoUrl,
                        game_genre: genreInput ? genreInput.value.trim() : '',
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
                            addExtractedMembers(data.members);
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
        }

        renderSelectedMembers();
    }

    setupMemberSelector({
        searchId: 'new-special-member-search',
        suggestionsId: 'new-special-member-suggestions',
        tagsId: 'new-special-member-tags',
        hiddenInputsId: 'new-special-member-hidden-inputs',
        urlInputsId: 'new-special-member-url-inputs',
        inputName: 'members',
        extractFromVideoUrl: specialGameData.extractForNewRegistrationUrl,
        dateInputId: 'new-special-event-date',
        genreInputId: 'new-special-game-genre',
    });

    document.querySelectorAll('.delete-special-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!confirm('この特別回を削除します。よろしいですか？（保存済みの参加者・URLも削除されます）')) {
                return;
            }

            const csrfToken = document.querySelector('input[name="_token"]').value;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = this.dataset.url;
            form.style.display = 'none';
            form.innerHTML = `<input type="hidden" name="_token" value="${csrfToken}">`;
            document.body.appendChild(form);
            form.submit();
        });
    });

    document.querySelectorAll('.extract-special-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const videoUrlInput = document.getElementById(button.dataset.videoUrlId);
            const genreInput = document.getElementById(button.dataset.genreInput);
            const videoUrl = videoUrlInput ? videoUrlInput.value.trim() : '';
            if (!videoUrl) return;

            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = '抽出中...';

            const csrfToken = document.querySelector('input[name="_token"]').value;

            fetch(button.dataset.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    member_id: button.dataset.memberId,
                    video_url: videoUrl,
                    game_genre: genreInput ? genreInput.value.trim() : '',
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
                    if (data.added > 0 || data.needs_review > 0) {
                        alert(`確認済み${data.added}人、要確認${data.needs_review}人を追加しました。画面を再読み込みします。`);
                        location.reload();
                    } else {
                        alert('この動画からは新しい参加者候補が見つかりませんでした。');
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
    });
});
</script>
@endsection
