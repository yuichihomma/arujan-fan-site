<?php

namespace Tests\Feature\Archive;

use App\Models\Member;
use App\Services\Archive\Contracts\PlatformArchiveFetcher;
use App\Services\Archive\ParticipantComputationService;
use App\Services\Archive\ParticipantNameExtractor;
use App\Services\GameGenreInferenceService;
use App\Services\YoutubeMemberArchiveSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ParticipantComputationServiceSoloExclusionTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(Collection $videosByMemberId): ParticipantComputationService
    {
        $fetcher = new class($videosByMemberId) implements PlatformArchiveFetcher {
            public function __construct(private readonly Collection $videosByMemberId)
            {
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
        };

        return new ParticipantComputationService(
            [$fetcher],
            new ParticipantNameExtractor(),
            new GameGenreInferenceService(),
            new YoutubeMemberArchiveSyncService(),
        );
    }

    public function test_solo_video_with_no_other_member_named_is_excluded(): void
    {
        $solo = Member::create(['name' => 'オシオン', 'youtube_channel_id' => 'UC_solo']);

        $eventDayStart = Carbon::parse('2026-07-15 00:00:00');
        $publishedAt = $eventDayStart->copy()->setTime(22, 0);

        $service = $this->makeService(collect([
            $solo->id => [[
                'video_id' => 'solo1',
                'url' => 'https://youtu.be/solo1',
                'title' => 'アルジャン所属オシオンのソロ雑談',
                'description' => '雑談配信です',
                'published_at' => $publishedAt,
                'is_live_archive' => true,
                'date' => $publishedAt->toDateString(),
            ]],
        ]));

        $result = $service->computeForDate($eventDayStart);

        $this->assertSame([], $result['primary']['members']);
        $this->assertNull($result['primary']['genre']);
    }

    public function test_two_members_uploading_independently_in_same_window_are_both_included(): void
    {
        $memberA = Member::create(['name' => 'メンバーA', 'youtube_channel_id' => 'UC_a']);
        $memberB = Member::create(['name' => 'メンバーB', 'youtube_channel_id' => 'UC_b']);

        $eventDayStart = Carbon::parse('2026-07-15 00:00:00');
        $publishedAt = $eventDayStart->copy()->setTime(22, 0);

        $service = $this->makeService(collect([
            $memberA->id => [[
                'video_id' => 'a1',
                'url' => 'https://youtu.be/a1',
                'title' => 'アルジャン Among Us',
                'description' => '',
                'published_at' => $publishedAt,
                'is_live_archive' => true,
                'date' => $publishedAt->toDateString(),
            ]],
            $memberB->id => [[
                'video_id' => 'b1',
                'url' => 'https://youtu.be/b1',
                'title' => 'アルジャン Among Us',
                'description' => '',
                'published_at' => $publishedAt->copy()->addMinutes(5),
                'is_live_archive' => true,
                'date' => $publishedAt->toDateString(),
            ]],
        ]));

        $result = $service->computeForDate($eventDayStart);

        $this->assertEqualsCanonicalizing(
            ['メンバーA', 'メンバーB'],
            collect($result['primary']['members'])->pluck('name')->all()
        );
    }

    public function test_solo_video_naming_another_member_includes_both(): void
    {
        $solo = Member::create(['name' => 'オシオン', 'youtube_channel_id' => 'UC_solo']);
        $named = Member::create(['name' => 'ナツピョン', 'youtube_channel_id' => 'UC_named']);

        $eventDayStart = Carbon::parse('2026-07-15 00:00:00');
        $publishedAt = $eventDayStart->copy()->setTime(22, 0);

        $service = $this->makeService(collect([
            $solo->id => [[
                'video_id' => 'solo2',
                'url' => 'https://youtu.be/solo2',
                'title' => 'アルジャン',
                'description' => '今日のメンバー：オシオン、ナツピョン',
                'published_at' => $publishedAt,
                'is_live_archive' => true,
                'date' => $publishedAt->toDateString(),
            ]],
        ]));

        $result = $service->computeForDate($eventDayStart);

        $this->assertEqualsCanonicalizing(
            ['オシオン', 'ナツピョン'],
            collect($result['primary']['members'])->pluck('name')->all()
        );
    }
}
