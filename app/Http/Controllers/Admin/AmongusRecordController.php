<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchiveSection;
use App\Models\Member;
use App\Models\Onedayarchive;
use App\Models\Role;
use App\Models\AmongusRecord;
use App\Models\AmongusMatch;
use App\Models\AmongusMatchMemberResult;
use App\Models\AmongusRegulation;
use App\Models\AmongusRegulationChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AmongusRecordController extends Controller
{
    public function create($date)
{
    // =========================
    // 1日分のデータ取得
    // =========================
    $onedayarchive = Onedayarchive::where('event_date', $date)->firstOrFail();

    $section = ArchiveSection::with('members')
        ->where('onedayarchive_id', $onedayarchive->id)
        ->whereRaw('LOWER(game_genre) = ?', ['among us'])
        ->first();

        

    $members = Member::orderBy('name')->get();

    $record = $section
    ? AmongusRecord::with('members')
        ->where('archive_section_id', $section->id)
        ->first()
    : null;

$recordMemberIds = $record
    ? $record->members()->pluck('members.id')->values()->toArray()
    : [];

$sectionMemberIds = $section
    ? $section->members()->pluck('members.id')->values()->toArray()
    : [];

$selectedMemberIds = !empty($recordMemberIds)
    ? $recordMemberIds
    : $sectionMemberIds;

    $selectedMembers = $members->whereIn('id', $selectedMemberIds)->values();


    // =========================
    // 役職
    // =========================
    $crewRoles = Role::where('type', 'crew')->orderBy('name')->get();
    $impostorRoles = Role::where('type', 'impostor')->orderBy('name')->get();
    $neutralRoles = Role::where('type', 'neutral')->orderBy('name')->get();
    $allRoles = Role::orderBy('name')->get();

    // =========================
    // record
    // =========================
    $record = AmongusRecord::with(['regulations', 'regulationChanges'])
        ->where('archive_section_id', $section?->id)
        ->first();

    // =========================
    // 試合
    // =========================
    $savedMatches = [];

    if ($record) {
        $matches = AmongusMatch::with(['memberResults'])
            ->where('amongus_record_id', $record->id)
            ->orderBy('match_number')
            ->get();

        foreach ($matches as $match) {
            foreach ($match->memberResults as $result) {
                $savedMatches[$match->match_number][$result->member_id] = [
                    'role_id' => $result->role_id,
                    'result' => $result->result,
                ];
            }
        }
    }

    // =========================
    // 通常レギュ
    // =========================
    $savedRegulations = $record
        ? $record->regulations
            ->where('phase', 'normal')
            ->values()
            ->toArray()
        : [];

    // =========================
    // 変更レギュ
    // =========================
    $savedChangedRegulations = $record
        ? $record->regulations
            ->where('phase', 'changed')
            ->values()
            ->toArray()
        : [];

    // =========================
    // 差分
    // =========================
    $savedAddChanges = $record
        ? $record->regulationChanges
            ->where('action', 'add')
            ->values()
            ->toArray()
        : [];

    $savedRemoveChanges = $record
        ? $record->regulationChanges
            ->where('action', 'remove')
            ->values()
            ->toArray()
        : [];

    // =========================
    // その他
    // =========================
    $savedRegulationChange = $record?->regulation_change ?? 'none';
    $savedRegulationChangeMatchNumber = $record?->regulation_change_match_number;


    return view('admin.stats.amongus.create', compact(
        'date',
        'onedayarchive',
        'section',
        'members',
        'selectedMemberIds',
        'selectedMembers',
        'crewRoles',
        'impostorRoles',
        'neutralRoles',
        'allRoles',
        'savedMatches',
        'savedRegulations',
        'savedChangedRegulations',
        'savedAddChanges',
        'savedRemoveChanges',
        'savedRegulationChange',
        'savedRegulationChangeMatchNumber'
    ));
}
    public function store(Request $request, $date)
    {
        
        $validated = $request->validate([
        'regulations' => ['nullable', 'array'],
        'regulations.*.crew_role_id' => ['nullable', 'integer', 'exists:roles,id'],
        'regulations.*.crew_count' => ['nullable', 'integer', 'min:0'],
        'regulations.*.impostor_role_id' => ['nullable', 'integer', 'exists:roles,id'],
        'regulations.*.impostor_count' => ['nullable', 'integer', 'min:0'],
        'regulations.*.neutral_role_id' => ['nullable', 'integer', 'exists:roles,id'],
        'regulations.*.neutral_count' => ['nullable', 'integer', 'min:0'],

        'regulation_change' => ['required', 'in:none,changed'],
        'regulation_change_match_number' => ['nullable', 'integer', 'min:1', 'max:10'],

        'add_changes' => ['nullable', 'array'],
        'add_changes.*.role_id' => ['nullable', 'integer', 'exists:roles,id'],
        'add_changes.*.count' => ['nullable', 'integer', 'min:1'],

        'remove_changes' => ['nullable', 'array'],
        'remove_changes.*.role_id' => ['nullable', 'integer', 'exists:roles,id'],
        'remove_changes.*.count' => ['nullable', 'integer', 'min:1'],

        'matches' => ['nullable', 'array'],
        'matches.*.results' => ['nullable', 'array'],
        'matches.*.results.*.member_id' => ['required', 'integer', 'exists:members,id'],
        'matches.*.results.*.role_id' => ['nullable', 'integer', 'exists:roles,id'],
        'matches.*.results.*.result' => ['nullable', 'in:win,lose'],
        'submit_action' => ['required', 'in:save,complete'],
        'member_ids' => ['nullable', 'array'],
        'member_ids.*' => ['nullable', 'integer', 'exists:members,id'],
    ]);


        // 日付から1日分のアーカイブを取得する
        $onedayarchive = Onedayarchive::where('event_date', $date)->firstOrFail();

        // Among Us セクションを取得する
        $section = ArchiveSection::where('onedayarchive_id', $onedayarchive->id)
            ->where('game_genre', 'among us')
            ->firstOrFail();


        DB::transaction(function () use ($section, $validated, $date) {
    // 親データ作成
    $record = AmongusRecord::firstOrCreate([
        'archive_section_id' => $section->id,
    ]);

    // 変更有無と変更開始試合を保存と完了状態の表示
    $record->update([
        'regulation_change' => $validated['regulation_change'],
        'regulation_change_match_number' => $validated['regulation_change'] === 'changed'
            ? ($validated['regulation_change_match_number'] ?? null)
            : null,

        'is_completed' => $validated['submit_action'] === 'complete',
    ]);

    $memberIds = collect($validated['member_ids'] ?? [])
        ->filter()
        ->unique()
        ->values()
        ->toArray();

    $record->members()->sync($memberIds);

    // 既存の試合結果を削除
    $record->matches()->delete();

    // 既存の通常レギュレーションを削除
    $record->regulations()->delete();

    // 既存の差分レギュレーションを削除
    $record->regulationChanges()->delete();

    // -------------------------
    // 通常レギュレーション保存
    // -------------------------
    foreach (($validated['regulations'] ?? []) as $row) {
        if ($this->isEmptyRegulationRow($row)) {
            continue;
        }

        AmongusRegulation::create([
            'amongus_record_id' => $record->id,
            'phase' => 'normal',
            'crew_role_id' => $row['crew_role_id'] ?? null,
            'crew_count' => $row['crew_count'] ?? null,
            'impostor_role_id' => $row['impostor_role_id'] ?? null,
            'impostor_count' => $row['impostor_count'] ?? null,
            'neutral_role_id' => $row['neutral_role_id'] ?? null,
            'neutral_count' => $row['neutral_count'] ?? null,
        ]);
    }

    // -------------------------
    // 差分保存（追加）
    // -------------------------
    if (($validated['regulation_change'] ?? 'none') === 'changed') {
        foreach (($validated['add_changes'] ?? []) as $row) {
            if (empty($row['role_id']) || empty($row['count'])) {
                continue;
            }

            AmongusRegulationChange::create([
                'amongus_record_id' => $record->id,
                'action' => 'add',
                'role_id' => $row['role_id'],
                'count' => $row['count'],
            ]);
        }

        // -------------------------
        // 差分保存（削除）
        // -------------------------
        foreach (($validated['remove_changes'] ?? []) as $row) {
            if (empty($row['role_id']) || empty($row['count'])) {
                continue;
            }

            AmongusRegulationChange::create([
                'amongus_record_id' => $record->id,
                'action' => 'remove',
                'role_id' => $row['role_id'],
                'count' => $row['count'],
            ]);
        }
    }

    // -------------------------
    // 試合結果保存
    // -------------------------
    foreach (($validated['matches'] ?? []) as $matchNumber => $matchData) {
        $filledResults = collect($matchData['results'] ?? [])
            ->filter(function ($result) {
                return !empty($result['role_id']) && !empty($result['result']);
            })
            ->values();

        if ($filledResults->isEmpty()) {
            continue;
        }

        $match = AmongusMatch::create([
            'amongus_record_id' => $record->id,
            'match_number' => $matchNumber,
        ]);

        foreach ($filledResults as $result) {
            AmongusMatchMemberResult::create([
                'amongus_match_id' => $match->id,
                'member_id' => $result['member_id'],
                'role_id' => $result['role_id'],
                'result' => $result['result'],
            ]);
        }
    }
});

        return redirect()
            ->route('admin.stats.amongus.create', ['date' => $date])
            ->with('success', '戦績を保存しました。');
    }

    private function isEmptyRegulationRow(array $row): bool
    {
        return empty($row['crew_role_id'])
            && empty($row['crew_count'])
            && empty($row['impostor_role_id'])
            && empty($row['impostor_count'])
            && empty($row['neutral_role_id'])
            && empty($row['neutral_count']);
    }
}