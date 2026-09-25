<?php

namespace App\Jobs;

use App\Services\Archive\ParticipantComputationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 「ゲーム内容と参加メンバーを抽出する」ボタンの処理本体（ParticipantComputationService::computeForDate）を
 * バックグラウンドで実行する。古い日付ほどYouTube/Twitchのページング遡りで数分かかることがあり、
 * Webリクエスト内の同期実行だとタイムアウトするためキュー化している。結果はCacheに置き、
 * 画面側はAdminArchiveController::computeParticipantsStatusをポーリングして取得する。
 */
class ComputeArchiveParticipantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        private readonly string $date,
        private readonly string $token,
    ) {
    }

    public static function cacheKey(string $date, string $token): string
    {
        return "compute-participants:{$date}:{$token}";
    }

    public function handle(ParticipantComputationService $service): void
    {
        $timezone = config('app.timezone', 'Asia/Tokyo');
        $eventDayStart = Carbon::parse($this->date, $timezone)->startOfDay();
        $key = self::cacheKey($this->date, $this->token);

        try {
            $sections = $service->computeForDate($eventDayStart);
            $official = $service->findOfficialVideoForDate($eventDayStart);

            Cache::put($key, [
                'status' => 'done',
                'sections' => $sections,
                'official' => $official,
            ], now()->addMinutes(30));
        } catch (Throwable $exception) {
            Log::error('ComputeArchiveParticipantsJob failed', [
                'date' => $this->date,
                'error' => $exception->getMessage(),
            ]);

            Cache::put($key, [
                'status' => 'failed',
                'message' => '抽出処理でエラーが発生しました。YouTube APIのクォータ超過等が考えられます。時間を置いて再度お試しください。',
            ], now()->addMinutes(30));
        }
    }

    public function failed(Throwable $exception): void
    {
        Cache::put(self::cacheKey($this->date, $this->token), [
            'status' => 'failed',
            'message' => '抽出処理でエラーが発生しました。YouTube APIのクォータ超過等が考えられます。時間を置いて再度お試しください。',
        ], now()->addMinutes(30));
    }
}
