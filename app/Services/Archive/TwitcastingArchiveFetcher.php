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
 * TwitCasting API (apiv2) 連携。
 * apiv2は client_credentials grantを提供していないため、サイト管理者アカウントで
 * 一度だけ Authorization Code Grant を実行して得たアクセストークン（services.twitcasting.access_token）
 * を使い回して、任意ユーザーの動画一覧（公開情報）を取得する。
 */
class TwitcastingArchiveFetcher implements PlatformArchiveFetcher, SingleVideoLookupFetcher
{
    private const PAGE_LIMIT = 20;

    public function platform(): string
    {
        return 'twitcasting';
    }

    public function supports(Member $member): bool
    {
        $url = mb_strtolower((string) $member->other_url);

        return $url !== '' && (str_contains($url, 'twitcasting.tv') || str_contains($url, 'twicas'));
    }

    public function fetchCandidates(Member $member, Carbon $from, Carbon $to, bool $requireArujanTag = true): Collection
    {
        if (!$this->supports($member)) {
            return collect();
        }

        $screenId = $this->resolveScreenId($member->other_url);

        if (!$screenId) {
            throw new RuntimeException("ツイキャスのユーザーIDが解決できません。member={$member->name} url={$member->other_url}");
        }

        $videos = collect();
        $offset = 0;

        do {
            $data = $this->callApi("users/{$screenId}/movies", [
                'offset' => $offset,
                'limit' => self::PAGE_LIMIT,
            ]);

            $movies = $data['movies'] ?? [];
            $reachedFrom = false;

            foreach ($movies as $movie) {
                if (!($movie['is_recorded'] ?? false)) {
                    continue;
                }

                // createFromTimestamp()は指定しないとUTCになるため、明示的にアプリのタイムゾーンに変換する。
                $publishedAt = Carbon::createFromTimestamp($movie['created'], config('app.timezone', 'Asia/Tokyo'));

                if ($publishedAt->lessThan($from)) {
                    $reachedFrom = true;
                    break;
                }

                if ($publishedAt->greaterThan($to)) {
                    continue;
                }

                if ($requireArujanTag && !$this->isArujanTaggedVideo($movie)) {
                    continue;
                }

                $videos->push([
                    'platform' => $this->platform(),
                    'member_id' => $member->id,
                    'member_name' => $member->name,
                    'video_id' => $movie['id'],
                    'url' => $movie['link'],
                    'title' => $movie['title'] ?? '',
                    'description' => trim(($movie['subtitle'] ?? '') . ' ' . ($movie['last_owner_comment'] ?? '')),
                    'published_at' => $publishedAt,
                    'duration_seconds' => $movie['duration'] ?? null,
                    'is_live_archive' => true,
                    'date' => $publishedAt->toDateString(),
                ]);
            }

            $offset += self::PAGE_LIMIT;
        } while (!$reachedFrom && count($movies) === self::PAGE_LIMIT);

        return $videos->values();
    }

    /**
     * メンバー個人アカウントには無関係な配信（ソロ配信・他コラボ等）も混在するため、
     * タイトルか概要欄に「アルジャン」を含む動画のみを対象にする。
     */
    private function isArujanTaggedVideo(array $movie): bool
    {
        return str_contains($movie['title'] ?? '', 'アルジャン')
            || str_contains(DescriptionBoilerplateStripper::strip($movie['subtitle'] ?? ''), 'アルジャン')
            || str_contains(DescriptionBoilerplateStripper::strip($movie['last_owner_comment'] ?? ''), 'アルジャン');
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

        $data = $this->callApi("movies/{$movieId}", [], allowNotFound: true);
        $movie = $data['movie'] ?? null;

        if (!$movie) {
            return null;
        }

        return [
            'platform' => $this->platform(),
            'url' => $movie['link'] ?? $url,
            'title' => $movie['title'] ?? '',
            'description' => trim(($movie['subtitle'] ?? '') . ' ' . ($movie['last_owner_comment'] ?? '')),
        ];
    }

    /**
     * ツイキャスの動画URL（例: https://twitcasting.tv/c:noristrycas/movie/840916481）から動画IDを取り出す。
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

        if (!str_contains($host, 'twitcasting.tv')) {
            return null;
        }

        $segments = explode('/', trim(parse_url($url, PHP_URL_PATH) ?? '', '/'));
        $index = array_search('movie', $segments, true);

        if ($index !== false && !empty($segments[$index + 1]) && preg_match('/^\d+$/', $segments[$index + 1])) {
            return $segments[$index + 1];
        }

        return null;
    }

    private function resolveScreenId(string $otherUrl): ?string
    {
        $otherUrl = trim($otherUrl);

        if ($otherUrl === '') {
            return null;
        }

        if (!str_starts_with($otherUrl, 'http')) {
            $otherUrl = "https://{$otherUrl}";
        }

        $path = trim(parse_url($otherUrl, PHP_URL_PATH) ?? '', '/');

        if ($path === '') {
            return null;
        }

        return explode('/', $path)[0];
    }

    /**
     * YouTube連携（YoutubeMemberArchiveSyncService::fetchYoutubeApi）と同じルールで、
     * APIレート制限節約のためレスポンスを24時間キャッシュする。失敗時はキャッシュせず例外を投げる。
     * $allowNotFound=trueのときだけ404をエラーではなくnull（動画なし）として扱う。
     */
    private function callApi(string $endpoint, array $params, bool $allowNotFound = false): ?array
    {
        $cacheKey = 'twitcasting_api:' . $endpoint . ':' . md5(json_encode($params));

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($endpoint, $params, $allowNotFound) {
            $response = Http::withToken($this->accessToken())
                ->withHeaders(['X-Api-Version' => '2.0'])
                ->get("https://apiv2.twitcasting.tv/{$endpoint}", $params);

            if ($allowNotFound && $response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new RuntimeException("ツイキャスAPI呼び出しに失敗しました ({$endpoint}): {$response->body()}");
            }

            return $response->json();
        });
    }

    private function accessToken(): string
    {
        $token = config('services.twitcasting.access_token');

        if (!$token) {
            throw new RuntimeException(
                'TWITCASTING_ACCESS_TOKENが未設定です。管理者アカウントでAuthorization Code Grantを再実行してください。'
            );
        }

        return $token;
    }
}
