<?php

namespace App\Services\Archive;

use App\Models\ArchiveSection;
use App\Models\ArchiveSectionAbsentMember;
use App\Models\Member;
use App\Services\Archive\Contracts\PlatformArchiveFetcher;
use App\Services\Archive\Contracts\SingleVideoLookupFetcher;
use App\Services\GameGenreInferenceService;
use App\Services\YoutubeMemberArchiveSyncService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 「カレンダーアーカイブ編集」画面の『ゲーム内容と参加メンバーを抽出する』ボタン用。
 * その日、アカウント登録済みの全メンバーの配信を横断的に検索し（アルジャンタグ付きのみ）、
 * タイトル・概要欄から参加者を検出、配信時刻からセクション(0次会〜4次会)を判定し、
 * セクションごとの参加者候補とゲームジャンル候補をまとめる。
 *
 * アルジャンタグ付きであっても、他メンバーとの合同セッションだという裏付け（本文への
 * 他メンバー名の記載、または同時間帯の複数メンバーによる独立投稿）が無い動画は、
 * ソロ配信とみなし参加者・ゲームジャンルどちらの判定からも除外する。
 */
class ParticipantComputationService
{
    /**
     * @param PlatformArchiveFetcher[] $fetchers
     */
    public function __construct(
        private readonly array $fetchers,
        private readonly ParticipantNameExtractor $nameExtractor,
        private readonly GameGenreInferenceService $gameGenreInference,
        private readonly YoutubeMemberArchiveSyncService $youtubeService,
    ) {
    }

