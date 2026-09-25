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
 * Twitch Helix API (Get Videos) 連携。
 * App Access Token (Client Credentials Grant) で認証し、type=archive の動画のみを取得する。
 */
class TwitchArchiveFetcher implements PlatformArchiveFetcher, SingleVideoLookupFetcher
{
    public function platform(): string
    {
        return 'twitch';
    }

    public function supports(Member $member): bool
    {
        return (bool) $member->twitch_url;
    }

    public function fetchCandidates(Member $member, Carbon $from, Carbon $to, bool $requireArujanTag = true): Collection
    {
        if (!$this->supports($member)) {
            return collect();
        }

        $login = $this->resolveLogin($member->twitch_url);

        if (!$login) {
            throw new RuntimeException("TwitchのユーザーIDが解決できません。member={$member->name} url={$member->twitch_url}");
        }

        $userId = $this->resolveUserId($login);

        if (!$userId) {
            throw new RuntimeException("Twitchユーザーが見つかりません。member={$member->name} login={$login}");
        }

        $videos = collect();
        $cursor = null;

        do {
            $params = [
                'user_id' => $userId,
                'type' => 'archive',
                'first' => 100,
            ];

            if ($cursor) {
                $params['after'] = $cursor;
            }

            $data = $this->callHelix('videos', $params);
            $items = $data['data'] ?? [];

            $reachedFrom = false;

            foreach ($items as $item) {
                // Twitchのcreated_atはUTC(Z)表記のため、明示的にアプリのタイムゾーンに変換する。
                $createdAt = Carbon::parse($item['created_at'])->setTimezone(config('app.timezone', 'Asia/Tokyo'));

                if ($createdAt->lessThan($from)) {
                    $reachedFrom = true;
                    break;
                }

                if ($createdAt->greaterThan($to)) {
                    continue;
                }

                if ($requireArujanTag && !$this->isArujanTaggedVideo($item)) {
                    continue;
                }

                $videos->push([
                    'platform' => $this->platform(),
                    'member_id' => $member->id,
                    'member_name' => $member->name,
                    'video_id' => $item['id'],
                    'url' => $item['url'],
                    'title' => $item['title'] ?? '',
                    'description' => $item['description'] ?? '',
                    'published_at' => $createdAt,
                    'duration_seconds' => $this->parseDurationSeconds($item['duration'] ?? null),
                    'is_live_archive' => true,
                    'date' => $createdAt->toDateString(),
                ]);
            }

            $cursor = $reachedFrom ? null : ($data['pagination']['cursor'] ?? null);
        } while ($cursor);

        return $videos->values();
    }

    /**
     * メンバー個人チャンネルには無関係な配信（ソロ配信・他コラボ等）も混在するため、
     * タイトルか概要欄に「アルジャン」を含む動画のみを対象にする。
     */
    private function isArujanTaggedVideo(array $item): bool
    {
        return str_contains($item['title'] ?? '', 'アルジャン')
            || str_contains(DescriptionBoilerplateStripper::strip($item['description'] ?? ''), 'アルジャン');
    }

    private function resolveLogin(string $twitchUrl): ?string
    {
        $twitchUrl = trim($twitchUrl);

        if ($twitchUrl === '') {
            return null;
        }

        if (!str_starts_with($twitchUrl, 'http')) {
            $twitchUrl = "https://{$twitchUrl}";
        }

        $path = trim(parse_url($twitchUrl, PHP_URL_PATH) ?? '', '/');

        if ($path === '') {
            return null;
        }

        return explode('/', $path)[0];
    }

    public function supportsUrl(string $url): bool
    {
        return $this->extractVideoId($url) !== null;
    }

    public function fetchVideoByUrl(string $url): ?array
    {
        $videoId = $this->extractVideoId($url);

        if (!$videoId) {
            return null;
        }

        // 削除済みVOD（60日超過等）はHelixが404を返すため、エラーではなく「動画なし」として扱う。
        $data = $this->callHelix('videos', ['id' => $videoId], allowNotFound: true);
        $item = $data['data'][0] ?? null;

        if (!$item) {
            return null;
        }

        return [
            'platform' => $this->platform(),
            'url' => $item['url'] ?? $url,
            'title' => $item['title'] ?? '',
            'description' => $item['description'] ?? '',
        ];
    }

