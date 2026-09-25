<?php

namespace App\Services\Archive;

use App\Models\Member;
use App\Services\Archive\Contracts\PlatformArchiveFetcher;
use App\Services\Archive\Contracts\SingleVideoLookupFetcher;
use App\Services\YoutubeMemberArchiveSyncService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class YoutubeArchiveFetcher implements PlatformArchiveFetcher, SingleVideoLookupFetcher
{
    public function __construct(private readonly YoutubeMemberArchiveSyncService $service)
    {
    }

    public function platform(): string
    {
        return 'youtube';
    }

    public function supports(Member $member): bool
    {
        return !empty($member->youtubeChannelIds()) || $this->resolveSubChannelId($member) !== null;
    }

    public function fetchCandidates(Member $member, Carbon $from, Carbon $to, bool $requireArujanTag = true): Collection
    {
        if (!$this->supports($member)) {
            return collect();
        }

        // アルジャン配信専用チャンネル（最優先） > メインチャンネル > other_url由来のサブチャンネル
        // の優先順で検索する。channel_priorityは、同じ時間帯に複数チャンネルの候補が重なったときに
        // 優先チャンネル側を採用するための序列（小さいほど優先）。
        $channelIds = collect($member->youtubeChannelIds())
            ->push($this->resolveSubChannelId($member))
            ->filter()
            ->unique()
            ->values();

        return $channelIds
            ->flatMap(fn (string $channelId, int $priority) => $this->fetchForChannel($member, $channelId, $from, $to, $requireArujanTag)
                ->map(fn (array $row) => $row + ['channel_priority' => $priority]))
            ->values();
    }

    /**
     * other_urlがYouTube（サブチャンネル等）を指している場合のみチャンネルIDを解決する。
     * 例: 「ちゃげぽよ。」さんのように、アルジャンの配信だけ別のサブチャンネルに上げる配信者がいるため。
     */
    private function resolveSubChannelId(Member $member): ?string
    {
        $url = $member->other_url;

        if (!$url || !str_contains(mb_strtolower($url), 'youtu')) {
            return null;
        }

        try {
            return $this->service->resolveChannelId($url);
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchForChannel(Member $member, string $channelId, Carbon $from, Carbon $to, bool $requireArujanTag = true): Collection
    {
        // search.listの日付範囲指定で対象期間（前後1日パディング込み）だけを取得する。
        // 最新から対象日まで遡るplaylistItems方式と違い、過去日でも取得時間が一定。
        return $this->service->searchChannelVideosByDate($channelId, $from, $to)
            ->filter(fn (array $video) => (bool) ($video['is_live_archive'] ?? false))
            // 検索は前後1日パディングしているため、配信開始時刻(actualStartTime)ベースの
            // published_at で改めて$from〜$toの範囲に絞り込む。
            ->filter(function (array $video) use ($from, $to) {
                $publishedAt = $video['published_at'] ?? null;

                return $publishedAt && $publishedAt->between($from, $to);
            })
            // メンバー個人チャンネルには無関係な配信（ソロ配信・他コラボ等）も混在するため、
            // タイトルか概要欄に「アルジャン」を含む動画のみを対象にする。
            // ただし$requireArujanTag=falseの場合は、呼び出し側が既に参加をほぼ確定できている
            // （他メンバーの動画から参加者として抽出済み）ため、このタグ判定をスキップする。
            ->when($requireArujanTag, fn (Collection $videos) => $videos->filter(fn (array $video) => $this->isArujanTaggedVideo($video)))
            ->map(fn (array $video) => [
                'platform' => $this->platform(),
                'member_id' => $member->id,
                'member_name' => $member->name,
                'video_id' => $video['video_id'],
                'url' => $video['url'],
                'title' => $video['title'] ?? '',
                'description' => $video['description'] ?? '',
                'published_at' => $video['published_at'] ?? null,
                'duration_seconds' => $video['duration_seconds'] ?? null,
                'is_live_archive' => true,
                'date' => $video['date'] ?? optional($video['published_at'] ?? null)->toDateString(),
            ]);
    }

    private function isArujanTaggedVideo(array $video): bool
    {
        return str_contains($video['title'] ?? '', 'アルジャン')
            || str_contains(DescriptionBoilerplateStripper::strip($video['description'] ?? ''), 'アルジャン');
    }

    public function supportsUrl(string $url): bool
    {
        return $this->service->extractYouTubeVideoId($url) !== null;
    }

    public function fetchVideoByUrl(string $url): ?array
    {
        $video = $this->service->fetchVideosByUrls([$url])->first();

        if (!$video) {
            return null;
        }

        return [
            'platform' => $this->platform(),
            'url' => $video['url'] ?? $url,
            'title' => $video['title'] ?? '',
            'description' => $video['description'] ?? '',
        ];
    }
}
