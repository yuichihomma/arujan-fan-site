<?php

namespace App\Services\Archive;

use App\Models\Member;
use App\Services\Archive\Contracts\PlatformArchiveFetcher;
use App\Services\Archive\Contracts\SingleVideoLookupFetcher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OPENREC Public API v5 (public.openrec.tv) 連携。
 * 非公式・無認証で叩ける内部APIで、公式のドキュメントは存在しない。
 * channel_id指定で最新20件までしか取得できず、offset/pageによるページングには対応していない
 * （動作確認済み）ため、直近のアーカイブのみを対象にする。
 */
class OpenrecArchiveFetcher implements PlatformArchiveFetcher, SingleVideoLookupFetcher
{
    public function platform(): string
    {
        return 'openrec';
    }

    public function supports(Member $member): bool
    {
        $platform = mb_strtoupper(trim((string) $member->other_platform));

        return $platform === 'OPENREC' && trim((string) $member->other_url) !== '';
    }

    public function fetchCandidates(Member $member, Carbon $from, Carbon $to, bool $requireArujanTag = true): Collection
    {
        if (!$this->supports($member)) {
            return collect();
        }

        $channelId = $this->resolveChannelId($member->other_url);

        if (!$channelId) {
            throw new RuntimeException("OPENRECのチャンネルIDが解決できません。member={$member->name} url={$member->other_url}");
        }

        $movies = $this->callApi('movies', ['channel_id' => $channelId]) ?? [];

        return collect($movies)
            ->filter(fn (array $movie) => !empty($movie['ended_at']))
            ->map(function (array $movie) {
                $startedAt = Carbon::parse($movie['started_at']);

                return [
                    'platform' => $this->platform(),
                    'video_id' => $movie['id'],
                    'url' => "https://www.openrec.tv/live/{$movie['id']}",
                    'title' => $movie['title'] ?? '',
                    'description' => $movie['introduction'] ?? '',
                    'published_at' => $startedAt,
                    'duration_seconds' => $movie['play_time'] ?? null,
                    'is_live_archive' => true,
                    'date' => $startedAt->toDateString(),
                ];
            })
            ->filter(fn (array $movie) => $movie['published_at']->between($from, $to))
            ->when($requireArujanTag, fn (Collection $movies) => $movies->filter(fn (array $movie) => $this->isArujanTaggedVideo($movie)))
            ->map(fn (array $movie) => [
                ...$movie,
                'member_id' => $member->id,
                'member_name' => $member->name,
            ])
            ->values();
    }

    public function supportsUrl(string $url): bool
    {
        return $this->extractMovieId($url) !== null;
    }

    public function fetchVideoByUrl(string $url): ?array
    {
        $movieId = $this->extractMovieId($url);

        if (!$movieId) {
            return null;
        }

        $movie = $this->callApi("movies/{$movieId}", [], allowNotFound: true);

        if (!$movie || empty($movie['id'])) {
            return null;
        }

        return [
            'platform' => $this->platform(),
            'url' => "https://www.openrec.tv/live/{$movie['id']}",
            'title' => $movie['title'] ?? '',
            'description' => $movie['introduction'] ?? '',
        ];
    }

    /**
     * OPENRECの動画URL（例: https://www.openrec.tv/live/olryv9qoor2）から動画IDを取り出す。
     * MELLOWへのリブランド後ドメイン（mellow-fan.com）のURLにも対応する。
     */
    private function extractMovieId(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (!str_starts_with($url, 'http')) {
            $url = "https://{$url}";
        }

        $host = mb_strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        if (!str_contains($host, 'openrec.tv') && !str_contains($host, 'mellow-fan.com')) {
            return null;
        }

        $segments = explode('/', trim(parse_url($url, PHP_URL_PATH) ?? '', '/'));

        foreach (['live', 'movie'] as $prefix) {
            $index = array_search($prefix, $segments, true);

            if ($index !== false && !empty($segments[$index + 1])) {
                return $segments[$index + 1];
            }
        }

        return null;
    }

    private function isArujanTaggedVideo(array $movie): bool
    {
        return str_contains($movie['title'] ?? '', 'アルジャン')
            || str_contains(DescriptionBoilerplateStripper::strip($movie['description'] ?? ''), 'アルジャン');
    }

    private function resolveChannelId(string $otherUrl): ?string
    {
        $otherUrl = trim($otherUrl);

        if ($otherUrl === '') {
            return null;
        }

        if (!str_starts_with($otherUrl, 'http')) {
            $otherUrl = "https://{$otherUrl}";
        }

        $path = trim(parse_url($otherUrl, PHP_URL_PATH) ?? '', '/');
        $segments = explode('/', $path);

        return end($segments) ?: null;
    }

    /**
     * YouTube連携（YoutubeMemberArchiveSyncService::fetchYoutubeApi）と同じルールで、
     * APIレート制限節約のためレスポンスを24時間キャッシュする。失敗時はキャッシュせず例外を投げる。
     * $allowNotFound=trueのときだけ404をエラーではなくnull（動画なし）として扱う。
     */
    private function callApi(string $endpoint, array $params, bool $allowNotFound = false): ?array
    {
        $cacheKey = 'openrec_api:' . $endpoint . ':' . md5(json_encode($params));

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($endpoint, $params, $allowNotFound) {
            $response = Http::get("https://public.openrec.tv/external/api/v5/{$endpoint}", $params);

            if ($allowNotFound && $response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new RuntimeException("OPENREC API呼び出しに失敗しました ({$endpoint}): {$response->body()}");
            }

            return $response->json() ?? [];
        });
    }
}