    /**
     * TwitchのVOD URL（例: https://www.twitch.tv/videos/1234567890）から動画IDを取り出す。
     */
    private function extractVideoId(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $url)) {
            return $url;
        }

        if (!str_starts_with($url, 'http')) {
            $url = "https://{$url}";
        }

        $host = parse_url($url, PHP_URL_HOST) ?? '';

        if (!str_contains($host, 'twitch.tv')) {
            return null;
        }

        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        $segments = explode('/', $path);
        $index = array_search('videos', $segments, true);

        if ($index !== false && !empty($segments[$index + 1]) && preg_match('/^\d+$/', $segments[$index + 1])) {
            return $segments[$index + 1];
        }

        // 「twitch.tv/{login}/video/{id}」形式（一部のクリップ/共有リンクで使われる）にも対応する。
        $index = array_search('video', $segments, true);

        if ($index !== false && !empty($segments[$index + 1]) && preg_match('/^\d+$/', $segments[$index + 1])) {
            return $segments[$index + 1];
        }

        return null;
    }

    private function resolveUserId(string $login): ?string
    {
        $cacheKey = 'twitch_user_id:' . $login;

        return Cache::remember($cacheKey, now()->addDay(), function () use ($login) {
            $data = $this->callHelix('users', ['login' => $login]);

            return $data['data'][0]['id'] ?? null;
        });
    }

    private function parseDurationSeconds(?string $duration): ?int
    {
        if (!$duration) {
            return null;
        }

        if (!preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $duration, $matches)) {
            return null;
        }

        return ((int) ($matches[1] ?? 0) * 3600)
            + ((int) ($matches[2] ?? 0) * 60)
            + (int) ($matches[3] ?? 0);
    }

    /**
     * YouTube連携（YoutubeMemberArchiveSyncService::fetchYoutubeApi）と同じルールで、
     * APIレート制限節約のためレスポンスを24時間キャッシュする。失敗時はキャッシュせず例外を投げる。
     * $allowNotFound=trueのときだけ404をエラーではなくnull（動画なし）として扱う。
     */
    private function callHelix(string $endpoint, array $params, bool $allowNotFound = false): ?array
    {
        $cacheKey = 'twitch_api:' . $endpoint . ':' . md5(json_encode($params));

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($endpoint, $params, $allowNotFound) {
            $response = Http::withHeaders([
                'Client-Id' => $this->clientId(),
                'Authorization' => "Bearer {$this->appAccessToken()}",
            ])->get("https://api.twitch.tv/helix/{$endpoint}", $params);

            if ($allowNotFound && $response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new RuntimeException("Twitch API呼び出しに失敗しました ({$endpoint}): {$response->body()}");
            }

            return $response->json();
        });
    }

    private function appAccessToken(): string
    {
        return Cache::remember('twitch_app_access_token', now()->addDays(30), function () {
            $response = Http::asForm()->post('https://id.twitch.tv/oauth2/token', [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'grant_type' => 'client_credentials',
            ]);

            if (!$response->successful()) {
                throw new RuntimeException("Twitchのアクセストークン取得に失敗しました: {$response->body()}");
            }

            return $response->json('access_token');
        });
    }

    private function clientId(): string
    {
        $clientId = config('services.twitch.client_id');

        if (!$clientId) {
            throw new RuntimeException('TWITCH_CLIENT_IDが未設定です。');
        }

        return $clientId;
    }

    private function clientSecret(): string
    {
        $clientSecret = config('services.twitch.client_secret');

        if (!$clientSecret) {
            throw new RuntimeException('TWITCH_CLIENT_SECRETが未設定です。');
        }

        return $clientSecret;
    }
}
