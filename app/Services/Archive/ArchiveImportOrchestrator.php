<?php

namespace App\Services\Archive;

use App\Models\ArchiveSection;
use App\Models\Member;
use App\Services\Archive\Contracts\PlatformArchiveFetcher;
use App\Services\GameGenreInferenceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * 「YouTube/Twitch/ツイキャス → スプシに一時保存 → 管理画面で確認 → DB保存 or 再取得」
 * の一連の流れを取りまとめる。個々のプラットフォーム取得はPlatformArchiveFetcher実装に委譲する。
 */
class ArchiveImportOrchestrator
{
    /**
     * 参加者一覧との名前一致で「本人確認」とみなすために必要な最低人数。
     * セクション登録自体に必要な「メインメンバー2人以上」ルールに合わせている。
     */
    private const MIN_ROSTER_NAME_MATCHES = 2;

    /**
     * @param PlatformArchiveFetcher[] $fetchers
     */
    public function __construct(
        private readonly array $fetchers,
        private readonly GoogleSheetsStagingService $staging,
        private readonly GameGenreInferenceService $gameGenreInference,
        private readonly ParticipantNameExtractor $nameExtractor,
    ) {
    }

    /**
     * 指定したメンバー一覧（例: あるセクションの参加者）に絞って候補を取得し、スプシに一時保存する。
     * 「1日分・その日の参加者だけ」のようにピンポイントで取得したい場合に使う。
     *
     * $gameを指定した場合、あるメンバーの候補が2件以上あるときだけタイトルからゲームジャンルを推定し
     * 絞り込む（1件しかない場合はゲーム名の記載有無に関わらずそのまま残す。タイトルにゲーム名を
     * 書かない配信者もいるため、候補が1件の時点で誤って弾いてしまわないようにする）。
     * 絞り込んだ結果0件になった場合も、判定不能とみなして元の候補をそのまま残す。
     *
     * $sectionType/$eventDayStartを指定した場合（＝既に確認済みのセクション参加者に対する取得）、
     * 通常の「アルジャンタグ必須」検索で1件も見つからなかったメンバーについてだけ、
     * そのセクションの時間帯に絞った「タグ不要」の補助検索を追加で行う。
     * 他メンバーの動画から参加者として抽出済み＝参加はほぼ確定しているとみなし、
     * 本人の投稿に「アルジャン」の文字列が無いだけの動画も、日付・配信時間帯・（分かれば）ゲーム
     * ジャンルの一致で拾えるようにするための補完（本人確認の代わりに間接的な参加者抽出結果を使う）。
     */
    public function fetchAndStageForMembers(
        Collection $members,
        Carbon $from,
        Carbon $to,
        ?string $game = null,
        ?string $sectionType = null,
        ?Carbon $eventDayStart = null
    ): array {
        $allRows = collect();
        $errors = [];

        foreach ($members as $targetMember) {
            foreach ($this->fetchers as $fetcher) {
                if (!$fetcher->supports($targetMember)) {
                    continue;
                }

                try {
                    $allRows = $allRows->merge($fetcher->fetchCandidates($targetMember, $from, $to));
                } catch (Throwable $exception) {
                    $errors[] = [
                        'member' => $targetMember->name,
                        'platform' => $fetcher->platform(),
                        'message' => $exception->getMessage(),
                    ];
                }
            }
        }

        if ($sectionType && $eventDayStart && isset(SectionTimeWindows::WINDOWS_MINUTES[$sectionType])) {
            $allRows = $allRows->merge($this->fetchUntaggedFallback(
                $members,
                $allRows,
                $sectionType,
                $eventDayStart,
                $game
            ));
        }

        if ($game) {
            $allRows = $allRows
                ->groupBy('member_id')
                ->flatMap(function (Collection $memberRows) use ($game) {
                    if ($memberRows->count() <= 1) {
                        return $memberRows;
                    }

                    $filtered = $memberRows->filter(
                        fn (array $row) => $this->gameGenreInference->inferFromText($row['title'] ?? '') === $game
                    );

                    return $filtered->isNotEmpty() ? $filtered : $memberRows;
                })
                ->values();
        }

        $fetchedCount = $allRows->count();

        if (!$this->staging->isConfigured()) {
            return [
                'staged' => 0,
                'fetched' => $fetchedCount,
                'errors' => $errors,
                'message' => 'Google Sheetsが未設定のため、スプシへの保存はスキップしました（取得自体は行いました）。',
            ];
        }

        // 「取得」を何度も押しても、既にスプシにある候補（platform+video_idが同じ）は重複追加しない。
        $alreadyStaged = $this->staging->fetchStagedRows()
            ->map(fn (array $row) => $row['platform'] . ':' . $row['video_id'])
            ->flip();

        $newRows = $allRows->reject(
            fn (array $row) => $alreadyStaged->has($row['platform'] . ':' . $row['video_id'])
        )->values();

        $this->staging->appendRows($newRows);

        return [
            'staged' => $newRows->count(),
            'fetched' => $fetchedCount,
            'errors' => $errors,
            'message' => 'ok',
        ];
    }

