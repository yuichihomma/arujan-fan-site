<?php

namespace Tests\Feature\Archive;

use App\Models\ArchiveSectionAbsentMember;
use App\Models\Member;
use App\Services\Archive\Contracts\PlatformArchiveFetcher;
use App\Services\Archive\Contracts\SingleVideoLookupFetcher;
use App\Services\Archive\ParticipantComputationService;
use App\Services\Archive\ParticipantNameExtractor;
use App\Services\GameGenreInferenceService;
use App\Services\YoutubeMemberArchiveSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\TestCase;

class ParticipantExtractionFromVideoUrlTest extends TestCase
{
    use RefreshDatabase;

    private function makeFetcher(
        array $videoByUrl,
        Collection $videosByMemberId,
        ?\Throwable $fetchException = null
    ): PlatformArchiveFetcher {
        return new class($videoByUrl, $videosByMemberId, $fetchException) implements PlatformArchiveFetcher, SingleVideoLookupFetcher {
            public function __construct(
                private readonly array $videoByUrl,
                private readonly Collection $videosByMemberId,
                private readonly ?\Throwable $fetchException
            ) {
            }

            public function platform(): string
            {
                return 'youtube';
            }

            public function supports(Member $member): bool
            {
                return $this->videosByMemberId->has($member->id);
            }

            public function fetchCandidates(Member $member, Carbon $from, Carbon $to, bool $requireArujanTag = true): Collection
            {
                return collect($this->videosByMemberId->get($member->id, []))
                    ->map(fn (array $video) => array_merge($video, [
                        'platform' => 'youtube',
                        'member_id' => $member->id,
                        'member_name' => $member->name,
                    ]))
                    ->values();
            }

            public function supportsUrl(string $url): bool
            {
                return array_key_exists($url, $this->videoByUrl);
            }

            public function fetchVideoByUrl(string $url): ?array
            {
                if ($this->fetchException) {
                    throw $this->fetchException;
                }

                $data = $this->videoByUrl[$url] ?? null;

                if (!$data) {
                    return null;
                }

                return array_merge(['platform' => 'youtube', 'url' => $url], $data);
            }
        };
    }

    private function makeService(PlatformArchiveFetcher $fetcher): ParticipantComputationService
    {
        return new ParticipantComputationService(
            [$fetcher],
            new ParticipantNameExtractor(),
            new GameGenreInferenceService(),
            new YoutubeMemberArchiveSyncService(),
        );
    }

    public function test_unsupported_url_returns_error(): void
    {
        $fetcher = $this->makeFetcher([], collect());
        $service = $this->makeService($fetcher);

        $result = $service->extractParticipantsFromVideoUrl(
            'https://example.com/video/1',
            'primary',
            Carbon::parse('2026-07-15 00:00:00'),
            1
        );

        $this->assertSame('unsupported_platform', $result['error']);
    }

    public function test_video_fetch_exception_returns_error(): void
    {
        $fetcher = $this->makeFetcher(
            ['https://youtu.be/abc' => ['title' => '', 'description' => '']],
            collect(),
            new RuntimeException('quota exceeded')
        );
        $service = $this->makeService($fetcher);

        $result = $service->extractParticipantsFromVideoUrl(
            'https://youtu.be/abc',
            'primary',
            Carbon::parse('2026-07-15 00:00:00'),
            1
        );

        $this->assertSame('fetch_failed', $result['error']);
    }

    public function test_video_not_found_returns_error(): void
    {
        // supportsUrlはtrue（=対応プラットフォームのURL）だが、fetchVideoByUrlがnull（=動画が見つからない）を返すケース。
        $fetcher = new class implements PlatformArchiveFetcher, SingleVideoLookupFetcher {
            public function platform(): string
            {
                return 'youtube';
            }

            public function supports(Member $member): bool
            {
                return false;
            }

            public function fetchCandidates(Member $member, Carbon $from, Carbon $to, bool $requireArujanTag = true): Collection
            {
                return collect();
            }

            public function supportsUrl(string $url): bool
            {
                return true;
            }

            public function fetchVideoByUrl(string $url): ?array
            {
                return null;
            }
        };

        $service = $this->makeService($fetcher);

        $result = $service->extractParticipantsFromVideoUrl(
            'https://youtu.be/deleted',
            'primary',
            Carbon::parse('2026-07-15 00:00:00'),
            1
        );

        $this->assertSame('video_not_found', $result['error']);
    }

