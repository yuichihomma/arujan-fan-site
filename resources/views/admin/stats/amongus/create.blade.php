@extends('layouts.admin')

@section('content')
<div class="rounded-[32px] border border-gray-200 bg-white/80 p-8 shadow-sm backdrop-blur-sm">
    <div class="mb-8">
        <h1 class="text-4xl font-bold tracking-tight">among us の戦績追加</h1>
        <p class="mt-2 text-lg text-gray-500">
            {{ $date }} の戦績を登録します。
        </p>
    </div>

    <div class="mx-auto max-w-4xl rounded-[28px] border border-gray-200 bg-white p-8 shadow-sm">
        <h2 class="mb-6 text-center text-2xl font-bold">among us の戦績追加</h2>

        <div class="mb-8 text-center text-2xl font-semibold">
            {{ $date }}
        </div>

        {{-- 全体送信用フォーム --}}
        <form action="{{ route('admin.stats.amongus.store', ['date' => $date]) }}" method="POST" class="space-y-10">
            @csrf

            {{-- 参加者の追加 --}}
            <section>
                <div class="mb-4 grid grid-cols-[180px_1fr] overflow-hidden rounded-xl border border-gray-300">
                    <div class="border-r border-gray-300 bg-gray-50 px-4 py-4 text-sm font-medium text-gray-700">
                        参加者の追加
                    </div>

                    <div class="px-4 py-4">
                        <div class="grid grid-cols-3 gap-4">
                            @for($i = 0; $i < 15; $i++)
                                <select name="member_ids[]" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    <option value="">選択してください</option>

                                    @foreach($members as $member)
                                        <option value="{{ $member->id }}"
                                            {{ (old("member_ids.$i", $selectedMemberIds[$i] ?? '') == $member->id) ? 'selected' : '' }}>
                                            {{ $member->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endfor
                        </div>
                    </div>
                </div>
            </section>

            {{-- レギュレーションの設定 --}}
            <section id="regulation-section">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-center text-2xl font-bold">レギュレーションの設定</h3>

                    <button
                        type="button"
                        id="toggle-regulation-box"
                        class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        閉じる
                    </button>
                </div>

                <div id="regulation-box">
                    {{-- 通常レギュレーション --}}
                    <div class="overflow-hidden rounded-xl border border-gray-300">
                        <table class="w-full table-fixed border-collapse text-center text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border border-gray-300 px-3 py-3">クルー</th>
                                    <th class="border border-gray-300 px-3 py-3">人数設定</th>
                                    <th class="border border-gray-300 px-3 py-3">インポスター</th>
                                    <th class="border border-gray-300 px-3 py-3">人数設定</th>
                                    <th class="border border-gray-300 px-3 py-3">第三陣営</th>
                                    <th class="border border-gray-300 px-3 py-3">人数設定</th>
                                    <th class="border border-gray-300 px-3 py-3">操作</th>
                                </tr>
                            </thead>
                            <tbody id="regulation-rows">
    @php
        $regulationRows = old('regulations', !empty($savedRegulations) ? $savedRegulations : [[]]);
    @endphp

    @foreach($regulationRows as $i => $row)
        <tr>
            <td class="border border-gray-300 px-3 py-3">
                <select name="regulations[{{ $i }}][crew_role_id]" class="regulation-role-select w-full rounded-md border border-gray-300 px-2 py-2 text-sm">
                    <option value="">役職設定</option>
                    @foreach($crewRoles as $role)
                        <option value="{{ $role->id }}"
                            {{ (string)($row['crew_role_id'] ?? '') === (string)$role->id ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </td>

            <td class="border border-gray-300 px-3 py-3">
                <input type="number"
                    name="regulations[{{ $i }}][crew_count]"
                    class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm"
                    placeholder="人数"
                    min="0"
                    value="{{ $row['crew_count'] ?? '' }}">
            </td>

            <td class="border border-gray-300 px-3 py-3">
                <select name="regulations[{{ $i }}][impostor_role_id]" class="regulation-role-select w-full rounded-md border border-gray-300 px-2 py-2 text-sm">
                    <option value="">役職設定</option>
                    @foreach($impostorRoles as $role)
                        <option value="{{ $role->id }}"
                            {{ (string)($row['impostor_role_id'] ?? '') === (string)$role->id ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </td>

            <td class="border border-gray-300 px-3 py-3">
                <input type="number"
                    name="regulations[{{ $i }}][impostor_count]"
                    class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm"
                    placeholder="人数"
                    min="0"
                    value="{{ $row['impostor_count'] ?? '' }}">
            </td>

            <td class="border border-gray-300 px-3 py-3">
                <select name="regulations[{{ $i }}][neutral_role_id]" class="regulation-role-select w-full rounded-md border border-gray-300 px-2 py-2 text-sm">
                    <option value="">役職設定</option>
                    @foreach($neutralRoles as $role)
                        <option value="{{ $role->id }}"
                            {{ (string)($row['neutral_role_id'] ?? '') === (string)$role->id ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </td>

            <td class="border border-gray-300 px-3 py-3">
                <input type="number"
                    name="regulations[{{ $i }}][neutral_count]"
                    class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm"
                    placeholder="人数"
                    min="0"
                    value="{{ $row['neutral_count'] ?? '' }}">
            </td>

            <td class="border border-gray-300 px-3 py-3">
                <button type="button" class="remove-regulation-row rounded-md border border-red-300 px-3 py-2 text-xs text-red-600 hover:bg-red-50">
                    削除
                </button>
            </td>
        </tr>
    @endforeach
</tbody>
                        </table>
                    </div>

                    {{-- 通常レギュレーションの行追加 --}}
                    <div class="mt-4 flex justify-center">
                        <button type="button" id="add-role-row"
                            class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                            ＋ 役職行を追加
                        </button>
                    </div>

                    {{-- 通常レギュレーション確定 --}}
                    <div class="mt-4 flex justify-center gap-3">
                        <button type="button" id="apply-regulations"
                            class="rounded-lg bg-gray-800 px-6 py-2 text-sm font-medium text-white hover:bg-gray-700">
                            レギュレーションを確定
                        </button>
                    </div>

                    {{-- ラジオ --}}
<div class="mt-4 text-center text-sm text-gray-500">
    <div class="flex items-center justify-center gap-8">
        <label class="flex items-center gap-2">
            <input
                type="radio"
                name="regulation_change"
                value="none"
                class="regulation-toggle"
                {{ old('regulation_change', $savedRegulationChange) === 'none' ? 'checked' : '' }}
            >
            <span>本日はこのレギュレーションで変更はありませんでした</span>
        </label>

        <label class="flex items-center gap-2">
            <input
                type="radio"
                name="regulation_change"
                value="changed"
                class="regulation-toggle"
                {{ old('regulation_change', $savedRegulationChange) === 'changed' ? 'checked' : '' }}
            >
            <span>途中変更あり（第X試合以降変更）</span>
        </label>
    </div>
</div>

{{-- 差分変更ボックス --}}
<div id="changed-regulation-box" class="mt-6 rounded-xl border border-gray-300 p-6" style="display: none;">
    {{-- 変更開始試合 --}}
    <div class="mb-6">
        <label class="mb-2 block text-sm font-medium text-gray-700">
            変更開始試合
        </label>

        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-600">第</span>

            <select
                name="regulation_change_match_number"
                class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
                <option value="">選択してください</option>
                @for($i = 1; $i <= 10; $i++)
                    <option
                        value="{{ $i }}"
                        {{ (string) old('regulation_change_match_number', $savedRegulationChangeMatchNumber) === (string) $i ? 'selected' : '' }}
                    >
                        {{ $i }}
                    </option>
                @endfor
            </select>

            <span class="text-sm text-gray-600">試合以降</span>
        </div>
    </div>

    {{-- 追加する役職 --}}
    <div class="mb-8">
        <h4 class="mb-4 text-lg font-bold text-gray-800">追加する役職</h4>

        <div class="overflow-hidden rounded-xl border border-gray-300">
            <table class="w-full table-fixed border-collapse text-center text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border border-gray-300 px-3 py-3">役職</th>
                        <th class="border border-gray-300 px-3 py-3">人数</th>
                        <th class="border border-gray-300 px-3 py-3">操作</th>
                    </tr>
                </thead>

                <tbody id="add-change-rows">
    @php
        $addRows = old('add_changes', !empty($savedAddChanges) ? $savedAddChanges : [[]]);
    @endphp

    @foreach($addRows as $i => $row)
        <tr>
            <td class="border border-gray-300 px-3 py-3">
                <select
                    name="add_changes[{{ $i }}][role_id]"
                    class="change-role-select w-full rounded-md border border-gray-300 px-2 py-2 text-sm"
                >
                    <option value="">追加する役職</option>

                    <optgroup label="クルー">
                        @foreach($crewRoles as $role)
                            <option
                                value="{{ $role->id }}"
                                {{ (string)($row['role_id'] ?? '') === (string)$role->id ? 'selected' : '' }}
                            >
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </optgroup>

                    <optgroup label="インポスター">
                        @foreach($impostorRoles as $role)
                            <option
                                value="{{ $role->id }}"
                                {{ (string)($row['role_id'] ?? '') === (string)$role->id ? 'selected' : '' }}
                            >
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </optgroup>

                    <optgroup label="第三陣営">
                        @foreach($neutralRoles as $role)
                            <option
                                value="{{ $role->id }}"
                                {{ (string)($row['role_id'] ?? '') === (string)$role->id ? 'selected' : '' }}
                            >
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </optgroup>
                </select>
            </td>

            <td class="border border-gray-300 px-3 py-3">
                <input
                    type="number"
                    name="add_changes[{{ $i }}][count]"
                    class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm"
                    placeholder="人数"
                    min="1"
                    value="{{ $row['count'] ?? '' }}"
                >
            </td>

            <td class="border border-gray-300 px-3 py-3">
                <button
                    type="button"
                    class="remove-add-change-row rounded-md border border-red-300 px-3 py-2 text-xs text-red-600 hover:bg-red-50"
                >
                    削除
                </button>
            </td>
        </tr>
    @endforeach
</tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-center">
            <button
                type="button"
                id="add-add-change-row"
                class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
            >
                ＋ 追加役職行を追加
            </button>
        </div>
    </div>

    {{-- 削除する役職 --}}
    <div>
        <h4 class="mb-4 text-lg font-bold text-gray-800">削除する役職</h4>

        <div class="overflow-hidden rounded-xl border border-gray-300">
            <table class="w-full table-fixed border-collapse text-center text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border border-gray-300 px-3 py-3">役職</th>
                        <th class="border border-gray-300 px-3 py-3">人数</th>
                        <th class="border border-gray-300 px-3 py-3">操作</th>
                    </tr>
                </thead>

               <tbody id="remove-change-rows">
    @php
        $removeRows = old('remove_changes', !empty($savedRemoveChanges) ? $savedRemoveChanges : [[]]);

        $initialRemoveRoleOptions = collect($regulationRows ?? [])
            ->flatMap(function ($row) {
                return [
                    [
                    'id' => $row['crew_role_id'] ?? null,
                    'name' => isset($row['crew_role_id']) ? ($crewRoleMap[$row['crew_role_id']] ?? null) : null,
                ],
                [
                    'id' => $row['impostor_role_id'] ?? null,
                    'name' => isset($row['impostor_role_id']) ? ($impostorRoleMap[$row['impostor_role_id']] ?? null) : null,
                ],
                [
                    'id' => $row['neutral_role_id'] ?? null,
                    'name' => isset($row['neutral_role_id']) ? ($neutralRoleMap[$row['neutral_role_id']] ?? null) : null,
                ],
                ];
            })
            ->filter(fn ($role) => !empty($role['id']))
            ->unique('id')
            ->values();
    @endphp

    @foreach($removeRows as $i => $row)
        <tr>
            <td class="border border-gray-300 px-3 py-3">
                <select
                    name="remove_changes[{{ $i }}][role_id]"
                    class="remove-role-select w-full rounded-md border border-gray-300 px-2 py-2 text-sm"
                >
                    <option value="">削除する役職</option>
                    @foreach($initialRemoveRoleOptions as $role)
                        <option
                            value="{{ $role['id'] }}"
                            {{ (string)($row['role_id'] ?? '') === (string)$role['id'] ? 'selected' : '' }}
                        >
                            {{ $role['name'] }}
                        </option>
                    @endforeach
                </select>
            </td>

            <td class="border border-gray-300 px-3 py-3">
                <input
                    type="number"
                    name="remove_changes[{{ $i }}][count]"
                    class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm"
                    placeholder="人数"
                    min="1"
                    value="{{ $row['count'] ?? '' }}"
                >
            </td>

            <td class="border border-gray-300 px-3 py-3">
                <button
                    type="button"
                    class="remove-remove-change-row rounded-md border border-red-300 px-3 py-2 text-xs text-red-600 hover:bg-red-50"
                >
                    削除
                </button>
            </td>
        </tr>
    @endforeach
</tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-center">
            <button
                type="button"
                id="add-remove-change-row"
                class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
            >
                ＋ 削除役職行を追加
            </button>
        </div>
    </div>
</div>

            {{-- 戦績の登録 --}}
            <section>
                <h3 class="mb-4 text-center text-2xl font-bold">戦績の登録</h3>

                <div class="rounded-xl border border-gray-300 max-h-[600px] overflow-y-auto overflow-x-auto">
                    <table class="w-full min-w-[2000px] table-fixed border-collapse text-sm">
                        <thead class="bg-gray-50 text-center">
                            <tr>
                                <th class="border border-gray-300 px-3 py-3 w-56 sticky left-0 bg-white z-20 sticky top-0 left-0 z-30 bg-gray-100 px-4 py-2 border">メンバー＼試合数</th>

                                @for ($match = 1; $match <= 16; $match++)
                                    <th class="sticky top-0 z-20 bg-gray-100 border border-gray-300 px-3 py-3">
                                        第{{ $match }}試合
                                    </th>
                                @endfor
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($selectedMembers as $memberIndex => $member)
                                <tr>
                                    <td class="border border-gray-300 px-3 py-3 font-medium sticky left-0 bg-white z-10">
                                        {{ $member->name }}
                                    </td>

                                    @for ($match = 1; $match <= 16; $match++)
                                        @php
                                            $savedRoleId = old(
                                                "matches.$match.results.$memberIndex.role_id",
                                                $savedMatches[$match][$member->id]['role_id'] ?? ''
                                            );

                                            $savedResult = old(
                                                "matches.$match.results.$memberIndex.result",
                                                $savedMatches[$match][$member->id]['result'] ?? ''
                                            );
                                        @endphp

                                        <td class="border border-gray-300 px-3 py-3 align-top">
                                            <input
                                                type="hidden"
                                                name="matches[{{ $match }}][results][{{ $memberIndex }}][member_id]"
                                                value="{{ $member->id }}"
                                            >

                                            <div class="flex flex-col gap-2">
                                                <select
                                                    name="matches[{{ $match }}][results][{{ $memberIndex }}][role_id]"
                                                    class="match-role-select w-full rounded-md border border-gray-300 px-2 py-2 text-sm"
                                                >
                                                    <option value="">役職を選択</option>

                                                    @foreach($allRoles as $role)
                                                        <option value="{{ $role->id }}" {{ (string)$savedRoleId === (string)$role->id ? 'selected' : '' }}>
                                                            {{ $role->name }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <select
                                                    name="matches[{{ $match }}][results][{{ $memberIndex }}][result]"
                                                    class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm"
                                                >
                                                    <option value="">結果を選択</option>
                                                    <option value="win" {{ $savedResult === 'win' ? 'selected' : '' }}>win</option>
                                                    <option value="lose" {{ $savedResult === 'lose' ? 'selected' : '' }}>lose</option>
                                                </select>
                                            </div>
                                        </td>
                                    @endfor
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="border border-gray-300 px-3 py-6 text-center text-gray-500">
                                        上で選択された参加メンバーがいません。
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- ボタン --}}
            <div class="flex items-center justify-between pt-6">
                <a href="{{ route('admin.stats.amongus.calendar') }}"
                    class="inline-flex h-14 min-w-[110px] items-center justify-center rounded-full border-2 border-gray-400 px-6 text-sm font-medium text-gray-700 hover:bg-gray-100">
                    戻る
                </a>

                <input type="hidden" name="submit_action" id="submit_action" value="save">

<div class="mt-8 flex justify-center gap-4">
    <button
        type="submit"
        onclick="document.getElementById('submit_action').value='save'"
        class="rounded-lg bg-gray-800 px-6 py-3 text-sm font-medium text-white hover:bg-gray-700"
    >
        保存する
    </button>

    <button
        type="submit"
        onclick="document.getElementById('submit_action').value='complete'"
        class="rounded-lg bg-green-600 px-6 py-3 text-sm font-medium text-white hover:bg-green-500"
    >
        完成する
    </button>
</div>
            </div>
        </form>
    </div>
</div>
@endsection

<script>
/* ===============================
   ▼ 状態管理
   =============================== */
let regulationState = {
    baseRoles: [],
    changeMatch: null
};

/* ===============================
   ▼ 初期レギュレーション取得
   =============================== */
function getBaseRoles() {
    const rows = document.querySelectorAll('#regulation-rows tr');
    const roles = [];

    rows.forEach(row => {
        const selects = row.querySelectorAll('select');
        const inputs = row.querySelectorAll('input');

        // クルー
        if (selects[0]?.value && inputs[0]?.value) {
            roles.push({
                id: String(selects[0].value),
                name: selects[0].selectedOptions[0].text
            });
        }

        // インポスター
        if (selects[1]?.value && inputs[1]?.value) {
            roles.push({
                id: String(selects[1].value),
                name: selects[1].selectedOptions[0].text
            });
        }

        // 第三陣営
        if (selects[2]?.value && inputs[2]?.value) {
            roles.push({
                id: String(selects[2].value),
                name: selects[2].selectedOptions[0].text
            });
        }
    });

    return roles;
}

/* ===============================
   ▼ 変更役職取得（追加・削除）
   =============================== */
function getChangedRoles() {
    const addRows = document.querySelectorAll('#add-change-rows tr');
    const removeRows = document.querySelectorAll('#remove-change-rows tr');

    const added = [];
    const removed = [];

    addRows.forEach(row => {
        const select = row.querySelector('select');
        const input = row.querySelector('input');

        if (select?.value && input?.value) {
            added.push({
                id: String(select.value),
                name: select.selectedOptions[0].text
            });
        }
    });

    removeRows.forEach(row => {
        const select = row.querySelector('select');

        if (select?.value) {
            removed.push(String(select.value));
        }
    });

    return { added, removed };
}

/* ===============================
   ▼ 変更開始試合取得
   =============================== */
function getChangeMatch() {
    const checkedRadio = document.querySelector('input[name="regulation_change"]:checked');
    const matchSelect = document.querySelector('select[name="regulation_change_match_number"]');

    if (!checkedRadio || checkedRadio.value !== 'changed') {
        return null;
    }

    if (!matchSelect || !matchSelect.value) {
        return null;
    }

    return Number(matchSelect.value);
}


/* ===============================
   ▼ 変更後レギュレーション生成
   =============================== */
function getChangedFinalRoles() {
    const { added, removed } = getChangedRoles();

    let roles = [...regulationState.baseRoles];

    // 削除
    roles = roles.filter(role => !removed.includes(String(role.id)));

    // 追加
    added.forEach(role => {
        roles.push(role);
    });

    return roles;
}

/* ===============================
   ▼ 試合ごとの役職一覧を返す
   =============================== */
function getRolesForMatch(matchNumber) {
    const changeMatch = regulationState.changeMatch;

    if (changeMatch !== null && Number(matchNumber) >= changeMatch) {
        return getChangedFinalRoles();
    }

    return regulationState.baseRoles;
}

/* ===============================
   ▼ 1つのselectだけ更新
   =============================== */
function updateSingleMatchRoleSelect(select) {
    const matchCell = select.closest('td');
    if (!matchCell) return;

    const hiddenInput = matchCell.querySelector('input[type="hidden"][name*="[member_id]"]');
    if (!hiddenInput) return;

    const matchName = hiddenInput.name;
    const matchNumberMatch = matchName.match(/^matches\[(\d+)\]/);

    if (!matchNumberMatch) return;

    const matchNumber = Number(matchNumberMatch[1]);
    const roles = getRolesForMatch(matchNumber);
    const currentValue = select.value;

    select.innerHTML = '<option value="">役職を選択</option>';

    roles.forEach(role => {
        const option = document.createElement('option');
        option.value = role.id;
        option.textContent = role.name;

        if (String(currentValue) === String(role.id)) {
            option.selected = true;
        }

        select.appendChild(option);
    });

    // 今の選択が候補から消えたら空にする
    const exists = roles.some(role => String(role.id) === String(currentValue));
    if (!exists) {
        select.value = '';
    }
}

/* ===============================
   ▼ 戦績テーブル更新
   =============================== */
function applyRolesToMatchTable() {
    document.querySelectorAll('.match-role-select').forEach(select => {
        const matchCell = select.closest('td');
        if (!matchCell) return;

        const hiddenInput = matchCell.querySelector('input[type="hidden"][name*="[member_id]"]');
        if (!hiddenInput) return;

        const matchNumberMatch = hiddenInput.name.match(/^matches\[(\d+)\]/);
        if (!matchNumberMatch) return;

        const matchNumber = Number(matchNumberMatch[1]);
        const roles = getRolesForMatch(matchNumber);

        const currentValue = select.value;
        const currentText = select.options[select.selectedIndex]?.text;

        let html = '<option value="">役職を選択</option>';

        roles.forEach(role => {
            const selected = String(currentValue) === String(role.id) ? 'selected' : '';
            html += `<option value="${role.id}" ${selected}>${role.name}</option>`;
        });

        // 変更前の過去役職も消さない
        if (currentValue && !roles.find(role => String(role.id) === String(currentValue))) {
            html += `<option value="${currentValue}" selected>${currentText}</option>`;
        }

        select.innerHTML = html;

        if (currentValue) {
            select.value = currentValue;
        }
    });
}

/* ===============================
   ▼ 削除候補更新
   ▼ 初期レギュレーション基準で更新
   =============================== */
function refreshRemoveOptions() {
    const roles = regulationState.baseRoles;

    document.querySelectorAll('.remove-role-select').forEach(select => {
        const currentValue = select.value;

        let html = '<option value="">削除する役職</option>';

        roles.forEach(role => {
            const selected = String(currentValue) === String(role.id) ? 'selected' : '';
            html += `<option value="${role.id}" ${selected}>${role.name}</option>`;
        });

        select.innerHTML = html;
    });
}

/* ===============================
   ▼ 途中変更ボックス表示切替
   =============================== */
function toggleChangedRegulationBox() {
    const checkedRadio = document.querySelector('input[name="regulation_change"]:checked');
    const box = document.getElementById('changed-regulation-box');

    if (!box) return;

    if (checkedRadio && checkedRadio.value === 'changed') {
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}

/* ===============================
   ▼ レギュレーション状態を保存
   =============================== */
function syncRegulationState() {
    regulationState.baseRoles = getBaseRoles();
    regulationState.changeMatch = getChangeMatch();
}

/* ===============================
   ▼ 全更新
   =============================== */
function refreshAll() {
    syncRegulationState();
    toggleChangedRegulationBox();
    refreshRemoveOptions();
    applyRolesToMatchTable();
}

// ===============================
// 🔥 参加者の重複チェック
// ===============================
document.querySelectorAll('select[name*="member_id"]').forEach(select => {
    select.addEventListener('change', function () {

        const allSelects = document.querySelectorAll('select[name*="member_id"]');

        const values = Array.from(allSelects)
            .map(s => s.value)
            .filter(v => v !== '');

        const duplicates = values.filter((v, i, arr) => arr.indexOf(v) !== i);

        if (duplicates.includes(this.value)) {
            alert('同じ参加者は選べません');
            this.value = '';
        }
    });
});

/* ===============================
   ▼ イベント
   =============================== */
document.addEventListener('DOMContentLoaded', () => {
    // 初期表示時は戦績表の役職selectを上書きしない
    + syncRegulationState();
    + toggleChangedRegulationBox();
    + refreshRemoveOptions();

    // 役職追加
const addRoleRowBtn = document.getElementById('add-role-row');

if (addRoleRowBtn) {
    addRoleRowBtn.addEventListener('click', function () {
        const regulationRows = document.getElementById('regulation-rows');
        const firstRow = regulationRows.querySelector('tr');

        const newRow = firstRow.cloneNode(true);

        newRow.querySelectorAll('select').forEach(select => {
            select.value = '';
        });

        newRow.querySelectorAll('input').forEach(input => {
            input.value = '';
        });

        regulationRows.appendChild(newRow);
        renumberRegulationRows();
        syncRegulationState();
    });
}

function renumberRegulationRows() {
    document.querySelectorAll('#regulation-rows tr').forEach((row, index) => {
        row.querySelectorAll('select, input').forEach(input => {
            const name = input.getAttribute('name');
            if (!name) return;

            const newName = name.replace(/regulations\[\d+\]/, `regulations[${index}]`);
            input.setAttribute('name', newName);
        });
    });
}
    // レギュレーション確定ボタン
    const applyBtn = document.getElementById('apply-regulations');
    if (applyBtn) {
        applyBtn.addEventListener('click', () => {
            refreshAll();
        });
    }

    function renumberRegulationRows() {
    document.querySelectorAll('#regulation-rows tr').forEach((row, index) => {
        row.querySelectorAll('select, input').forEach(input => {
            input.name = input.name.replace(/regulations\[\d+\]/, `regulations[${index}]`);
        });
    });
    }

    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-regulation-row')) {
            e.target.closest('tr').remove();
            renumberRegulationRows();
            syncRegulationState();
        }
    });

    // ラジオ切替
    document.querySelectorAll('input[name="regulation_change"]').forEach(radio => {
        radio.addEventListener('change', () => {
            refreshAll();
        });
    });

    // 変更開始試合
    const changeMatchSelect = document.querySelector('select[name="regulation_change_match_number"]');
    if (changeMatchSelect) {
        changeMatchSelect.addEventListener('change', () => {
            refreshAll();
        });
    }

    // 通常レギュレーション変更
    document.addEventListener('change', (e) => {
        if (e.target.closest('#regulation-rows')) {
            refreshAll();
        }
    });

    // 追加・削除変更
    document.addEventListener('change', (e) => {
        if (
            e.target.closest('#add-change-rows') ||
            e.target.closest('#remove-change-rows')
        ) {
            refreshAll();
        }
    });
});

/* ===============================
   ▼ デバッグ
   =============================== */
window.debugRoles = () => {
    console.log('baseRoles:', regulationState.baseRoles);
    console.log('changeMatch:', regulationState.changeMatch);
    console.log('changedRoles:', getChangedFinalRoles());

    for (let i = 1; i <= 10; i++) {
        console.log(`match ${i}:`, getRolesForMatch(i));
    }
};


</script>