    /**
     * アルジャンタグ必須の検索で「対象セクションの時間帯に」1件も見つからなかったメンバーだけを対象に、
     * セクションの時間帯に絞った「タグ不要」の補助検索を行う。
     * タグ必須検索は1日分まるごと（深夜またぎ対応のため）検索しているので、そのメンバーが
     * 別セクション（例: 1次会）のタグ付き動画を持っているだけでは対象から除外しない
     * （そのままだと、他セクションでたまたまタグ付き投稿がある人だけ補助検索が発動しなくなる）。
     * タグという最有力の裏付けが無い分、ゲームジャンルが一致する場合のみ、
     * またはその時間帯の投稿がその1件しか無い場合のみ採用し、誤爆を避ける。
     * ここで扱う$membersは常に「既に確定済みのセクション参加者ロースター」（section->members等）で、
     * 他メンバーの動画からの名前抽出等で既に参加が裏付けられている前提のため、Among Usのような
     * 汎用ゲームでも対象外にはしない（対象外にすると、ロースター確定済みなのに本人の動画だけ
     * 見つからないという逆効果になる）。無関係な配信との混同リスクは、まだ参加者として未確定な
     * メンバー全員を対象にする ParticipantComputationService::addTimeWindowGenreMatches 側でのみ
     * Among Usを除外することで抑えている。
     */
    private function fetchUntaggedFallback(
        Collection $members,
        Collection $existingRows,
        string $sectionType,
        Carbon $eventDayStart,
        ?string $game
    ): Collection {
        [$startMinutes, $endMinutes] = SectionTimeWindows::WINDOWS_MINUTES[$sectionType];
        $windowFrom = $eventDayStart->copy()->addMinutes($startMinutes);
        $windowTo = $eventDayStart->copy()->addMinutes($endMinutes);

        $membersWithCandidates = $existingRows
            ->filter(function (array $row) use ($windowFrom, $windowTo) {
                $publishedAt = $row['published_at'] ?? null;

                return $publishedAt && $publishedAt->between($windowFrom, $windowTo);
            })
            ->pluck('member_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        $fallbackRows = collect();

        foreach ($members as $targetMember) {
            if ($membersWithCandidates->contains($targetMember->id)) {
                continue;
            }

            $memberCandidates = collect();

            foreach ($this->fetchers as $fetcher) {
                if (!$fetcher->supports($targetMember)) {
                    continue;
                }

                try {
                    $memberCandidates = $memberCandidates->merge(
                        $fetcher->fetchCandidates($targetMember, $windowFrom, $windowTo, requireArujanTag: false)
                    );
                } catch (Throwable) {
                    // 補助検索の失敗はタグ必須検索の結果に影響させない（0件のままでよい）。
                }
            }

            if ($memberCandidates->isEmpty()) {
                continue;
            }

            // 概要欄の「参加者一覧」に、既にこのセクションの参加者と確定している他メンバーの名前が
            // 一定人数以上載っている場合、それ自体が強い裏付けになる（本人がタグを付け忘れていても、
            // 主催側などが書いた参加者一覧に名前が挙がっていれば、その配信の一部だとほぼ確定できる）。
            // ジャンルが汎用的で当てにならないAmong Us等でも、この照合だけは有効に使える。
            $namedMatched = $memberCandidates->filter(function (array $row) use ($members, $targetMember) {
                $text = ($row['title'] ?? '') . ' ' . DescriptionBoilerplateStripper::strip($row['description'] ?? '');
                $mentioned = $this->nameExtractor->extract($text, $members)
                    ->reject(fn (Member $member) => $member->id === $targetMember->id);

                return $mentioned->count() >= self::MIN_ROSTER_NAME_MATCHES;
            });

            if ($namedMatched->isNotEmpty()) {
                $fallbackRows = $fallbackRows->merge($namedMatched);
                continue;
            }

            if ($game) {
                $matched = $memberCandidates->filter(
                    fn (array $row) => $this->gameGenreInference->inferFromText($row['title'] ?? '', $row['description'] ?? '') === $game
                );

                if ($matched->isNotEmpty()) {
                    $fallbackRows = $fallbackRows->merge($matched);
                    continue;
                }
            }

            // ゲームジャンルで判定できない場合は、その時間帯の投稿がこの1件しか無い時だけ採用する
            // （タグも無くゲームも合致しない状態で複数候補があると誤爆リスクが高いため）。
            if ($memberCandidates->count() === 1) {
                $fallbackRows = $fallbackRows->merge($memberCandidates);
            }
        }

        return $fallbackRows;
    }

    /**
     * スプシに溜まっている候補行を読み込む（管理画面の確認用）。
     */
    public function reviewStaged(): Collection
    {
        return $this->staging->fetchStagedRows();
    }

    /**
     * 指定したスプシ行のURLを、指定ArchiveSection・メンバーのvideo_urlとして確定保存する。
     * 呼び出し側（管理画面）で「このスプシ行をどのセクションに保存するか」を決めてから呼ぶ。
     */
    public function commitRow(int $sheetRow, int $memberId, int $sectionId): void
    {
        $target = $this->staging->fetchStagedRows()->firstWhere('sheet_row', $sheetRow);

        if (!$target) {
            throw new RuntimeException("スプシ上に行が見つかりません: row={$sheetRow}");
        }

        $section = ArchiveSection::findOrFail($sectionId);
        $section->members()->syncWithoutDetaching([
            $memberId => ['video_url' => $target['url']],
        ]);

        $this->staging->deleteRow($sheetRow);
    }

    /**
     * commitRowの複数行版。指定した複数のスプシ行を、まとめて同じセクションのvideo_urlとして確定する
     * （行ごとのmember_idはスプシ上の値を使うので、複数メンバー分を一度に保存できる）。
     * 行削除は後続行の番号がずれるため、必ずsheet_rowの大きい方から処理する。
     */
    public function commitRows(array $sheetRows, int $sectionId): array
    {
        $staged = $this->staging->fetchStagedRows()->keyBy('sheet_row');
        $section = ArchiveSection::findOrFail($sectionId);

        $committed = 0;
        $errors = [];

        rsort($sheetRows);

        foreach ($sheetRows as $sheetRow) {
            $target = $staged->get($sheetRow);

            if (!$target) {
                $errors[] = "スプシ上に行が見つかりません: row={$sheetRow}";
                continue;
            }

            $section->members()->syncWithoutDetaching([
                (int) $target['member_id'] => ['video_url' => $target['url']],
            ]);

            $this->staging->deleteRow($sheetRow);
            $committed++;
        }

        return ['committed' => $committed, 'errors' => $errors];
    }

    /**
     * 「削除」: 候補として不要な行をスプシから取り除く。DBには何も保存しない。
     */
    public function discardRow(int $sheetRow): void
    {
        $this->staging->deleteRow($sheetRow);
    }
}