    public function test_no_names_detected_returns_empty_result(): void
    {
        $uploader = Member::create(['name' => 'オシオン', 'youtube_channel_id' => 'UC_uploader']);

        $fetcher = $this->makeFetcher(
            ['https://youtu.be/v1' => ['title' => 'アルジャン', 'description' => '雑談配信でした']],
            collect()
        );
        $service = $this->makeService($fetcher);

        $result = $service->extractParticipantsFromVideoUrl(
            'https://youtu.be/v1',
            'primary',
            Carbon::parse('2026-07-15 00:00:00'),
            $uploader->id
        );

        $this->assertSame([], $result['members']);
        $this->assertSame([], $result['unverified_names']);
    }

    public function test_named_member_without_own_confirmed_video_is_unverified(): void
    {
        $uploader = Member::create(['name' => 'オシオン', 'youtube_channel_id' => 'UC_uploader']);
        $named = Member::create(['name' => 'ナツピョン', 'youtube_channel_id' => 'UC_named']);

        $fetcher = $this->makeFetcher(
            ['https://youtu.be/v1' => ['title' => 'アルジャン', 'description' => '今日のメンバー：オシオン、ナツピョン']],
            collect() // ナツピョン本人の配信は見つからない
        );
        $service = $this->makeService($fetcher);

        $result = $service->extractParticipantsFromVideoUrl(
            'https://youtu.be/v1',
            'primary',
            Carbon::parse('2026-07-15 00:00:00'),
            $uploader->id
        );

        $this->assertSame([], $result['members']);
        $this->assertSame(['ナツピョン'], collect($result['unverified_names'])->pluck('name')->all());
        $this->assertSame($named->id, $result['unverified_names'][0]['id']);
    }

    public function test_named_member_with_own_confirmed_video_is_verified_and_exclusions_apply(): void
    {
        $uploader = Member::create(['name' => 'オシオン', 'youtube_channel_id' => 'UC_uploader']);
        $verified = Member::create(['name' => 'ナツピョン', 'youtube_channel_id' => 'UC_named']);
        $alreadySelected = Member::create(['name' => 'ハッチャン', 'youtube_channel_id' => 'UC_hatchan']);
        $markedAbsent = Member::create(['name' => 'バブルケーキ', 'youtube_channel_id' => 'UC_bubble']);

        $eventDayStart = Carbon::parse('2026-07-15 00:00:00');
        $publishedAt = $eventDayStart->copy()->setTime(21, 30);

        ArchiveSectionAbsentMember::create([
            'event_date' => $eventDayStart->toDateString(),
            'section_type' => 'primary',
            'member_id' => $markedAbsent->id,
        ]);

        $fetcher = $this->makeFetcher(
            ['https://youtu.be/v1' => [
                'title' => 'アルジャン',
                'description' => '今日のメンバー：オシオン、ナツピョン、ハッチャン、バブルケーキ',
            ]],
            collect([
                $verified->id => [[
                    'video_id' => 'named1',
                    'url' => 'https://youtu.be/named1',
                    'title' => 'アルジャン',
                    'description' => '',
                    'published_at' => $publishedAt,
                    'duration_seconds' => 3600,
                    'is_live_archive' => true,
                    'date' => $publishedAt->toDateString(),
                ]],
                $alreadySelected->id => [[
                    'video_id' => 'hatchan1',
                    'url' => 'https://youtu.be/hatchan1',
                    'title' => 'アルジャン',
                    'description' => '',
                    'published_at' => $publishedAt,
                    'duration_seconds' => 3600,
                    'is_live_archive' => true,
                    'date' => $publishedAt->toDateString(),
                ]],
                $markedAbsent->id => [[
                    'video_id' => 'bubble1',
                    'url' => 'https://youtu.be/bubble1',
                    'title' => 'アルジャン',
                    'description' => '',
                    'published_at' => $publishedAt,
                    'duration_seconds' => 3600,
                    'is_live_archive' => true,
                    'date' => $publishedAt->toDateString(),
                ]],
            ])
        );
        $service = $this->makeService($fetcher);

        $result = $service->extractParticipantsFromVideoUrl(
            'https://youtu.be/v1',
            'primary',
            $eventDayStart,
            $uploader->id,
            [$alreadySelected->id]
        );

        $this->assertSame(['ナツピョン'], collect($result['members'])->pluck('name')->all());
        $this->assertSame('https://youtu.be/named1', $result['members'][0]['video_url']);
        $this->assertSame([], $result['unverified_names']);
    }

