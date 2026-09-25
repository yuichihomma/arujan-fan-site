<?php

namespace App\Services\Archive;

use App\Models\Member;
use App\Services\Archive\Contracts\PlatformArchiveFetcher;
use App\Services\YoutubeMemberArchiveSyncService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 全メンバーの生配信アーカイブを「アルジャンタグの有無に関わらず」スプシのall_streamsシートに貯める。
 * 1日ずつAPIで候補を取って即判定するのではなく、先に全件を貯めてから仕分けるための下準備。
 *
 * YouTubeだけはYoutubeArchiveFetcher（search.list: 1回100ユニット・約500件上限）を使わず、
 * アップロード再生リスト（playlistItems: 1回1ユニット）から遡る。数年分の初回取り込みでも
 * クォータを使い切らないようにするため。
 * Twitch/ツイキャス/OPENRECは既存Fetcherをタグ判定なしで使う。TwitchはVODが約60日で消え、
 * OPENRECは最新20件しか返さないため、定期取り込みで消える前に記録しておく前提。
 */
class StreamArchiveCollector
{
    private const SECTION_LABELS = [
        'pre' => '0次会',
        'primary' => '1次会',
        'secondary' => '2次会',
        'third' => '3次会',
        'fourth' => '4次会',
    ];

    /**
     * 4次会の時間帯（翌08:00まで）に合わせ、これより前に始まった配信は前日のイベントとして扱う。
     */
    private const EVENT_DAY_BOUNDARY_HOUR = 8;

    /**
     * @param PlatformArchiveFetcher[] $otherFetchers YouTube以外のプラットフォームのFetcher
     */
    public function __construct(
        private readonly YoutubeMemberArchiveSyncService $youtube,
        private readonly GoogleSheetsStreamArchiveStore $store,
        private readonly array $otherFetchers,
    ) {
    }

    /**
     * 指定期間の生配信アーカイブを取得してall_streamsシートに追記する。既にシートにある
     * platform+video_idは追記しないため、何度実行しても（期間が重なっても）重複しない。
     *
     * @param callable(string $member, string $platform, string $message): void $onError
     * @return int 追記した件数
     */
    public function import(Collection $members, Carbon $from, Carbon $to, callable $onError): int
    {
        $existingKeys = $this->store->existingKeys()->flip();
        $registeredUrls = $this->registeredUrls();
        $saved = 0;

        foreach ($members as $member) {
            $videos = collect();

            try {
                $videos = $videos->merge($this->fetchYoutube($member, $from, $to));
            } catch (Throwable $exception) {
                $onError($member->name, 'youtube', $exception->getMessage());
            }

            foreach ($this->otherFetchers as $fetcher) {
                if (!$fetcher->supports($member)) {
                    continue;
                }

                try {
                    $videos = $videos->merge($fetcher->fetchCandidates($member, $from, $to, requireArujanTag: false));
                } catch (Throwable $exception) {
                    $onError($member->name, $fetcher->platform(), $exception->getMessage());
                }
            }

            $newVideos = $videos
                ->unique(fn (array $video) => $video['platform'] . ':' . $video['video_id'])
                ->reject(fn (array $video) => $existingKeys->has($video['platform'] . ':' . $video['video_id']))
                ->sortBy(fn (array $video) => $video['published_at']->getTimestamp())
                ->values();

            // メンバーごとに追記し、途中でAPIが失敗してもそこまでの取得分は残す。
            $this->store->appendRows($newVideos->map(fn (array $video) => $this->toSheetRow(
                $member,
                $video,
                $registeredUrls->has($this->normalizeUrl($video['url']))
            )));
            $saved += $newVideos->count();
        }

        return $saved;
    }

    private function registeredUrls(): Collection
    {
        return DB::table('archive_section_member')
            ->whereNotNull('video_url')
            ->pluck('video_url')
            ->map(fn (string $url) => $this->normalizeUrl($url))
            ->flip();
    }

    /**
     * GoogleSheetsStreamArchiveStore::HEADER_ROWの順に並べた1行を作る。
     */
    private function toSheetRow(Member $member, array $video, bool $registered): array
    {
        /** @var Carbon $startedAt */
        $startedAt = $video['published_at']->copy()->setTimezone(config('app.timezone', 'Asia/Tokyo'));
        $eventDayStart = $startedAt->copy()->startOfDay();

        if ($startedAt->hour < self::EVENT_DAY_BOUNDARY_HOUR) {
            $eventDayStart->subDay();
        }

        $sectionType = SectionTimeWindows::windowContaining($eventDayStart, $startedAt);
        $hasTag = str_contains($video['title'] ?? '', 'アルジャン')
            || str_contains(DescriptionBoilerplateStripper::strip($video['description'] ?? ''), 'アルジャン');
        $durationSeconds = $video['duration_seconds'] ?? null;

        return [
            $eventDayStart->toDateString(),
            $startedAt->format('Y-m-d H:i'),
            $video['platform'],
            $member->name,
            $video['title'] ?? '',
            $video['url'],
            $durationSeconds !== null ? (int) round($durationSeconds / 60) : '',
            $hasTag ? 'あり' : '',
            self::SECTION_LABELS[$sectionType] ?? '',
            $registered ? '済' : '',
            (string) $video['video_id'],
            now()->format('Y-m-d H:i'),
        ];
    }

    private function fetchYoutube(Member $member, Carbon $from, Carbon $to): Collection
    {
        return collect($member->youtubeChannelIds())
            ->push($this->resolveSubChannelId($member))
            ->filter()
            ->unique()
            ->flatMap(fn (string $channelId) => $this->youtube->fetchChannelUploadVideos($channelId, $from, $to))
            // 再生リストは一定件数古い動画が続くまで遡るため、配信開始時刻で改めて期間を絞る。
            ->filter(fn (array $video) => ($video['is_live_archive'] ?? false)
                && $video['published_at']->between($from, $to))
            ->map(fn (array $video) => [
                'platform' => 'youtube',
                'video_id' => $video['video_id'],
                'url' => $video['url'],
                'title' => $video['title'] ?? '',
                'description' => $video['description'] ?? '',
                'published_at' => $video['published_at'],
                'duration_seconds' => $video['duration_seconds'] ?? null,
            ]);
    }

    /**
     * other_urlがYouTube（アルジャン用サブチャンネル等）を指している場合も対象にする
     * （YoutubeArchiveFetcher::resolveSubChannelIdと同じ扱い）。
     */
    private function resolveSubChannelId(Member $member): ?string
    {
        $url = $member->other_url;

        if (!$url || !str_contains(mb_strtolower($url), 'youtu')) {
            return null;
        }

        try {
            return $this->youtube->resolveChannelId($url);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * DB側のURLは手入力もあるため、YouTubeは動画IDで、それ以外はhttp/https・www・末尾スラッシュの
     * 違いを無視して照合する。
     */
    private function normalizeUrl(string $url): string
    {
        $youtubeId = $this->youtube->extractYouTubeVideoId($url);

        if ($youtubeId !== null) {
            return 'youtube:' . $youtubeId;
        }

        return rtrim(preg_replace('#^https?://(www\.)?#i', '', trim($url)), '/');
    }
}