    /**
     * @return array<string, array{genre: ?string, members: array<int, array{id:int, name:string}>}>
     */
    public function computeForDate(Carbon $eventDayStart): array
    {
        $to = $eventDayStart->copy()->addDay()->setTime(9, 0);

        $members = $this->membersWithAnyAccount();
        $allMembers = Member::all();

        $candidates = $this->fetchAllCandidates($members, $eventDayStart, $to);

        $sectionRows = [];
        $sectionUploaderIds = [];
        $sectionMemberIds = [];

        foreach ($candidates as $row) {
            $publishedAt = $row['published_at'];

            if (!$publishedAt) {
                continue;
            }

            // 配信開始時刻が属する1セクションだけを参加者・ゲームジャンル判定に使う。
            // 配信時間が延びて隣のセクションの時間帯にもかかっている場合でも、その延長分が
            // 「同じ顔ぶれでのアルジャン継続」とは限らない（配信者が一人になって別ゲームを
            // プレイしているだけ、等）ため、時間の重なりだけでは参加者を計上しない。
            $primaryType = SectionTimeWindows::windowContaining($eventDayStart, $publishedAt);

            if ($primaryType === null) {
                continue;
            }

            // 前日を基準にした判定でも別の区分に一致する場合は、前日の配信の続きである
            // 可能性が高いため、今日の0次会としては計上しない。
            if ($primaryType === 'pre') {
                $previousDayType = SectionTimeWindows::windowContaining($eventDayStart->copy()->subDay(), $publishedAt);

                if ($previousDayType !== null && $previousDayType !== 'pre') {
                    continue;
                }
            }

            $text = ($row['title'] ?? '') . ' ' . DescriptionBoilerplateStripper::strip($row['description'] ?? '');
            $detectedOthers = $this->nameExtractor->extract($text, $allMembers)
                ->reject(fn (Member $detectedMember) => $detectedMember->id === (int) $row['member_id']);

            $sectionRows[$primaryType][] = $row;
            $sectionUploaderIds[$primaryType][(int) $row['member_id']] = true;

            // 本文に他メンバーの名前が書かれている動画だけを、この時点で参加確定として扱う。
            // 自分の名前しか出てこない動画（＝タイトル・概要欄に「アルジャン」を含むだけの
            // ソロ配信）は、ここでは確定させず後段のクロスアップロード判定に委ねる。
            if ($detectedOthers->isNotEmpty()) {
                $sectionMemberIds[$primaryType][(int) $row['member_id']] = true;

                foreach ($detectedOthers as $detectedMember) {
                    $sectionMemberIds[$primaryType][$detectedMember->id] = true;
                }
            }
        }

        // 個人配信の除外: 同じ時間帯に「アルジャン」タグ付き動画をアップしたメンバーが
        // 自分1人だけで、かつ本文にも他メンバーの名前が一切無い場合は、コラボの証拠が無い
        // ソロ配信とみなし、参加者・ゲームジャンル判定のどちらからも除外する。
        // 逆に2人以上が同じ時間帯にそれぞれ独立して投稿していれば、名前の言及が無くても
        // 合同セッションだった強い証拠として扱い、全員を参加者として計上する。
        $sectionVideos = [];

        foreach ($sectionRows as $type => $rows) {
            $uploaderIds = array_keys($sectionUploaderIds[$type] ?? []);

            if (count($uploaderIds) >= 2) {
                foreach ($uploaderIds as $uploaderId) {
                    $sectionMemberIds[$type][$uploaderId] = true;
                }
            }

            $sectionVideos[$type] = array_values(array_filter(
                $rows,
                fn (array $row) => isset($sectionMemberIds[$type][(int) $row['member_id']])
            ));
        }

        // 2次会以降は「その区分の時間帯に開始したアルジャンタグ付き配信」が1本も無いことがある
        // （前の区分の配信をそのまま延長する人＋タグを書かない新規配信だけの回）。
        // 前の区分で参加確定済みのメンバーを起点に、延長配信（ルールA）とタグ無し新規配信（ルールB）で補完する。
        // 保存済みセクションの参加者（人間が確定させた分）も起点に含めることで、
        // 手動で参加者を登録した後の再抽出でもURL・ジャンルの補完が効くようにする。
        $savedMemberIdsByType = ArchiveSection::query()
            ->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', $eventDayStart->toDateString()))
            ->with('members:members.id')
            ->get()
            ->mapWithKeys(fn (ArchiveSection $section) => [
                $section->section_type => $section->members->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ]);

        $this->addContinuationCandidates($members, $eventDayStart, $to, $savedMemberIdsByType, $sectionMemberIds, $sectionVideos);

        $sectionGenres = [];

        foreach (array_keys(SectionTimeWindows::WINDOWS_MINUTES) as $type) {
            $sectionGenres[$type] = !empty($sectionVideos[$type])
                ? $this->gameGenreInference->inferFromVideos($sectionVideos[$type])
                : null;
        }

        // 他メンバーの動画に名前が一切書かれていないと、上記のクロスリファレンスだけでは検出できない
        // 参加者がいる（例: 各自が個別に自分のチャンネルへ投稿するゲームで、誰も参加者一覧を書かない場合）。
        // そのセクションのジャンルが既に確定している場合に限り、まだ未検出のメンバーについて、
        // 本人のアルジャンタグ無し投稿がその時間帯にあり、かつ同じゲームジャンルと判定できれば、
        // 参加者として追加する（日付・配信時間帯・ゲームタイトルの一致を「本人確認」の代わりに使う）。
        $this->addTimeWindowGenreMatches($members, $eventDayStart, $sectionGenres, $sectionMemberIds);

        // 「急遽不参加」として確認済みのメンバーは、他メンバーの動画に名前が残っている限り
        // 毎回検出されてしまうため、抽出結果から除外する。
        $absentMemberIdsByType = ArchiveSectionAbsentMember::where('event_date', $eventDayStart->toDateString())
            ->get()
            ->groupBy('section_type')
            ->map(fn (Collection $rows) => $rows->pluck('member_id')->all());

        $result = [];

        foreach (array_keys(SectionTimeWindows::WINDOWS_MINUTES) as $type) {
            $memberIds = array_diff(
                array_keys($sectionMemberIds[$type] ?? []),
                $absentMemberIdsByType->get($type, [])
            );

            $urlsByMemberId = $this->resolveSectionMemberUrls(
                $type,
                $sectionVideos[$type] ?? [],
                $memberIds,
                $allMembers,
                $eventDayStart,
                $to
            );

            $result[$type] = [
                'genre' => $sectionGenres[$type],
                'members' => $allMembers->whereIn('id', $memberIds)
                    ->sortBy('name')
                    ->map(fn (Member $member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'video_url' => $urlsByMemberId[$member->id] ?? '',
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $result;
    }

    /**
     * セクション参加者それぞれの動画URLを決める（確定できたメンバーのみ）。
     * 本人のアルジャンタグ付き投稿がそのセクションにちょうど1件あればそれを使い、
     * 無いメンバー（＝他メンバーの概要欄への記載などで参加確定済み）は、タグ無しも含めた
     * 時間帯照合(findMemberVideoInWindow)で本人の配信を探す。
     * どちらも「候補が1件に特定できた場合のみ」URLを返し、0件・複数件のときは空のまま
     * 手動登録に委ねる（syncMemberと同じ誤爆防止ルール）。
     *
     * @param array<int, array> $sectionVideos
     * @param int[] $memberIds
     * @return array<int, string>
     */
    private function resolveSectionMemberUrls(
        string $type,
        array $sectionVideos,
        array $memberIds,
        Collection $allMembers,
        Carbon $eventDayStart,
        Carbon $to
    ): array {
        $urls = [];
        $rowsByMemberId = collect($sectionVideos)->groupBy(fn (array $row) => (int) $row['member_id']);

        foreach ($memberIds as $memberId) {
            $ownRows = $rowsByMemberId->get((int) $memberId, collect());

            if ($ownRows->count() === 1) {
                $urls[(int) $memberId] = $ownRows->first()['url'] ?? '';
                continue;
            }

            // 本人のタグ付き投稿が複数重なっている場合はどれが正か決められないため空のまま。
            if ($ownRows->count() > 1) {
                continue;
            }

            $member = $allMembers->firstWhere('id', (int) $memberId);

            if (!$member) {
                continue;
            }

            // 各チャンネル・アカウントの動画一覧はfetchAllCandidatesと同じAPI呼び出しで
            // キャッシュ済みのため、ここでの再照合に追加のクォータ消費はほぼ無い。
            $video = $this->findMemberVideoInWindow($member, $type, $eventDayStart, $to);

            if ($video) {
                $urls[(int) $memberId] = $video['url'] ?? '';
            }
        }

        return $urls;
    }

    /**
     * 「この動画から参加者を抽出する」ボタン用。既にセクション内の1名について確認済みの動画URLを
     * 起点に、その動画の概要欄から他の参加者候補を検出する。全メンバー横断検索(computeForDate)より
     * 対象が1動画だけなので高速な一方、名前の言及だけでは誤検出のリスクがあるため、検出した候補は
     * 「本人の配信がこのセクションの時間帯に1件だけ見つかった場合」のみ確定候補として返す。
     *
     * @param int[] $excludeMemberIds 既にこのセクションで選択済み（フォーム上の未保存分含む）のメンバーID
     * @return array{
     *     members?: array<int, array{id:int, name:string, video_url:string}>,
     *     unverified_names?: string[],
     *     video?: array{platform:string, title:string},
     *     error?: string,
     *     message?: string,
     * }
     */
    public function extractParticipantsFromVideoUrl(
        string $videoUrl,
        string $sectionType,
        Carbon $eventDayStart,
        int $sourceMemberId,
        array $excludeMemberIds = [],
        ?string $gameGenre = null
    ): array {
        $fetcher = collect($this->fetchers)
            ->first(fn (PlatformArchiveFetcher $fetcher) => $fetcher instanceof SingleVideoLookupFetcher
                && $fetcher->supportsUrl($videoUrl));

        if (!$fetcher) {
            return [
                'error' => 'unsupported_platform',
                'message' => '対応していない動画サイトのURLです（YouTube・Twitch・ツイキャス・OPENRECに対応しています）。',
            ];
        }

        try {
            $video = $fetcher->fetchVideoByUrl($videoUrl);
        } catch (Throwable $exception) {
            Log::warning('ParticipantComputationService: extractParticipantsFromVideoUrl fetch failed', [
                'video_url' => $videoUrl,
                'platform' => $fetcher->platform(),
                'error' => $exception->getMessage(),
            ]);

            return [
                'error' => 'fetch_failed',
                'message' => '動画情報の取得に失敗しました。時間を置いて再度お試しください。',
            ];
        }

        if (!$video) {
            return [
                'error' => 'video_not_found',
                'message' => '動画情報を取得できませんでした。削除・非公開になっている可能性があります。',
            ];
        }

        $text = ($video['title'] ?? '') . ' ' . DescriptionBoilerplateStripper::strip($video['description'] ?? '');

        $absentMemberIds = ArchiveSectionAbsentMember::where('event_date', $eventDayStart->toDateString())
            ->where('section_type', $sectionType)
            ->pluck('member_id')
            ->all();

        $excludedIds = array_unique(array_merge($excludeMemberIds, $absentMemberIds, [$sourceMemberId]));

        $allMembers = Member::all();
        $detected = $this->nameExtractor->extract($text, $allMembers)
            ->reject(fn (Member $member) => in_array($member->id, $excludedIds, true));

        if ($detected->isEmpty()) {
            return [
                'members' => [],
                'unverified_names' => [],
                'video' => ['platform' => $video['platform'], 'title' => $video['title'] ?? ''],
            ];
        }

        $from = $eventDayStart;
        $to = $eventDayStart->copy()->addDay()->setTime(9, 0);

        $verifiedMembers = [];
        $unverifiedNames = [];

        foreach ($detected as $candidate) {
            // special等、時間帯の定義が無いsection_typeは時間帯重なりでは検証できないため、
            // ゲームジャンル一致（その日1日分の範囲）による代替検証にフォールバックする。
            $matchedVideo = array_key_exists($sectionType, SectionTimeWindows::WINDOWS_MINUTES)
                ? $this->findMemberVideoInWindow($candidate, $sectionType, $from, $to)
                : ($gameGenre ? $this->findMemberVideoByGenre($candidate, $gameGenre, $from, $to) : null);

            if ($matchedVideo) {
                $verifiedMembers[] = [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                    'video_url' => $matchedVideo['url'],
                ];
            } else {
                $unverifiedNames[] = ['id' => $candidate->id, 'name' => $candidate->name];
            }
        }

        return [
            'members' => $verifiedMembers,
            'unverified_names' => $unverifiedNames,
            'video' => ['platform' => $video['platform'], 'title' => $video['title'] ?? ''],
        ];
    }

    /**
     * $memberの本人チャンネル・アカウントから、$sectionTypeの時間帯に重なる配信を探す。
     * 誤爆防止のため、重なる候補がちょうど1件のときだけ返す（0件・複数件はnull）。
     * routes/console.phpのmatchArchiveSectionVideo()と同じ考え方だが、永続化済みArchiveSectionを
     * 前提にせず section_type 文字列と日時だけで判定できるようにしたもの
     * （編集画面では未保存の新規セクションに対しても使う必要があるため）。
     *
     * requireArujanTag=falseで照合する: このメソッドの呼び出し元は、他メンバーの動画の
     * 概要欄への名前記載など、別の証拠で参加をほぼ確定させたメンバーのURL裏付けにのみ使うため、
     * 本人の投稿タイトル・概要欄に「アルジャン」が無いだけの動画（タグを書かない配信者）も
     * 拾えるようにする。時間帯の重なり＋候補1件の条件は維持しているので誤爆リスクは低い。
     */
    private function findMemberVideoInWindow(Member $member, string $sectionType, Carbon $from, Carbon $to): ?array
    {
        foreach ($this->fetchers as $fetcher) {
            if (!$fetcher->supports($member)) {
                continue;
            }

            try {
                $videos = $fetcher->fetchCandidates($member, $from, $to, false);
            } catch (Throwable $exception) {
                Log::warning('ParticipantComputationService: findMemberVideoInWindow fetch failed', [
                    'member_id' => $member->id,
                    'member_name' => $member->name,
                    'platform' => $fetcher->platform(),
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            $overlapping = $videos->filter(function (array $video) use ($sectionType, $from) {
                $start = $video['published_at'];

                if (!$start) {
                    return false;
                }

                $end = isset($video['duration_seconds'])
                    ? $start->copy()->addSeconds($video['duration_seconds'])
                    : $start;

                return SectionTimeWindows::overlapsWindow($sectionType, $from, $start, $end);
            })->values();

            // 複数チャンネルの候補が重なった場合は、最優先チャンネル
            // （アルジャン配信専用チャンネル > メイン > other_urlサブ）の候補だけに絞る。
            if ($overlapping->count() > 1) {
                $topPriority = $overlapping->min(fn (array $video) => $video['channel_priority'] ?? 0);
                $overlapping = $overlapping
                    ->filter(fn (array $video) => ($video['channel_priority'] ?? 0) === $topPriority)
                    ->values();
            }

            if ($overlapping->count() === 1) {
                return $overlapping->first();
            }
        }

        return null;
    }

    /**
     * $memberの本人チャンネル・アカウントの中から、$gameGenreと推定一致するタイトル/概要欄の
     * 動画を、その日1日分($from〜$to)の範囲から探す。special等、時間帯の定義が無いsection_type
     * 向けの代替検証（addTimeWindowGenreMatches()と同じジャンル一致の考え方だが、時間帯では
     * なくその日全体を対象にする）。時間帯重なりほどの一意性は求めず、最初に見つかった1件を返す。
     */
    private function findMemberVideoByGenre(Member $member, string $gameGenre, Carbon $from, Carbon $to): ?array
    {
        foreach ($this->fetchers as $fetcher) {
            if (!$fetcher->supports($member)) {
                continue;
            }

            try {
                $videos = $fetcher->fetchCandidates($member, $from, $to, false);
            } catch (Throwable $exception) {
                Log::warning('ParticipantComputationService: findMemberVideoByGenre fetch failed', [
                    'member_id' => $member->id,
                    'member_name' => $member->name,
                    'platform' => $fetcher->platform(),
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            $matched = $videos->first(
                fn (array $video) => $this->gameGenreInference->inferFromText($video['title'] ?? '', $video['description'] ?? '') === $gameGenre
            );

            if ($matched) {
                return $matched;
            }
        }

        return null;
    }

    /**
     * ルールA（延長配信の持ち越し）で「前の区分の配信がこの区分でも続いている」とみなす
     * 最小の食い込み時間（分）。境界を少しはみ出しただけの配信を誤って持ち越さないための閾値で、
     * 時間帯照合の MIN_OVERLAP_MINUTES(10分) より意図的に厳しくしている。
     */
    private const CARRY_OVER_MIN_OVERLAP_MINUTES = 30;

    /**
     * 延長配信の持ち越し（ルールA）と、当日参加者のタグ無し新規配信（ルールB）による補完。
     * 「その区分の時間帯に開始したアルジャンタグ付き配信」が無い区分（延長組＋タグを書かない
     * 新規配信だけで構成される2次会以降）を検出できるようにする。
     *
     * A: それより前の区分で参加確定済みのメンバーの確定配信が、この区分の時間帯に
     *    CARRY_OVER_MIN_OVERLAP_MINUTES 以上食い込んでいれば、参加継続の候補とみなす。
     * B: 前の区分で参加確定済みのメンバーがこの区分の時間帯に開始した新規ライブは、
     *    タイトル・概要欄に「アルジャン」が無くても本人配信の候補とみなす
     *    （アルジャンとの繋がりは、当日すでに参加確定していること自体が担保する）。
     *
     * どちらも単独では確定させない: この区分をカバーする参加者（既存ロジックでの確定分＋
     * A・Bの候補）が合計2人以上いる場合のみ「この区分は開催された」とみなして参加者に加える。
     * 1人だけの延長・単独配信は、アルジャン解散後のソロ継続の可能性があるため保留する
     * （「2人以上の独立した証拠で合同セッションとみなす」既存ルールと同じ考え方）。
     *
     * Bの新規配信はゲームジャンル判定の材料($sectionVideos)にも加える。Aの延長分は
     * タイトル・概要欄が前の区分のゲームの話なので、ジャンル判定には使わない。
     * 区分を時系列順に処理するため、ここで確定したメンバーは次の区分の起点（前の区分の
     * 確定参加者）としても連鎖的に使われる。
     *
     * 起点（参加確定済み）には、この抽出で確定したメンバーに加えて、保存済みセクションの
     * 参加者（人間が登録した分。この区分自身の保存分も含む）を使う。保存済みメンバー自身も
     * Bの対象になるため、手動登録→再抽出で本人のタグ無し配信のURL・ジャンルが補完される。
     *
     * @param \Illuminate\Support\Collection<string, int[]> $savedMemberIdsByType
     * @param array<string, array<int, true>> $sectionMemberIds
     * @param array<string, array<int, array>> $sectionVideos
     */
    private function addContinuationCandidates(
        Collection $members,
        Carbon $eventDayStart,
        Carbon $to,
        Collection $savedMemberIdsByType,
        array &$sectionMemberIds,
        array &$sectionVideos
    ): void {
        $types = array_keys(SectionTimeWindows::WINDOWS_MINUTES);
        $allRowsByMember = [];

        foreach ($types as $index => $type) {
            if ($index === 0) {
                continue;
            }

            $seedIds = [];

            foreach (array_slice($types, 0, $index) as $earlierType) {
                $seedIds += $sectionMemberIds[$earlierType] ?? [];

                foreach ($savedMemberIdsByType->get($earlierType, []) as $savedId) {
                    $seedIds[$savedId] = true;
                }
            }

            // この区分自身に保存済みのメンバーも、B（本人の新規配信のURL・ジャンル補完）の対象にする。
            foreach ($savedMemberIdsByType->get($type, []) as $savedId) {
                $seedIds[$savedId] = true;
            }

            $carryOverIds = [];
            $newStreamRowsByMember = [];

            foreach (array_keys($seedIds) as $memberId) {
                if (isset($sectionMemberIds[$type][$memberId])) {
                    continue;
                }

                // A: 前の区分の確定配信（$sectionVideosに残っている本人の動画）が
                //    この区分の時間帯に一定時間以上食い込んでいるか。
                foreach (array_slice($types, 0, $index) as $earlierType) {
                    foreach ($sectionVideos[$earlierType] ?? [] as $row) {
                        if ((int) $row['member_id'] === $memberId
                            && $this->overlapMinutes($type, $eventDayStart, $row) >= self::CARRY_OVER_MIN_OVERLAP_MINUTES) {
                            $carryOverIds[$memberId] = true;
                            break 2;
                        }
                    }
                }

                if (isset($carryOverIds[$memberId])) {
                    continue;
                }

                // B: この区分の時間帯に開始した本人の新規ライブ（タグ不問）があるか。
                $member = $members->firstWhere('id', $memberId);

                if (!$member) {
                    continue;
                }

                if (!array_key_exists($memberId, $allRowsByMember)) {
                    $allRowsByMember[$memberId] = $this->fetchUntaggedCandidates($member, $eventDayStart, $to);
                }

                $newRows = $allRowsByMember[$memberId]
                    ->filter(function (array $row) use ($eventDayStart, $type) {
                        $publishedAt = $row['published_at'] ?? null;

                        return $publishedAt
                            && SectionTimeWindows::windowContaining($eventDayStart, $publishedAt) === $type;
                    })
                    ->values();

                if ($newRows->isNotEmpty()) {
                    $newStreamRowsByMember[$memberId] = $newRows;
                }
            }

            // この区分をカバーする参加者（保存済み＝人間確定分も含む）が2人以上そろった場合のみ
            // 開催とみなして確定させる。
            $coveringMemberIds = array_unique(array_merge(
                array_keys($sectionMemberIds[$type] ?? []),
                $savedMemberIdsByType->get($type, []),
                array_keys($carryOverIds),
                array_keys($newStreamRowsByMember)
            ));

            if (count($coveringMemberIds) < 2) {
                continue;
            }

            // 延長（A）だけでは区分を開かない: 配信が長引いただけの時間帯を「次の会が開催された」と
            // 誤認しないため、この区分で新たに始まった配信（B）・既存ロジックでの確定・保存済み参加者の
            // いずれかが最低1つ必要。延長は人数の足しにはなるが、それ単独では開催の証拠にしない。
            $hasNonCarryOverEvidence = !empty($sectionMemberIds[$type])
                || !empty($savedMemberIdsByType->get($type, []))
                || !empty($newStreamRowsByMember);

            if (!$hasNonCarryOverEvidence) {
                continue;
            }

            foreach (array_keys($carryOverIds) as $memberId) {
                $sectionMemberIds[$type][$memberId] = true;
            }

            $existingVideoIds = collect($sectionVideos[$type] ?? [])->pluck('video_id')->filter()->all();

            foreach ($newStreamRowsByMember as $memberId => $rows) {
                $sectionMemberIds[$type][$memberId] = true;

                foreach ($rows as $row) {
                    if (!in_array($row['video_id'] ?? null, $existingVideoIds, true)) {
                        $sectionVideos[$type][] = $row;
                    }
                }
            }
        }
    }

    /**
     * $typeの時間帯と動画行の配信時間が何分重なっているかを返す（重なっていなければ負値）。
     */
    private function overlapMinutes(string $type, Carbon $eventDayStart, array $row): float
    {
        $start = $row['published_at'] ?? null;

        if (!$start) {
            return -1.0;
        }

        $end = isset($row['duration_seconds']) && $row['duration_seconds']
            ? $start->copy()->addSeconds($row['duration_seconds'])
            : $start;

        [$startMinutes, $endMinutes] = SectionTimeWindows::WINDOWS_MINUTES[$type];
        $windowStart = $eventDayStart->copy()->addMinutes($startMinutes);
        $windowEnd = $eventDayStart->copy()->addMinutes($endMinutes);

        return $start->max($windowStart)->diffInMinutes($end->min($windowEnd), false);
    }

    /**
     * 本人の全ライブ（アルジャンタグ不問）を全プラットフォームから集める。ルールB用。
     * APIレスポンス自体はタグ付き検索と同じものがキャッシュされているため、追加のクォータ消費はほぼ無い。
     */
    private function fetchUntaggedCandidates(Member $member, Carbon $from, Carbon $to): Collection
    {
        $rows = collect();

        foreach ($this->fetchers as $fetcher) {
            if (!$fetcher->supports($member)) {
                continue;
            }

            try {
                $rows = $rows->merge($fetcher->fetchCandidates($member, $from, $to, false));
            } catch (Throwable $exception) {
                Log::warning('ParticipantComputationService: continuation candidate fetch failed', [
                    'member_id' => $member->id,
                    'member_name' => $member->name,
                    'platform' => $fetcher->platform(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $rows->values();
    }

    /**
     * @param array<string, ?string> $sectionGenres
     * @param array<string, array<int, true>> $sectionMemberIds
     */
    private function addTimeWindowGenreMatches(
        Collection $members,
        Carbon $eventDayStart,
        array $sectionGenres,
        array &$sectionMemberIds
    ): void {
        foreach ($sectionGenres as $type => $genre) {
            // Among Usはアルジャン外の別コミュニティ（例: 「高田村」）でも頻繁にプレイされている
            // ため、ジャンル一致だけでは「同じ日の同じ時間帯にAmong Usをやっていた無関係な配信者」
            // まで誤って参加者にしてしまうリスクが高い。この時間帯ジャンル一致による参加者補完は、
            // タイトルだけで一意に絞り込める固有名の強いゲーム（Machine Party等）向けの機能なので、
            // Among Usはここでは対象外とする。
            if (!$genre || $genre === 'Among Us') {
                continue;
            }

            [$startMinutes, $endMinutes] = SectionTimeWindows::WINDOWS_MINUTES[$type];
            $windowFrom = $eventDayStart->copy()->addMinutes($startMinutes);
            $windowTo = $eventDayStart->copy()->addMinutes($endMinutes);

            foreach ($members as $member) {
                if (isset($sectionMemberIds[$type][$member->id])) {
                    continue;
                }

                foreach ($this->fetchers as $fetcher) {
                    if (!$fetcher->supports($member)) {
                        continue;
                    }

                    try {
                        $ownCandidates = $fetcher->fetchCandidates($member, $windowFrom, $windowTo, false);
                    } catch (Throwable $exception) {
                        Log::warning('ParticipantComputationService: time-window genre match fetch failed', [
                            'member_id' => $member->id,
                            'member_name' => $member->name,
                            'platform' => $fetcher->platform(),
                            'error' => $exception->getMessage(),
                        ]);

                        continue;
                    }

                    $matched = $ownCandidates->contains(
                        fn (array $row) => $this->gameGenreInference->inferFromText($row['title'] ?? '', $row['description'] ?? '') === $genre
                    );

                    if ($matched) {
                        $sectionMemberIds[$type][$member->id] = true;
                        break;
                    }
                }
            }
        }
    }

    /**
     * アルジャン公式チャンネル（1つに固定）から、その日に投稿された動画を1本推定する
     * （「公式編集動画」欄の自動入力用）。公式チャンネルは毎日恒例企画を投稿しているため、
     * 生配信アーカイブに限らず、その日付に投稿された動画をそのまま候補とする。
     * 同日に複数本ある場合は、一番早く投稿された1本を返す（他は手動で確認・修正する想定）。
     */
    public function findOfficialVideoForDate(Carbon $eventDayStart): ?array
    {
        $officialChannelId = config('services.youtube.channel_id');

        if (!$officialChannelId) {
            return null;
        }

        $dayEnd = $eventDayStart->copy()->endOfDay();

        try {
            $video = $this->youtubeService
                ->searchChannelVideosByDate($officialChannelId, $eventDayStart, $dayEnd)
                // 検索は前後1日パディングして緩く取得しているため、ここで改めて厳密に絞り込む。
                ->filter(function (array $video) use ($eventDayStart, $dayEnd) {
                    $publishedAt = $video['published_at'] ?? null;

                    return $publishedAt && $publishedAt->between($eventDayStart, $dayEnd);
                })
                // Shorts（タイトルに#shorts、または60秒以下の短尺）は「公式編集動画」の対象外とする。
                ->reject(fn (array $video) => $this->isShort($video))
                ->sortBy('published_at')
                ->first();
        } catch (Throwable $exception) {
            // 公式動画の推定はあくまで補助機能のため、これが失敗しても参加者・ジャンル抽出まで
            // 巻き込んで画面全体をエラーにしない。
            Log::warning('ParticipantComputationService: official video fetch failed', [
                'channel_id' => $officialChannelId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (!$video) {
            return null;
        }

        return [
            'title' => $video['title'] ?? '',
            'url' => $video['url'] ?? '',
        ];
    }

    private function isShort(array $video): bool
    {
        $durationSeconds = $video['duration_seconds'] ?? null;

        return str_contains(mb_strtolower($video['title'] ?? ''), '#shorts')
            || ($durationSeconds !== null && $durationSeconds <= 180);
    }

    private function fetchAllCandidates(Collection $members, Carbon $from, Carbon $to): Collection
    {
        $candidates = collect();

        foreach ($members as $member) {
            foreach ($this->fetchers as $fetcher) {
                if (!$fetcher->supports($member)) {
                    continue;
                }

                try {
                    $candidates = $candidates->merge($fetcher->fetchCandidates($member, $from, $to));
                } catch (Throwable $exception) {
                    // 1メンバー・1プラットフォームの失敗で全体を止めない（この機能は補助的な下書き作成のため）。
                    // ただしAPIクォータ超過等が完全に無音で握りつぶされると気づけないため、警告ログだけは残す。
                    Log::warning('ParticipantComputationService: candidate fetch failed', [
                        'member_id' => $member->id,
                        'member_name' => $member->name,
                        'platform' => $fetcher->platform(),
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return $candidates;
    }

    private function membersWithAnyAccount(): Collection
    {
        return Member::query()
            ->where(function ($query) {
                $query->whereNotNull('youtube_channel_id')->where('youtube_channel_id', '<>', '');
            })
            ->orWhere(function ($query) {
                $query->whereNotNull('arujan_youtube_channel_id')->where('arujan_youtube_channel_id', '<>', '');
            })
            ->orWhere(function ($query) {
                $query->whereNotNull('twitch_url')->where('twitch_url', '<>', '');
            })
            ->orWhere(function ($query) {
                $query->whereNotNull('other_url')->where('other_url', '<>', '');
            })
            ->orderBy('id')
            ->get();
    }
}