    public function test_special_section_uses_genre_matching_instead_of_time_window(): void
    {
        $uploader = Member::create(['name' => 'オシオン', 'youtube_channel_id' => 'UC_uploader']);
        $verified = Member::create(['name' => 'ナツピョン', 'youtube_channel_id' => 'UC_named']);

        $eventDayStart = Carbon::parse('2026-07-15 00:00:00');

        $fetcher = $this->makeFetcher(
            ['https://youtu.be/v1' => ['title' => '特別回', 'description' => '今日のメンバー：オシオン、ナツピョン']],
            collect([
                $verified->id => [[
                    'video_id' => 'named1',
                    'url' => 'https://youtu.be/named1',
                    'title' => '雀魂やります',
                    'description' => '',
                    'published_at' => $eventDayStart->copy()->setTime(23, 0),
                    'duration_seconds' => 3600,
                    'is_live_archive' => true,
                    'date' => $eventDayStart->toDateString(),
                ]],
            ])
        );
        $service = $this->makeService($fetcher);

        // 'special'はSectionTimeWindows::WINDOWS_MINUTESに無いため、時間帯ではなく
        // $gameGenre（'雀魂'）とのジャンル一致で本人確認される。
        $result = $service->extractParticipantsFromVideoUrl(
            'https://youtu.be/v1',
            'special',
            $eventDayStart,
            $uploader->id,
            [],
            '雀魂'
        );

        $this->assertSame(['ナツピョン'], collect($result['members'])->pluck('name')->all());
        $this->assertSame('https://youtu.be/named1', $result['members'][0]['video_url']);
    }

    public function test_special_section_without_game_genre_leaves_candidates_unverified(): void
    {
        $uploader = Member::create(['name' => 'オシオン', 'youtube_channel_id' => 'UC_uploader']);
        $named = Member::create(['name' => 'ナツピョン', 'youtube_channel_id' => 'UC_named']);

        $eventDayStart = Carbon::parse('2026-07-15 00:00:00');

        $fetcher = $this->makeFetcher(
            ['https://youtu.be/v1' => ['title' => '特別回', 'description' => '今日のメンバー：オシオン、ナツピョン']],
            collect([
                $named->id => [[
                    'video_id' => 'named1',
                    'url' => 'https://youtu.be/named1',
                    'title' => '雀魂やります',
                    'description' => '',
                    'published_at' => $eventDayStart->copy()->setTime(23, 0),
                    'duration_seconds' => 3600,
                    'is_live_archive' => true,
                    'date' => $eventDayStart->toDateString(),
                ]],
            ])
        );
        $service = $this->makeService($fetcher);

        $result = $service->extractParticipantsFromVideoUrl(
            'https://youtu.be/v1',
            'special',
            $eventDayStart,
            $uploader->id,
            [],
            null // ゲームジャンル未入力
        );

        $this->assertSame([], $result['members']);
        $this->assertSame(['ナツピョン'], collect($result['unverified_names'])->pluck('name')->all());
    }
}
