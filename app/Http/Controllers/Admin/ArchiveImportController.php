<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchiveSection;
use App\Models\Game;
use App\Models\Member;
use App\Services\Archive\ArchiveImportOrchestrator;
use App\Services\Archive\SectionTimeWindows;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * 「YouTube/Twitch/ツイキャス → スプシに一時保存 → 管理画面で確認 → DB保存 or 再取得」の管理画面側。
 * 配信アーカイブのデータ取得.drawio の全体フローに対応する。
 */
class ArchiveImportController extends Controller
{
    /**
     * admin/archives編集画面の表記に合わせたsection_typeラベル。
     */
    private const SECTION_TYPE_LABELS = [
        'pre' => '0次会',
        'primary' => '1次会',
        'secondary' => '2次会',
        'third' => '3次会',
        'fourth' => '4次会',
    ];

    public function index()
    {
        $sections = ArchiveSection::query()
            ->with(['onedayarchive', 'members'])
            ->get()
            ->filter(fn (ArchiveSection $section) => $section->onedayarchive && $section->members->isNotEmpty())
            ->sortByDesc(fn (ArchiveSection $section) => $section->onedayarchive->event_date)
            ->values();

        $members = Member::orderBy('name')->get(['id', 'name']);
        $games = Game::orderBy('name')->pluck('name');

        return view('admin.archive-import.index', [
            'sections' => $sections,
            'sectionTypeLabels' => self::SECTION_TYPE_LABELS,
            'members' => $members,
            'games' => $games,
        ]);
    }

