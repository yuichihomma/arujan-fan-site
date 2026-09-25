<?php

namespace App\Services\Archive;

use App\Models\Member;
use App\Models\StreamArchive;
use App\Services\Archive\Contracts\PlatformArchiveFetcher;
use App\Services\YoutubeMemberArchiveSyncService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 全メンバーの生配信アーカイブを「アルジャンタグの有無に関わらず」stream_archivesに貯め、
 * そこからスプシ仕分け用のCSVを書き出す。
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
    public const CSV_HEADERS = [
        'イベント日',
        '配信開始',
        'プラットフォーム',
        '配信者',
        'タイトル',
        'URL',
        '長さ(分)',
        'アルジャンタグ',
        '推定区分',
        'DB登録済み',
    ];

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
        private readonly array $otherFetchers,
    ) {
    }

    /**
     * 指定期間の生配信アーカイブを取得してstream_archivesに保存する。platform+video_idで
     * upsertするため、何度実行しても重複せず、タイトル等が変わっていれば最新の値で上書きされる。
     *
     * @param callable(string $member, string $platform, string $message): void $onError
     * @return int 保存（新規＋更新）した件数
     */
    public function import(Collection $members, Carbon $from, Carbon $to, callable $onError): int
    {
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

            // メンバーごとに保存し、途中でAPIが失敗してもそこまでの取得分は残す。
            $saved += $this->store($member, $videos);
        }

        return $saved;
    }

    /**
     * stream_archivesの内容を、Excelで開いても文字化けしないUTF-8 BOM付きCSVで書き出す。
     *
     * @return int 書き出した件数
     */
    public function exportCsv(string $path, ?Carbon $from = null, ?Carbon $to = null): int
    {
        $registeredUrls = DB::table('archive_section_member')
            ->whereNotNull('video_url')
            ->pluck('video_url')
            ->map(fn (string $url) => $this->normalizeUrl($url))
            ->flip();

        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::CSV_HEADERS, escape: '');

        $count = 0;

        StreamArchive::query()
            ->with('member')
            ->when($from, fn ($query) => $query->whereDate('event_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('event_date', '<=', $to->toDateString()))
            ->orderBy('started_at')
            ->orderBy('id')
            ->each(function (StreamArchive $archive) use ($handle, $registeredUrls, &$count) {
                fputcsv($handle, [
                    $archive->event_date->toDateString(),
                    $archive->started_at->format('Y-m-d H:i'),
                    $archive->platform,
                    $archive->member?->name ?? '',
                    $archive->title,
                    $archive->url,
                    $archive->duration_seconds !== null ? (int) round($archive->duration_seconds / 60) : '',
                    $archive->has_arujan_tag ? 'あり' : '',
                    self::SECTION_LABELS[$archive->estimated_section_type] ?? '',
                    $registeredUrls->has($this->normalizeUrl($archive->url)) ? '済' : '',
                ], escape: '');
                $count++;
            });

        fclose($handle);

        return $count;
    }

    private function store(Member $member, Collection $videos): int
    {
        $timezone = config('app.timezone', 'Asia/Tokyo');
        $now = now();

        $rows = $videos
            ->unique(fn (array $video) => $video['platform'] . ':' . $video['video_id'])
            ->map(function (array $video) use ($member, $timezone, $now) {
                /** @var Carbon $startedAt */
                $startedAt = $video['published_at']->copy()->setTimezone($timezone);
                $eventDayStart = $startedAt->copy()->startOfDay();

                if ($startedAt->hour < self::EVENT_DAY_BOUNDARY_HOUR) {
                    $eventDayStart->subDay();
                }

                return [
                    'platform' => $video['platform'],
                    'video_id' => (string) $video['video_id'],
                    'member_id' => $member->id,
                    'url' => $video['url'],
                    'title' => mb_substr($video['title'] ?? '', 0, 255),
                    'description' => $video['description'] ?? '',
                    'started_at' => $startedAt->format('Y-m-d H:i:s'),
                    'duration_seconds' => $video['duration_seconds'] ?? null,
                    'event_date' => $eventDayStart->toDateString(),
                    'estimated_section_type' => SectionTimeWindows::windowContaining($eventDayStart, $startedAt),
                    'has_arujan_tag' => str_contains($video['title'] ?? '', 'アルジャン')
                        || str_contains(DescriptionBoilerplateStripper::strip($video['description'] ?? ''), 'アルジャン'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values();

        if ($rows->isEmpty()) {
            return 0;
        }

        $rows->chunk(200)->each(fn (Collection $chunk) => StreamArchive::upsert(
            $chunk->values()->all(),
            ['platform', 'video_id'],
            ['member_id', 'url', 'title', 'description', 'started_at', 'duration_seconds', 'event_date', 'estimated_section_type', 'has_arujan_tag', 'updated_at']
        ));

        return $rows->count();
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
