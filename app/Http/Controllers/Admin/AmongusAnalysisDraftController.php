<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmongusAnalysisDraft;
use App\Models\AmongusMatch;
use App\Models\AmongusMatchMemberResult;
use App\Models\AmongusRecord;
use App\Models\Member;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AmongusAnalysisDraftController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $drafts = AmongusAnalysisDraft::with([
            'archiveSection.onedayarchive',
            'archiveSection.members',
            'member',
        ])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderBy('archive_section_id')
            ->orderBy('match_number')
            ->paginate(50)
            ->withQueryString();

        return view('admin.stats.amongus.analysis-drafts.index', [
            'drafts' => $drafts,
            'status' => $status,
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function update(Request $request, AmongusAnalysisDraft $draft)
    {
        $validated = $request->validate([
            'match_number' => ['nullable', 'integer', 'min:1'],
            'video_timestamp_seconds' => ['nullable', 'integer', 'min:0'],
            'video_timestamp_label' => ['nullable', 'string', 'max:20'],
            'estimated_real_time' => ['nullable', 'date'],
            'member_name' => ['nullable', 'string', 'max:255'],
            'role_name' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', 'in:win,lose'],
            'win_side' => ['nullable', 'string', 'max:50'],
            'evidence_text' => ['nullable', 'string'],
            'confidence' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['required', 'in:pending,approved,rejected'],
            'memo' => ['nullable', 'string'],
        ]);

        $draft->update($validated);

        return back()->with('success', '下書きを更新しました。');
    }

    public function approve(AmongusAnalysisDraft $draft)
    {
        $draft->update(['status' => AmongusAnalysisDraft::STATUS_APPROVED]);

        return back()->with('success', '下書きを承認しました。');
    }

    public function reject(AmongusAnalysisDraft $draft)
    {
        $draft->update(['status' => AmongusAnalysisDraft::STATUS_REJECTED]);

        return back()->with('success', '下書きを不採用にしました。');
    }

    public function apply(Request $request)
    {
        $validated = $request->validate([
            'draft_ids' => ['required', 'array', 'min:1'],
            'draft_ids.*' => ['integer', 'exists:amongus_analysis_drafts,id'],
            'overwrite' => ['nullable', 'boolean'],
        ]);

        $drafts = AmongusAnalysisDraft::with('archiveSection')
            ->where('status', AmongusAnalysisDraft::STATUS_APPROVED)
            ->whereIn('id', $validated['draft_ids'])
            ->orderBy('archive_section_id')
            ->orderBy('match_number')
            ->get();

        $applied = 0;
        $skipped = 0;
        $warnings = [];

        DB::transaction(function () use ($drafts, $validated, &$applied, &$skipped, &$warnings) {
            foreach ($drafts as $draft) {
                if (!$draft->match_number || !$draft->member_name || !$draft->role_name || !$draft->result) {
                    $warnings[] = "下書きID {$draft->id}: 試合番号、参加者、役職、勝敗のいずれかが未入力です。";
                    $skipped++;
                    continue;
                }

                $member = Member::where('name', $draft->member_name)->first();
                $role = Role::where('name', $draft->role_name)->first();

                if (!$member || !$role) {
                    $warnings[] = "下書きID {$draft->id}: 参加者または役職をDBと照合できません。";
                    $skipped++;
                    continue;
                }

                $record = AmongusRecord::firstOrCreate([
                    'archive_section_id' => $draft->archive_section_id,
                ]);

                $record->members()->syncWithoutDetaching([$member->id]);

                $match = AmongusMatch::firstOrCreate([
                    'amongus_record_id' => $record->id,
                    'match_number' => $draft->match_number,
                ], [
                    'win_side' => $draft->win_side,
                ]);

                if ($draft->win_side && !$match->win_side) {
                    $match->update(['win_side' => $draft->win_side]);
                }

                $existing = AmongusMatchMemberResult::where('amongus_match_id', $match->id)
                    ->where('member_id', $member->id)
                    ->first();

                if ($existing && empty($validated['overwrite'])) {
                    $skipped++;
                    continue;
                }

                AmongusMatchMemberResult::updateOrCreate([
                    'amongus_match_id' => $match->id,
                    'member_id' => $member->id,
                ], [
                    'role_id' => $role->id,
                    'result' => $draft->result,
                ]);

                $applied++;
            }
        });

        return back()->with([
            'success' => "本登録しました。applied={$applied} skipped={$skipped}",
            'warnings' => $warnings,
        ]);
    }

    private function statusLabels(): array
    {
        return [
            AmongusAnalysisDraft::STATUS_PENDING => '未確認',
            AmongusAnalysisDraft::STATUS_APPROVED => '承認済み',
            AmongusAnalysisDraft::STATUS_REJECTED => '不採用',
        ];
    }
}