    /**
     * 指定したセクション（例: 8/3の1次会）の参加者だけに絞って取得する。
     * 深夜またぎのセクションも拾えるよう、取得期間はその日〜翌日までとする。
     * カレンダー未登録の日は、section_idの代わりにdate + member_idsで直接指定する。
     */
    public function fetch(Request $request, ArchiveImportOrchestrator $orchestrator)
    {
        $validated = $request->validate([
            'section_id' => ['nullable', 'integer', 'exists:archive_sections,id'],
            'date' => ['nullable', 'date'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
            'game' => ['nullable', 'string', 'max:255'],
        ]);

        $timezone = config('app.timezone', 'Asia/Tokyo');
        $section = null;

        if (!empty($validated['section_id'])) {
            $section = ArchiveSection::with(['onedayarchive', 'members'])->findOrFail($validated['section_id']);

            if (!$section->onedayarchive) {
                return back()->withErrors(['section_id' => 'このセクションには開催日が設定されていません。']);
            }

            $eventDate = $section->onedayarchive->event_date;
            $members = $section->members;
        } elseif (!empty($validated['date']) && !empty($validated['member_ids'])) {
            $eventDate = $validated['date'];
            $members = Member::whereIn('id', $validated['member_ids'])->get();
        } else {
            return back()->withErrors(['section_id' => 'セクションを選ぶか、日付と参加者を指定してください。']);
        }

        $from = Carbon::parse($eventDate, $timezone)->startOfDay();
        // 4次会は翌朝8時頃まで続くことがあるため、深夜またぎ分だけ拾えるよう翌日9時までに留める。
        // 翌日いっぱい（24時まで）にすると、翌日の別セッションの配信まで巻き込んでしまうため。
        $to = $from->copy()->addDay()->setTime(9, 0);

        // セクション指定の取得は、既にそのセクションの参加者として確定しているメンバーに絞って
        // 検索している。よって、そのメンバー自身の投稿に「アルジャン」の文字列が無いだけの動画も、
        // セクションの時間帯・（保存済みなら）ゲームジャンルの一致で拾えるよう、補助検索を有効にする。
        $game = $validated['game'] ?? null;

        if (!$game && $section) {
            $game = $section->game_genre ?: null;
        }

        $result = $orchestrator->fetchAndStageForMembers(
            $members,
            $from,
            $to,
            $game,
            $section?->section_type,
            $section ? $from->copy() : null
        );

        // 誤った保存先セクションへの一括保存を防ぐため、取得時に絞り込んだセクションを
        // 確認画面の「一括保存」プルダウンの初期選択にも引き継ぐ。
        $result['section_id'] = $section?->id;

        return redirect()
            ->route('admin.archive-import.review')
            ->with('fetch_result', $result);
    }

    /**
     * スプシに溜まっている候補行を確認する画面。
     */
    public function review(ArchiveImportOrchestrator $orchestrator)
    {
        $sections = ArchiveSection::query()
            ->with('onedayarchive')
            ->get()
            ->filter(fn (ArchiveSection $section) => $section->onedayarchive !== null);

        $timezone = config('app.timezone', 'Asia/Tokyo');

        $rows = $orchestrator->reviewStaged()->map(function (array $row) use ($sections, $timezone) {
            $row['candidate_section_ids'] = $this->candidateSectionIdsForRow($row, $sections, $timezone);

            return $row;
        });

        // 一括保存の「保存先セクション」プルダウンは、全日程ではなく今回取得した日付
        // （深夜またぎを考慮して前日も含む）だけに絞る。取得自体が特定の日にスコープされて
        // いるのに、選択肢だけ全日程分並ぶとスクロールが大変なため。
        $relevantDates = $rows->flatMap(function (array $row) {
            if (empty($row['date'])) {
                return [];
            }

            $date = Carbon::parse($row['date']);

            return [$date->toDateString(), $date->copy()->subDay()->toDateString()];
        })->unique();

        $bulkSections = $relevantDates->isEmpty()
            ? $sections
            : $sections->filter(
                fn (ArchiveSection $section) => $relevantDates->contains(
                    Carbon::parse($section->onedayarchive->event_date)->toDateString()
                )
            );

        return view('admin.archive-import.review', [
            'rows' => $rows,
            'sections' => $sections,
            'bulkSections' => $bulkSections,
            'sectionTypeLabels' => self::SECTION_TYPE_LABELS,
        ]);
    }

    /**
     * ある候補行が、どのセクションのvideo_urlになり得るかを判定する。
     * 配信開始時刻が分かる場合は、共通ルール(SectionTimeWindows)で時間帯まで絞り込む
     * （深夜またぎで配信日の翌日側に日付がずれるケースも、前日のセクションを候補に含めて判定する）。
     * 時間帯で1件も絞り込めなかった場合は、判定不能とみなして同日の全セクションを候補として残す。
     */
    private function candidateSectionIdsForRow(array $row, Collection $sections, string $timezone): array
    {
        $sameDaySectionIds = fn () => $sections->filter(
            fn (ArchiveSection $section) => Carbon::parse($section->onedayarchive->event_date)->toDateString() === $row['date']
        )->pluck('id')->all();

        if (empty($row['published_at'])) {
            return $sameDaySectionIds();
        }

        $publishedAt = Carbon::parse($row['published_at'], $timezone);
        $durationSeconds = $row['duration_seconds'] !== '' ? (int) $row['duration_seconds'] : null;
        $endedAt = $durationSeconds ? $publishedAt->copy()->addSeconds($durationSeconds) : $publishedAt;

        $matches = $sections->filter(function (ArchiveSection $section) use ($row, $publishedAt, $endedAt, $timezone) {
            $eventDate = Carbon::parse($section->onedayarchive->event_date)->toDateString();

            // 4次会等は翌朝まで続き配信の日付が1日ずれることがあるため、
            // 「同日」と「配信日の前日」のセクションを候補に含めた上で時間帯の重なりを見る。
            if ($eventDate !== $row['date'] && $eventDate !== Carbon::parse($row['date'])->subDay()->toDateString()) {
                return false;
            }

            $dayStart = Carbon::parse($section->onedayarchive->event_date, $timezone)->startOfDay();

            return SectionTimeWindows::overlapsWindow($section->section_type, $dayStart, $publishedAt, $endedAt);
        });

        return $matches->isNotEmpty() ? $matches->pluck('id')->all() : $sameDaySectionIds();
    }

    /**
     * 確認画面から「保存」: 指定したスプシ行を指定セクション・メンバーのvideo_urlとして確定する。
     */
    public function commit(Request $request, ArchiveImportOrchestrator $orchestrator)
    {
        $validated = $request->validate([
            'sheet_row' => ['required', 'integer'],
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'section_id' => ['required', 'integer', 'exists:archive_sections,id'],
        ]);

        $orchestrator->commitRow(
            (int) $validated['sheet_row'],
            (int) $validated['member_id'],
            (int) $validated['section_id']
        );

        $eventDate = ArchiveSection::with('onedayarchive')->find($validated['section_id'])?->onedayarchive?->event_date;

        return redirect()
            ->route('admin.archive-import.review')
            ->with('success', 'DBに保存しました。')
            ->with('committed_date', $eventDate);
    }

    /**
     * 確認画面から「選択した行を一括保存」: チェックした複数行を、1つのセクションにまとめて確定する。
     */
    public function bulkCommit(Request $request, ArchiveImportOrchestrator $orchestrator)
    {
        $validated = $request->validate([
            'sheet_rows' => ['required', 'array', 'min:1'],
            'sheet_rows.*' => ['integer'],
            'section_id' => ['required', 'integer', 'exists:archive_sections,id'],
        ]);

        $result = $orchestrator->commitRows(
            array_map('intval', $validated['sheet_rows']),
            (int) $validated['section_id']
        );

        $message = "{$result['committed']}件をDBに保存しました。";

        if (!empty($result['errors'])) {
            $message .= ' (' . implode(' / ', $result['errors']) . ')';
        }

        $eventDate = ArchiveSection::with('onedayarchive')->find($validated['section_id'])?->onedayarchive?->event_date;

        return redirect()
            ->route('admin.archive-import.review')
            ->with('success', $message)
            ->with('committed_date', $eventDate);
    }

    /**
     * 確認画面から「削除」: 不要な候補行をスプシから取り除く。DBには保存しない。
     */
    public function discard(Request $request, ArchiveImportOrchestrator $orchestrator)
    {
        $validated = $request->validate([
            'sheet_row' => ['required', 'integer'],
        ]);

        $orchestrator->discardRow((int) $validated['sheet_row']);

        return redirect()
            ->route('admin.archive-import.review')
            ->with('success', '候補から削除しました。');
    }
}
