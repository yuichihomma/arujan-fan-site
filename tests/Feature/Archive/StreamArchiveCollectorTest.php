<?php

namespace Tests\Feature\Archive;

use App\Models\ArchiveSection;
use App\Models\Member;
use App\Models\Onedayarchive;
use App\Models\StreamArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StreamArchiveCollectorTest extends TestCase
{
    use RefreshDatabase;

    private string $taggedTitle = '【アルジャン】2次会';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.youtube.api_key' => 'test-key']);
    }

    public function test_collects_all_live_archives_and_upserts_on_rerun(): void
    {
        Member::create(['name' => 'テスト配信者', 'youtube_channel_id' => 'UC_test']);

        $this->fakeYoutube();
        $this->artisan('archives:collect-streams', ['--from' => '2022-12-01', '--to' => '2023-01-31'])
            ->assertExitCode(0);

        $this->assertSame(2, StreamArchive::count());

        $tagged = StreamArchive::where('video_id', 'tagged01')->first();
        // 2022-12-31 01:00 JST開始 → 前日(12/30)のイベントの2次会
        $this->assertSame('2022-12-30', $tagged->event_date->toDateString());
        $this->assertSame('secondary', $tagged->estimated_section_type);
        $this->assertTrue($tagged->has_arujan_tag);

        $untagged = StreamArchive::where('video_id', 'untagged1')->first();
        $this->assertSame('primary', $untagged->estimated_section_type);
        $this->assertFalse($untagged->has_arujan_tag);

        // 再実行しても重複せず、変わったタイトルは上書きされる。
        Cache::flush();
        $this->taggedTitle = '【アルジャン】2次会（改題）';
        $this->artisan('archives:collect-streams', ['--from' => '2022-12-01', '--to' => '2023-01-31'])
            ->assertExitCode(0);

        $this->assertSame(2, StreamArchive::count());
        $this->assertSame('【アルジャン】2次会（改題）', StreamArchive::where('video_id', 'tagged01')->value('title'));
    }

    public function test_exports_csv_with_registration_column(): void
    {
        $member = Member::create(['name' => 'テスト配信者', 'youtube_channel_id' => 'UC_test']);

        $section = ArchiveSection::create([
            'onedayarchive_id' => Onedayarchive::create(['event_date' => '2022-12-30'])->id,
            'section_type' => 'secondary',
        ]);
        $section->members()->attach($member->id, ['video_url' => 'https://youtu.be/tagged01']);

        $this->fakeYoutube();
        $this->artisan('archives:collect-streams', ['--from' => '2022-12-01', '--to' => '2023-01-31']);

        $output = storage_path('framework/testing/stream-archives.csv');

        $this->artisan('archives:export-streams', ['--output' => $output])->assertExitCode(0);

        $lines = array_map('str_getcsv', file($output, FILE_IGNORE_NEW_LINES));
        @unlink($output);

        $this->assertSame("\xEF\xBB\xBFイベント日", $lines[0][0]);
        $this->assertCount(3, $lines);
        $this->assertSame(
            ['2022-12-30', '2022-12-31 01:00', 'youtube', 'テスト配信者', '【アルジャン】2次会', 'https://www.youtube.com/watch?v=tagged01', '120', 'あり', '2次会', '済'],
            $lines[1]
        );
        $this->assertSame(
            ['2023-01-05', '2023-01-05 21:30', 'youtube', 'テスト配信者', 'ソロ配信', 'https://www.youtube.com/watch?v=untagged1', '120', '', '1次会', ''],
            $lines[2]
        );
    }

    private function fakeYoutube(): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => [
                $this->playlistItem('tagged01', '2022-12-30T16:00:00Z'),
                $this->playlistItem('untagged1', '2023-01-05T12:00:00Z'),
                $this->playlistItem('uploaded1', '2023-01-06T12:00:00Z'),
                $this->playlistItem('tooold01', '2022-11-01T12:00:00Z'),
            ]]),
            'www.googleapis.com/youtube/v3/videos*' => fn () => Http::response(['items' => [
                $this->liveVideo('tagged01', $this->taggedTitle, '2022-12-30T16:00:00Z'),
                // タグなしの配信も貯める
                $this->liveVideo('untagged1', 'ソロ配信', '2023-01-05T12:30:00Z'),
                // 生配信ではない通常アップロードは除外
                ['id' => 'uploaded1', 'snippet' => ['title' => '切り抜き', 'publishedAt' => '2023-01-06T12:00:00Z'], 'contentDetails' => ['duration' => 'PT5M']],
                // 期間外は除外
                $this->liveVideo('tooold01', '【アルジャン】古い回', '2022-11-01T12:00:00Z'),
            ]]),
        ]);
    }

    private function playlistItem(string $videoId, string $publishedAt): array
    {
        return [
            'snippet' => ['publishedAt' => $publishedAt],
            'contentDetails' => ['videoId' => $videoId, 'videoPublishedAt' => $publishedAt],
        ];
    }

    private function liveVideo(string $videoId, string $title, string $startedAt): array
    {
        return [
            'id' => $videoId,
            'snippet' => ['title' => $title, 'description' => '', 'channelId' => 'UC_test', 'publishedAt' => $startedAt],
            'contentDetails' => ['duration' => 'PT2H'],
            'liveStreamingDetails' => ['actualStartTime' => $startedAt],
        ];
    }
}
