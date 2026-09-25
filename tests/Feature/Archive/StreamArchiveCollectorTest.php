<?php

namespace Tests\Feature\Archive;

use App\Models\ArchiveSection;
use App\Models\Member;
use App\Models\Onedayarchive;
use App\Services\Archive\GoogleSheetsStreamArchiveStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StreamArchiveCollectorTest extends TestCase
{
    use RefreshDatabase;

    private GoogleSheetsStreamArchiveStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.youtube.api_key' => 'test-key']);

        // 実際のスプシの代わりに、追記された行をメモリ上に貯めるだけのフェイク。
        $this->store = new class extends GoogleSheetsStreamArchiveStore {
            public array $rows = [];

            public function existingKeys(): Collection
            {
                return collect($this->rows)->map(fn (array $row) => $row[2] . ':' . $row[10]);
            }

            public function appendRows(Collection $rows): void
            {
                array_push($this->rows, ...$rows->values()->all());
            }
        };
        $this->app->instance(GoogleSheetsStreamArchiveStore::class, $this->store);
    }

    public function test_appends_all_live_archives_to_sheet_without_duplicates(): void
    {
        $member = Member::create(['name' => 'テスト配信者', 'youtube_channel_id' => 'UC_test']);

        $section = ArchiveSection::create([
            'onedayarchive_id' => Onedayarchive::create(['event_date' => '2022-12-30'])->id,
            'section_type' => 'secondary',
        ]);
        $section->members()->attach($member->id, ['video_url' => 'https://youtu.be/tagged01']);

        $this->fakeYoutube();
        $this->artisan('archives:collect-streams', ['--from' => '2022-12-01', '--to' => '2023-01-31'])
            ->assertExitCode(0);

        $rows = array_map(fn (array $row) => array_slice($row, 0, 11), $this->store->rows);

        $this->assertSame([
            // 2022-12-31 01:00 JST開始 → 前日(12/30)のイベントの2次会。DB登録済み
            ['2022-12-30', '2022-12-31 01:00', 'youtube', 'テスト配信者', '【アルジャン】2次会', 'https://www.youtube.com/watch?v=tagged01', 120, 'あり', '2次会', '済', 'tagged01'],
            // タグなしの配信も貯める
            ['2023-01-05', '2023-01-05 21:30', 'youtube', 'テスト配信者', 'ソロ配信', 'https://www.youtube.com/watch?v=untagged1', 120, '', '1次会', '', 'untagged1'],
        ], $rows);

        // 期間を重ねて再実行しても、既にシートにある配信は追記しない。
        Cache::flush();
        $this->artisan('archives:collect-streams', ['--from' => '2022-12-01', '--to' => '2023-01-31'])
            ->assertExitCode(0);

        $this->assertCount(2, $this->store->rows);
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
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [
                $this->liveVideo('tagged01', '【アルジャン】2次会', '2022-12-30T16:00:00Z'),
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
