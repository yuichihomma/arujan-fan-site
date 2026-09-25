<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchiveSection;
use App\Models\Game;
use App\Models\Member;
use App\Models\Onedayarchive;
use App\Services\Archive\ParticipantComputationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminSpecialArchiveController extends Controller
{
    public function index()
    {
        $sections = ArchiveSection::query()
            ->where('section_type', 'special')
            ->whereHas('members')
            ->with(['onedayarchive', 'members'])
            ->get()
            ->sortByDesc(fn (ArchiveSection $section) => $section->onedayarchive?->event_date)
            ->values();

        $members = Member::orderBy('name')->get();
        $games = Game::orderBy('name')->get();
        $registeredGameNamesForJs = $games->pluck('name')->values();

        $allMembersForJs = $members->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
        ])->values();

        return view('admin.archives.special', compact('sections', 'members', 'games', 'allMembersForJs', 'registeredGameNamesForJs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_date' => ['required', 'date'],
            'game_genre' => ['nullable', 'string', 'max:255'],
            'members' => ['nullable', 'array'],
            'members.*.selected' => ['nullable'],
            'members.*.video_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $date = $validated['event_date'];
        $archive = Onedayarchive::where('event_date', $date)->first();

        if ($archive && $archive->hasNonSpecialContent()) {
            throw ValidationException::withMessages([
                'event_date' => "{$date} は通常アーカイブとして既に登録されているため、特別回として登録できません。",
            ]);
        }

        $selectedIds = collect($validated['members'] ?? [])
            ->filter(fn (array $data) => !empty($data['selected']))
            ->keys();

        if ($selectedIds->isEmpty() || !Member::hasEnoughMainMembers($selectedIds)) {
            throw ValidationException::withMessages([
                'members' => 'アルジャンのメインメンバーが2人以上いないと登録できません。',
            ]);
        }

        if (!$archive) {
            $archive = Onedayarchive::create([
                'event_date' => $date,
                'official_title' => '',
                'official_video_url' => '',
                'thumbnail_url' => '',
                'description' => '',
            ]);
        }

        $section = ArchiveSection::firstOrCreate(
            ['onedayarchive_id' => $archive->id, 'section_type' => 'special'],
            ['section_title' => '特別回', 'game_genre' => '']
        );

        $section->update(['game_genre' => $validated['game_genre'] ?? '']);

        $memberData = collect($validated['members'] ?? [])
            ->filter(fn (array $data) => !empty($data['selected']))
            ->mapWithKeys(fn (array $data, $memberId) => [
                $memberId => [
                    'video_url' => $data['video_url'] ?? null,
                    'needs_review' => false,
                    'review_reason' => null,
                ],
            ])
            ->all();

        // 既存の参加者を消さないよう、追加・更新のみ行う（sync()は使わない）
        $section->members()->syncWithoutDetaching($memberData);

        return redirect()
            ->route('admin.archives.special.index')
            ->with('success', "{$date} の特別回を登録しました。");
    }

    public function update(Request $request, ArchiveSection $section)
    {
        if ($section->section_type !== 'special') {
            abort(404);
        }

        $validated = $request->validate([
            'game_genre' => ['nullable', 'string', 'max:255'],
            'members' => ['nullable', 'array'],
            'members.*.video_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $section->update(['game_genre' => $validated['game_genre'] ?? '']);

        foreach ($validated['members'] ?? [] as $memberId => $data) {
            $section->members()->updateExistingPivot($memberId, [
                'video_url' => $data['video_url'] ?? null,
                'needs_review' => false,
                'review_reason' => null,
            ]);
        }

        return redirect()
            ->route('admin.archives.special.index')
            ->with('success', '特別回を更新しました。');
    }

    /**
     * 「この特別回を削除」ボタン用。archive_section_memberは外部キーonDelete('cascade')
     * 済みのため、セクション削除だけで参加者の紐付けも自動的に消える。この日付に他の
     * セクションが一切残っていなければ、Onedayarchive自体も削除して完全に未登録へ戻す。
     */
    public function destroy(ArchiveSection $section)
    {
        if ($section->section_type !== 'special') {
            abort(404);
        }

        $archive = $section->onedayarchive;
        $section->delete();

        if ($archive && $archive->sections()->doesntExist()) {
            $archive->delete();
        }

        return redirect()
            ->route('admin.archives.special.index')
            ->with('success', '特別回を削除しました。');
    }

    /**
     * 新規登録フォーム（まだDB未保存）用の「この動画から参加者を抽出する」。
     * 保存済みArchiveSectionが無いため、日付＋ゲームジャンルだけで本人確認する点は
     * extractParticipantsFromVideo()と同じだが、こちらはDBへの保存は一切行わず、
     * 確認済み候補（members）のみを画面側の未保存フォーム状態に返す
     * （通常アーカイブ編集画面のextractParticipantsFromVideoと同じ「保存前の候補提示」の考え方）。
     */
    public function extractParticipantsForNewRegistration(Request $request, ParticipantComputationService $service)
    {
        $validated = $request->validate([
            'event_date' => ['required', 'date'],
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'video_url' => ['required', 'string', 'max:2048'],
            'game_genre' => ['nullable', 'string', 'max:255'],
            'existing_member_ids' => ['nullable', 'array'],
            'existing_member_ids.*' => ['integer'],
        ]);

        $timezone = config('app.timezone', 'Asia/Tokyo');
        $eventDayStart = Carbon::parse($validated['event_date'], $timezone)->startOfDay();

        $result = $service->extractParticipantsFromVideoUrl(
            $validated['video_url'],
            'special',
            $eventDayStart,
            (int) $validated['member_id'],
            array_map('intval', $validated['existing_member_ids'] ?? []),
            $validated['game_genre'] ?? null
        );

        if (isset($result['error'])) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json($result);
    }

    /**
     * 「この動画から参加者を抽出する」ボタン用。特別回は時間帯の定義が無いため、通常アーカイブ
     * 編集画面の同機能（時間帯重なりで本人確認）とは異なり、日付＋ゲームジャンル一致で本人確認する
     * （ParticipantComputationService::extractParticipantsFromVideoUrlの$gameGenre引数）。
     * 確認済み・要確認どちらの候補も、既存メンバーを消さずその場でDBへ追加する
     * （store()と同じsyncWithoutDetachingパターン）。
     */
    public function extractParticipantsFromVideo(Request $request, ArchiveSection $section, ParticipantComputationService $service)
    {
        if ($section->section_type !== 'special') {
            abort(404);
        }

        $validated = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'video_url' => ['required', 'string', 'max:2048'],
            'game_genre' => ['nullable', 'string', 'max:255'],
        ]);

        $timezone = config('app.timezone', 'Asia/Tokyo');
        $eventDayStart = Carbon::parse($section->onedayarchive->event_date, $timezone)->startOfDay();
        $existingMemberIds = $section->members()->pluck('members.id')->all();

        $result = $service->extractParticipantsFromVideoUrl(
            $validated['video_url'],
            $section->section_type,
            $eventDayStart,
            (int) $validated['member_id'],
            $existingMemberIds,
            $validated['game_genre'] ?? null
        );

        if (isset($result['error'])) {
            return response()->json(['message' => $result['message']], 422);
        }

        $reviewReason = '動画概要欄で検出されましたが、ゲームジャンル一致による本人確認ができませんでした。手動でURLを確認してください。';

        $memberData = collect($result['members'])->mapWithKeys(fn (array $member) => [
            $member['id'] => ['video_url' => $member['video_url'], 'needs_review' => false, 'review_reason' => null],
        ])->all();

        $memberData += collect($result['unverified_names'])->mapWithKeys(fn (array $member) => [
            $member['id'] => ['video_url' => null, 'needs_review' => true, 'review_reason' => $reviewReason],
        ])->all();

        if (!empty($memberData)) {
            $section->members()->syncWithoutDetaching($memberData);
        }

        return response()->json([
            'added' => count($result['members']),
            'needs_review' => count($result['unverified_names']),
        ]);
    }
}
