<?php

namespace App\Services;

use App\Models\ArchiveSection;
use App\Models\Member;
use App\Services\Archive\DescriptionBoilerplateStripper;
use App\Services\Archive\SectionTimeWindows;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class YoutubeMemberArchiveSyncService
{
    /**
     * 視聴数のゴールデンタイムであり、対応する動画が無いのは不自然なため要確認扱いにするセクション。
     */
    private const IMPORTANT_SECTION_TYPES = ['primary', 'secondary'];

    public function resolveChannelId(string $youtubeUrl): ?string
    {
        $youtubeUrl = trim($youtubeUrl);

        if ($youtubeUrl === '') {
            return null;
        }

        if (preg_match('/^UC[a-zA-Z0-9_-]{20,}$/', $youtubeUrl)) {
            return $youtubeUrl;
        }

        $url = str_starts_with($youtubeUrl, 'http')
            ? $youtubeUrl
            : "https://{$youtubeUrl}";

        $parts = parse_url($url);
        $path = trim($parts['path'] ?? '', '/');

        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);

        if (($segments[0] ?? null) === 'channel' && !empty($segments[1])) {
            return $segments[1];
        }

        if (str_starts_with($segments[0], '@')) {
            return $this->fetchChannelIdByHandle($segments[0]);
        }

        if (($segments[0] ?? null) === 'user' && !empty($segments[1])) {
            return $this->fetchChannelIdByUsername($segments[1]);
        }

        return $this->fetchChannelIdFromPage($url);
    }

    public function searchChannelCandidates(string $keyword, int $limit = 5): array
    {
        $data = $this->fetchYoutubeApi('search', [
            'key' => $this->apiKey(),
            'q' => $keyword,
            'part' => 'snippet',
            'type' => 'channel',
            'maxResults' => max(1, min($limit, 10)),
        ]);

        return collect(data_get($data, 'items', []))
            ->map(function (array $item) {
                $channelId = data_get($item, 'snippet.channelId')
                    ?: data_get($item, 'id.channelId');

                return [
                    'channel_id' => $channelId,
                    'title' => data_get($item, 'snippet.title', ''),
                    'description' => data_get($item, 'snippet.description', ''),
                    'url' => $channelId ? "https://www.youtube.com/channel/{$channelId}" : null,
                ];
            })
            ->filter(fn (array $candidate) => $candidate['channel_id'])
            ->values()
            ->all();
    }

    public function fetchChannelProfile(string $channelId): ?array
    {
        $data = $this->fetchYoutubeApi('channels', [
            'key' => $this->apiKey(),
            'id' => $channelId,
            'part' => 'snippet',
            'maxResults' => 1,
        ]);

        $item = data_get($data, 'items.0');

        if (!$item) {
            return null;
        }

        return [
            'title' => data_get($item, 'snippet.title', ''),
            'description' => data_get($item, 'snippet.description', ''),
            'thumbnail_url' => data_get($item, 'snippet.thumbnails.high.url')
                ?: data_get($item, 'snippet.thumbnails.medium.url')
                ?: data_get($item, 'snippet.thumbnails.default.url'),
        ];
    }

    public function fetchChannelVideos(Member $member, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return collect($member->youtubeChannelIds())
            ->flatMap(fn (string $channelId) => $this->fetchChannelUploadVideos($channelId, $from, $to)
                ->map(fn (array $video) => array_merge($video, [
                    'member_id' => $member->id,
                    'member_name' => $member->name,
                    'channel_id' => $channelId,
                ])))
            ->values();
    }

    /**
     * search.list の日付範囲指定で、対象期間の動画だけをピンポイントに取得する。
     * playlistItemsのように「最新から対象日まで遡る」必要がないため、どんな過去日でも
     * 取得時間・呼び出し回数が一定（抽出ボタンなど対象日が1日に決まっている処理向け）。
     * 1回100ユニットとクォータ消費は重いので、期間の広い一括同期には使わないこと。
     *
     * publishedAt（公開日時）は配信開始時刻(actualStartTime)と多少ズレることがあるため、
     * 検索自体は前後1日パディングし、正確な絞り込みは呼び出し側がvideos.list由来の
     * published_at で行う前提。
     */
    public function searchChannelVideosByDate(string $channelId, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        $videoIds = collect();
        $pageToken = null;

        do {
            $params = [
                'key' => $this->apiKey(),
                'channelId' => $channelId,
                'part' => 'id',
                'type' => 'video',
                'order' => 'date',
                'maxResults' => 50,
                'publishedAfter' => $from->copy()->subDay()->utc()->toIso8601ZuluString(),
                'publishedBefore' => $to->copy()->addDay()->utc()->toIso8601ZuluString(),
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $data = $this->fetchYoutubeApi('search', $params);

            foreach (data_get($data, 'items', []) as $item) {
                $videoId = data_get($item, 'id.videoId');

                if ($videoId) {
                    $videoIds->push($videoId);
                }
            }

            $pageToken = data_get($data, 'nextPageToken');
        } while ($pageToken);

        if ($videoIds->isEmpty()) {
            return collect();
        }

        return $this->fetchVideoDetails($videoIds->unique()->values()->all())
            ->map(fn (array $video) => array_merge($video, [
                'channel_id' => $video['channel_id'] ?? $channelId,
            ]));
    }

    public function fetchChannelUploadVideos(string $channelId, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        $playlistId = $this->fetchUploadsPlaylistId($channelId);
        $playlistVideos = $this->fetchPlaylistVideos($playlistId, $from, $to);

        if ($playlistVideos->isEmpty()) {
            return collect();
        }

        return $this->fetchVideoDetails($playlistVideos->pluck('video_id')->all())
            ->map(function (array $video) use ($channelId, $playlistVideos) {
                $playlistVideo = $playlistVideos->firstWhere('video_id', $video['video_id']);

                return array_merge($playlistVideo, $video, [
                    'channel_id' => $channelId,
                ]);
            });
    }

    public function fetchVideosByUrls(array $urls): \Illuminate\Support\Collection
    {
        $videoIds = collect($urls)
            ->map(fn (?string $url) => $url ? $this->extractYouTubeVideoId($url) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($videoIds)) {
            return collect();
        }

        return $this->fetchVideoDetails($videoIds);
    }

    /**
     * 指定メンバーの本人チャンネルから、指定セクションの時間帯に重なる生配信アーカイブを1件だけ探す。
     * 名前だけでの参加者追加を禁止するためのルール: 呼び出し側は、ここで動画が見つかった場合のみ
     * そのメンバーをセクションに追加してよい（候補0件・複数件のときは追加しない）。
     */
    public function findSectionVideoForMember(Member $member, ArchiveSection $section): ?array
    {
        $channelIds = $member->youtubeChannelIds();

        foreach ($channelIds as $channelId) {
            $video = $this->findSectionVideoForChannel($channelId, $section);

            if ($video) {
                return $video;
            }
        }

        $subChannelId = $this->resolveYoutubeSubChannelId($member);

        if ($subChannelId && !in_array($subChannelId, $channelIds, true)) {
            return $this->findSectionVideoForChannel($subChannelId, $section);
        }

        return null;
    }

    /**
     * other_url がYouTube（サブチャンネル等）を指している場合のみチャンネルIDを解決する。
     * Twitch/ツイキャス等、API連携のない媒体はここでは解決できない（呼び出し側で人手確認を促す）。
     */
    private function resolveYoutubeSubChannelId(Member $member): ?string
    {
        $url = $member->other_url;

        if (!$url || !str_contains(mb_strtolower($url), 'youtu')) {
            return null;
        }

        try {
            return $this->resolveChannelId($url);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function findSectionVideoForChannel(string $channelId, ArchiveSection $section): ?array
    {
        $eventDate = $section->onedayarchive?->event_date;

        if (!$eventDate || !array_key_exists($section->section_type, SectionTimeWindows::WINDOWS_MINUTES)) {
            return null;
        }

        $timezone = config('app.timezone', 'Asia/Tokyo');
        $date = Carbon::parse($eventDate, $timezone)->startOfDay();

        // 対象日の前後1日だけをsearch.listでピンポイント取得する（最新からの遡り無し）。
        $videos = $this->searchChannelVideosByDate(
            $channelId,
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay()
        )->filter(fn (array $video) => $this->isValidArujanLiveVideo($video));

        $overlapping = $videos->filter(function (array $video) use ($section, $date) {
            $videoStart = $video['published_at'];
            $videoEnd = $video['ended_at']
                ?? (isset($video['duration_seconds']) ? $videoStart->copy()->addSeconds($video['duration_seconds']) : $videoStart);

            return SectionTimeWindows::overlapsWindow($section->section_type, $date, $videoStart, $videoEnd);
        })->values();

        if ($overlapping->count() !== 1) {
            return null;
        }

        return $overlapping->first();
    }

    private function isValidArujanLiveVideo(array $video): bool
    {
        $isLive = ($video['is_live_archive'] ?? false) === true;
        $durationSeconds = $video['duration_seconds'] ?? null;
        $isShorts = str_contains(mb_strtolower($video['title'] ?? ''), '#shorts')
            || ($durationSeconds !== null && $durationSeconds <= 180);

        return $isLive && !$isShorts && $this->isArujanTaggedVideo($video);
    }


    public function syncMember(
        Member $member,
        ?Carbon $from = null,
        ?Carbon $to = null,
        bool $overwrite = false,
        bool $dryRun = false
    ): array {
        $channelIds = $member->youtubeChannelIds();

        if (empty($channelIds)) {
            return [
                'member' => $member->name,
                'fetched' => 0,
                'updated' => 0,
                'skipped' => 0,
                'message' => 'youtube_channel_id is empty.',
            ];
        }

        $targetDates = $this->datesNeedingSync($member, $from, $to, $overwrite);

        if (empty($targetDates)) {
            return [
                'member' => $member->name,
                'fetched' => 0,
                'updated' => 0,
                'skipped' => 0,
                'multiple_candidate_dates' => [],
                'message' => 'No archive sections need syncing for this member/date range.',
            ];
        }

        // アルジャン配信専用チャンネル（登録されていれば最優先）とメインチャンネルの両方を検索する
        // （アルジャンだけサブチャンネルで配信するメンバーの手動登録を不要にするため）。
        // channel_priorityは、両チャンネルの動画が同じ時間帯に重なったときに
        // 優先チャンネル側を採用するための序列（小さいほど優先）。
        $videos = collect($channelIds)
            ->values()
            ->flatMap(function (string $channelId, int $priority) use ($from, $to, $targetDates) {
                $uploadsPlaylistId = $this->fetchUploadsPlaylistId($channelId);

                return $this->fetchVideosByPublishedDate($uploadsPlaylistId, $from, $to, $targetDates)
                    ->flatten(1)
                    ->map(fn (array $video) => $video + ['channel_priority' => $priority]);
            })
            ->unique('video_id')
            ->groupBy('date');

        $updated = 0;
        $skipped = 0;
        $multipleCandidateDates = [];
        $needsReview = [];

        foreach ($videos as $date => $dateVideos) {
            if ($dateVideos->count() > 1) {
                $multipleCandidateDates[$date] = $dateVideos->count();
            }

            $sections = ArchiveSection::query()
                ->whereHas('onedayarchive', function ($query) use ($date) {
                    $query->whereDate('event_date', $date);
                })
                ->whereHas('members', function ($query) use ($member) {
                    $query->where('members.id', $member->id);
                })
                ->with(['members' => function ($query) use ($member) {
                    $query->where('members.id', $member->id);
                }])
                ->get();

            if ($sections->isEmpty()) {
                $skipped++;
                continue;
            }

            $orderableSections = $sections->filter(
                fn (ArchiveSection $section) => array_key_exists($section->section_type, SectionTimeWindows::WINDOWS_MINUTES)
            )->values();
            $unorderableSections = $sections->reject(
                fn (ArchiveSection $section) => array_key_exists($section->section_type, SectionTimeWindows::WINDOWS_MINUTES)
            )->values();

            $dayStart = Carbon::parse($date, config('app.timezone', 'Asia/Tokyo'))->startOfDay();
            $isMainMember = Member::isMainMemberName($member->name);

            foreach ($orderableSections as $section) {
                // メインメンバーが2人以上確定していないセクションは「開催されていない」と判断し、
                // サブメンバー以下の登録は保留する（メインメンバー自身の判定は素通りさせる）。
                if (!$isMainMember && !$this->sectionHasEnoughConfirmedMainMembers($section)) {
                    $skipped++;
                    continue;
                }

                $overlappingVideos = $dateVideos->filter(function (array $video) use ($section, $dayStart) {
                    $videoStart = $video['published_at'];
                    $videoEnd = $video['ended_at']
                        ?? (isset($video['duration_seconds']) ? $videoStart->copy()->addSeconds($video['duration_seconds']) : $videoStart);

                    return SectionTimeWindows::overlapsWindow($section->section_type, $dayStart, $videoStart, $videoEnd);
                })->values();

                $overlappingVideos = $this->narrowToTopPriorityChannel($overlappingVideos);

                $currentUrl = $section->members->first()?->pivot?->video_url;

                if ($overlappingVideos->count() === 1) {
                    $video = $overlappingVideos->first();

                    if ($currentUrl && !$overwrite) {
                        $skipped++;
                        continue;
                    }

                    if (!$dryRun) {
                        $section->members()->updateExistingPivot($member->id, [
                            'video_url' => $video['url'],
                            'needs_review' => false,
                            'review_reason' => null,
                        ]);
                    }

                    $updated++;
                    continue;
                }

                if ($overlappingVideos->count() > 1) {
                    $reason = sprintf(
                        '%s の時間帯に動画候補が%d件重なっています',
                        $section->section_type,
                        $overlappingVideos->count()
                    );

                    $needsReview[] = [
                        'date' => $date,
                        'section_id' => $section->id,
                        'section_type' => $section->section_type,
                        'reason' => $reason,
                    ];

                    if (!$dryRun) {
                        $section->members()->updateExistingPivot($member->id, [
                            'needs_review' => true,
                            'review_reason' => $reason,
                        ]);
                    }

                    $skipped++;
                    continue;
                }

                // 該当する動画が0件
                if (in_array($section->section_type, self::IMPORTANT_SECTION_TYPES, true)) {
                    $reason = '重要な時間帯なのに該当する動画が見つかりません';

                    $needsReview[] = [
                        'date' => $date,
                        'section_id' => $section->id,
                        'section_type' => $section->section_type,
                        'reason' => $reason,
                    ];

                    if (!$dryRun) {
                        $section->members()->updateExistingPivot($member->id, [
                            'needs_review' => true,
                            'review_reason' => $reason,
                        ]);
                    }

                    $skipped++;
                    continue;
                }

                // pre/third は該当動画が無くても正常（警告なし・空欄のまま）。
                // overwrite時は、古い（誤った）video_urlやneeds_reviewが残っていれば後始末する。
                if ($overwrite && !$dryRun && ($currentUrl || $section->members->first()?->pivot?->needs_review)) {
                    $section->members()->updateExistingPivot($member->id, [
                        'video_url' => null,
                        'needs_review' => false,
                        'review_reason' => null,
                    ]);
                }

                $skipped++;
            }

            $unorderableReason = 'special等、時間帯では自動判定できないsection_typeのため手動確認が必要';

            foreach ($unorderableSections as $section) {
                if (!$isMainMember && !$this->sectionHasEnoughConfirmedMainMembers($section)) {
                    $skipped++;
                    continue;
                }

                $needsReview[] = [
                    'date' => $date,
                    'section_id' => $section->id,
                    'section_type' => $section->section_type,
                    'reason' => $unorderableReason,
                ];

                if (!$dryRun) {
                    $section->members()->updateExistingPivot($member->id, [
                        'needs_review' => true,
                        'review_reason' => $unorderableReason,
                    ]);
                }

                $skipped++;
            }
        }

        return [
            'member' => $member->name,
            'fetched' => $videos->flatten(1)->count(),
            'updated' => $updated,
            'skipped' => $skipped,
            'multiple_candidate_dates' => $multipleCandidateDates,
            'needs_review' => $needsReview,
            'message' => 'ok',
        ];
    }

    /**
     * 同じ時間帯に複数チャンネルの動画が重なった場合、最優先チャンネル
     * （アルジャン配信専用チャンネル > メインチャンネル）の候補だけに絞る。
     * 詩人さんのように両チャンネルへ動画が並ぶケースで、専用チャンネル側を採用するため。
     * 同一チャンネル内で複数重なっている場合は絞り込めないのでそのまま返す（要確認扱いになる）。
     */
    private function narrowToTopPriorityChannel(\Illuminate\Support\Collection $videos): \Illuminate\Support\Collection
    {
        if ($videos->count() <= 1) {
            return $videos;
        }

        $topPriority = $videos->min(fn (array $video) => $video['channel_priority'] ?? 0);

        return $videos
            ->filter(fn (array $video) => ($video['channel_priority'] ?? 0) === $topPriority)
            ->values();
    }

    /**
     * 同期対象になり得る日付（そのメンバーが参加するセクションがあり、
     * overwrite指定がないなら動画URL未登録の日付）だけに絞り込む。
     * これによりAPI呼び出し（videos.list等）を無関係な日付分省略できる。
     */
    private function datesNeedingSync(Member $member, ?Carbon $from, ?Carbon $to, bool $overwrite): array
    {
        return ArchiveSection::query()
            ->whereHas('members', function ($query) use ($member) {
                $query->where('members.id', $member->id);
            })
            ->whereHas('onedayarchive', function ($query) use ($from, $to) {
                if ($from) {
                    $query->whereDate('event_date', '>=', $from->toDateString());
                }

                if ($to) {
                    $query->whereDate('event_date', '<=', $to->toDateString());
                }
            })
            ->with([
                'onedayarchive',
                'members' => function ($query) use ($member) {
                    $query->where('members.id', $member->id);
                },
            ])
            ->get()
            ->filter(function (ArchiveSection $section) use ($overwrite) {
                if ($overwrite) {
                    return true;
                }

                return !$section->members->first()?->pivot?->video_url;
            })
            ->pluck('onedayarchive.event_date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * そのセクションに、動画URLが確定済みのメインメンバーが2人以上いるかどうか。
     * 満たさない場合、そのセクションは「開催されていない」と判断し、
     * サブメンバー以下の登録を保留する。
     */
    private function sectionHasEnoughConfirmedMainMembers(ArchiveSection $section): bool
    {
        return $section->members()
            ->get()
            ->filter(fn (Member $m) => Member::isMainMemberName($m->name) && !empty($m->pivot->video_url))
            ->count() >= 2;
    }

    private function fetchUploadsPlaylistId(string $channelId): string
    {
        if (str_starts_with($channelId, 'UC')) {
            return 'UU' . substr($channelId, 2);
        }

        $data = $this->fetchYoutubeApi('channels', [
            'key' => $this->apiKey(),
            'id' => $channelId,
            'part' => 'contentDetails',
            'maxResults' => 1,
        ]);

        $playlistId = data_get($data, 'items.0.contentDetails.relatedPlaylists.uploads');

        if (!$playlistId) {
            throw new RuntimeException("Uploads playlist was not found for channel ID: {$channelId}");
        }

        return $playlistId;
    }

    private function fetchChannelIdByHandle(string $handle): ?string
    {
        $data = $this->fetchYoutubeApi('channels', [
            'key' => $this->apiKey(),
            'forHandle' => $handle,
            'part' => 'id',
            'maxResults' => 1,
        ]);

        return data_get($data, 'items.0.id');
    }

    private function fetchChannelIdByUsername(string $username): ?string
    {
        $data = $this->fetchYoutubeApi('channels', [
            'key' => $this->apiKey(),
            'forUsername' => $username,
            'part' => 'id',
            'maxResults' => 1,
        ]);

        return data_get($data, 'items.0.id');
    }

    private function fetchChannelIdFromPage(string $url): ?string
    {
        $response = Http::timeout(10)->get($url);

        if (!$response->successful()) {
            return null;
        }

        if (preg_match('/"channelId":"(UC[a-zA-Z0-9_-]+)"/', $response->body(), $matches)) {
            return $matches[1];
        }

        if (preg_match('/<meta itemprop="channelId" content="(UC[a-zA-Z0-9_-]+)">/', $response->body(), $matches)) {
            return $matches[1];
        }

        return null;
    }


    private function fetchVideosByPublishedDate(string $playlistId, ?Carbon $from, ?Carbon $to, array $targetDates = []): \Illuminate\Support\Collection
    {
        $pageToken = null;
        $videos = collect();
        $timezone = config('app.timezone', 'Asia/Tokyo');
        $reachedFrom = false;
        $consecutiveOld = 0;

        // 深夜またぎ配信はvideoPublishedAtが翌日側にずれることがあるため、
        // $targetDatesが指定されているときは取得範囲自体も前後1日広げておく。
        $fetchFrom = ($from && !empty($targetDates)) ? $from->copy()->subDay() : $from;
        $fetchTo = ($to && !empty($targetDates)) ? $to->copy()->addDay() : $to;

        do {
            $params = [
                'key' => $this->apiKey(),
                'playlistId' => $playlistId,
                'part' => 'snippet,contentDetails',
                'maxResults' => 50,
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $data = $this->fetchYoutubeApi('playlistItems', $params);

            foreach (data_get($data, 'items', []) as $item) {
                $videoId = data_get($item, 'contentDetails.videoId');
                $publishedAt = data_get($item, 'contentDetails.videoPublishedAt')
                    ?: data_get($item, 'snippet.publishedAt');

                if (!$videoId || !$publishedAt) {
                    continue;
                }

                $published = Carbon::parse($publishedAt)->timezone($timezone);
                $date = $published->toDateString();

                if ($fetchTo && $published->greaterThan($fetchTo->copy()->endOfDay())) {
                    continue;
                }

                if ($fetchFrom && $published->lessThan($fetchFrom->copy()->startOfDay())) {
                    // 予約投稿・プレミア公開の動画が再生リスト上で公開日時順から前後することがあり、
                    // 1件だけ古い動画が挟まっても即座に打ち切らず、一定件数連続した場合のみ打ち切る。
                    $consecutiveOld++;

                    if ($consecutiveOld >= self::MIN_CONSECUTIVE_OLD_TO_STOP) {
                        $reachedFrom = true;
                        break;
                    }

                    continue;
                }

                $consecutiveOld = 0;

                $videos->push([
                    'video_id' => $videoId,
                    'url' => "https://www.youtube.com/watch?v={$videoId}",
                    'title' => data_get($item, 'snippet.title', ''),
                    'published_at' => $published,
                    'date' => $date,
                ]);
            }

            $pageToken = $reachedFrom ? null : data_get($data, 'nextPageToken');
        } while ($pageToken);

        if (!empty($targetDates)) {
            // 深夜またぎ配信は videoPublishedAt が翌日側にずれることがあるため、
            // 前後1日を許容してから videos.list で配信開始時刻ベースの正確な日付に補正する。
            $paddedDates = collect($targetDates)
                ->flatMap(function (string $date) {
                    $day = Carbon::parse($date);

                    return [
                        $day->copy()->subDay()->toDateString(),
                        $day->toDateString(),
                        $day->copy()->addDay()->toDateString(),
                    ];
                })
                ->unique()
                ->all();

            $videos = $videos->filter(fn (array $video) => in_array($video['date'], $paddedDates, true))->values();
        }

        return $this->filterLiveArchiveVideos($videos, $targetDates)->groupBy('date');
    }

    /**
     * Shorts・編集済みハイライト動画（liveStreamingDetailsを持たない動画）を除外し、
     * 生配信アーカイブのみを候補として残す。日付は配信開始時刻（actualStartTime）ベースに
     * 補正し、$targetDatesが指定されていれば補正後の日付でも最終絞り込みを行う。
     */
    private function filterLiveArchiveVideos(\Illuminate\Support\Collection $videos, array $targetDates = []): \Illuminate\Support\Collection
    {
        if ($videos->isEmpty()) {
            return $videos;
        }

        $details = $this->fetchVideoDetails($videos->pluck('video_id')->all())->keyBy('video_id');

        return $videos
            ->map(function (array $video) use ($details) {
                $detail = $details->get($video['video_id']);

                return array_merge($video, [
                    'date' => $detail['date'] ?? $video['date'],
                    'is_live_archive' => (bool) ($detail['is_live_archive'] ?? false),
                    'duration_seconds' => $detail['duration_seconds'] ?? null,
                    // videos.list の liveStreamingDetails ベースの方が playlistItems より正確なため上書きする
                    'published_at' => $detail['published_at'] ?? $video['published_at'],
                    'ended_at' => $detail['ended_at'] ?? null,
                    'description' => $detail['description'] ?? '',
                ]);
            })
            ->filter(fn (array $video) => $video['is_live_archive'])
            // アルジャン企画の配信のみを対象にする（個人チャンネルの無関係な配信を除外）
            ->filter(fn (array $video) => $this->isArujanTaggedVideo($video))
            ->when(
                !empty($targetDates),
                fn (\Illuminate\Support\Collection $collection) => $collection->filter(
                    fn (array $video) => in_array($video['date'], $targetDates, true)
                )
            )
            ->values();
    }

    /**
     * タイトルか概要欄に「アルジャン」を含む動画のみをアルジャン企画の配信とみなす。
     * メンバー個人チャンネルには無関係な配信（ソロ配信・他コラボ等）も混在するための絞り込み。
     */
    private function isArujanTaggedVideo(array $video): bool
    {
        return str_contains($video['title'] ?? '', 'アルジャン')
            || str_contains(DescriptionBoilerplateStripper::strip($video['description'] ?? ''), 'アルジャン');
    }

    /**
     * 予約投稿・プレミア公開の動画は、実際の公開(配信)日時より先に再生リストに追加されることがあり、
     * その分だけ再生リストの並び順が公開日時の降順から前後することがある。1件でも$fromより古い
     * 動画を見つけた時点で打ち切ると、そのすぐ後（実際にはページング上もっと先）にある本来
     * 範囲内の動画を取りこぼすため、連続して一定件数($minConsecutiveOldToStop)古い動画が
     * 続いた場合のみ打ち切ることで、この並び順の乱れを許容する。
     */
    private const MIN_CONSECUTIVE_OLD_TO_STOP = 5;

    private function fetchPlaylistVideos(string $playlistId, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        $pageToken = null;
        $videos = collect();
        $timezone = config('app.timezone', 'Asia/Tokyo');
        $consecutiveOld = 0;

        do {
            $params = [
                'key' => $this->apiKey(),
                'playlistId' => $playlistId,
                'part' => 'snippet,contentDetails',
                'maxResults' => 50,
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $data = $this->fetchYoutubeApi('playlistItems', $params);

            foreach (data_get($data, 'items', []) as $item) {
                $videoId = data_get($item, 'contentDetails.videoId');
                $publishedAt = data_get($item, 'contentDetails.videoPublishedAt')
                    ?: data_get($item, 'snippet.publishedAt');

                if (!$videoId || !$publishedAt) {
                    continue;
                }

                $published = Carbon::parse($publishedAt)->timezone($timezone);

                // 予約投稿・プレミア公開の動画は、実際の配信(公開)日時と再生リストへの登録日時が
                // 数日〜1週間以上ズレることがある。ここではvideoPublishedAtで絞り込まず、
                // 一旦全件集めて、後段（videos.list取得後）でactualStartTimeベースに正確な
                // 期間フィルタをかける。videoPublishedAtは、ページングをどこまで遡るかの
                // 目安（何件連続で古い動画が続いたら打ち切るか）にのみ使う。
                $videos->push([
                    'video_id' => $videoId,
                    'url' => "https://www.youtube.com/watch?v={$videoId}",
                    'playlist_published_at' => $published,
                ]);

                if ($published->lessThan($from)) {
                    $consecutiveOld++;

                    if ($consecutiveOld >= self::MIN_CONSECUTIVE_OLD_TO_STOP) {
                        return $videos;
                    }
                } else {
                    $consecutiveOld = 0;
                }
            }

            $pageToken = data_get($data, 'nextPageToken');
        } while ($pageToken);

        return $videos;
    }

    private function fetchVideoDetails(array $videoIds): \Illuminate\Support\Collection
    {
        $timezone = config('app.timezone', 'Asia/Tokyo');

        return collect($videoIds)
            ->chunk(50)
            ->flatMap(function ($chunk) use ($timezone) {
                $data = $this->fetchYoutubeApi('videos', [
                    'key' => $this->apiKey(),
                    'id' => $chunk->implode(','),
                    'part' => 'snippet,liveStreamingDetails,contentDetails',
                    'maxResults' => 50,
                ]);

                return collect(data_get($data, 'items', []))
                    ->map(function (array $item) use ($timezone) {
                        $startedAt = data_get($item, 'liveStreamingDetails.actualStartTime')
                            ?: data_get($item, 'liveStreamingDetails.scheduledStartTime')
                            ?: data_get($item, 'snippet.publishedAt');
                        $published = Carbon::parse($startedAt)->timezone($timezone);
                        $videoId = data_get($item, 'id');
                        $duration = data_get($item, 'contentDetails.duration');
                        $durationSeconds = $this->parseYoutubeDurationSeconds($duration);
                        $liveStreamingDetails = data_get($item, 'liveStreamingDetails');
                        $hasLiveDetails = !empty($liveStreamingDetails);
                        $isPremiere = $hasLiveDetails && empty(data_get($liveStreamingDetails, 'actualStartTime'));
                        // プレミア公開（生配信していない編集動画の公開演出）は、
                        // 35分以下ならハイライト動画とみなして除外する。35分超は生配信扱いとする。
                        $isShortPremiere = $isPremiere && $durationSeconds !== null && $durationSeconds <= 35 * 60;

                        return [
                            'video_id' => $videoId,
                            'url' => "https://www.youtube.com/watch?v={$videoId}",
                            'title' => data_get($item, 'snippet.title', ''),
                            'description' => data_get($item, 'snippet.description', ''),
                            'channel_id' => data_get($item, 'snippet.channelId'),
                            'tags' => data_get($item, 'snippet.tags', []),
                            'duration' => $duration,
                            'duration_seconds' => $durationSeconds,
                            'is_live_archive' => $hasLiveDetails && !$isShortPremiere,
                            'published_at' => $published,
                            'ended_at' => data_get($liveStreamingDetails, 'actualEndTime')
                                ? Carbon::parse(data_get($liveStreamingDetails, 'actualEndTime'))->timezone($timezone)
                                : null,
                            'date' => $published->toDateString(),
                        ];
                    });
            })
            ->values();
    }

    private function parseYoutubeDurationSeconds(?string $duration): ?int
    {
        if (!$duration) {
            return null;
        }

        if (!preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/', $duration, $matches)) {
            return null;
        }

        return ((int) ($matches[1] ?? 0) * 86400)
            + ((int) ($matches[2] ?? 0) * 3600)
            + ((int) ($matches[3] ?? 0) * 60)
            + (int) ($matches[4] ?? 0);
    }

    public function extractYouTubeVideoId(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
            return $url;
        }

        $parts = parse_url(str_starts_with($url, 'http') ? $url : "https://{$url}");
        $host = $parts['host'] ?? '';
        $path = trim($parts['path'] ?? '', '/');

        if (str_contains($host, 'youtu.be') && $path !== '') {
            return explode('/', $path)[0] ?: null;
        }

        if (str_contains($host, 'youtube.com')) {
            parse_str($parts['query'] ?? '', $query);

            if (!empty($query['v'])) {
                return $query['v'];
            }

            $segments = explode('/', $path);

            foreach (['shorts', 'live', 'embed'] as $prefix) {
                $index = array_search($prefix, $segments, true);

                if ($index !== false && !empty($segments[$index + 1])) {
                    return $segments[$index + 1];
                }
            }
        }

        return null;
    }

    /**
     * YouTube Data APIのクォータを節約するため、レスポンスを24時間キャッシュする。
     * 失敗時はキャッシュせず例外を投げる。
     */
    private function fetchYoutubeApi(string $endpoint, array $params): array
    {
        $cacheKey = 'youtube_api:' . $endpoint . ':' . md5(json_encode($params));

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($endpoint, $params) {
            $response = Http::get("https://www.googleapis.com/youtube/v3/{$endpoint}", $params);

            if (!$response->successful()) {
                throw new RuntimeException("Failed to call YouTube API ({$endpoint}): {$response->body()}");
            }

            return $response->json();
        });
    }

    private function apiKey(): string
    {
        $apiKey = config('services.youtube.api_key');

        if (!$apiKey) {
            throw new RuntimeException('YOUTUBE_API_KEY is not configured.');
        }

        return $apiKey;
    }
}
