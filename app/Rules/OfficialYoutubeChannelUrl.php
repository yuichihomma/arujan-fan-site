<?php

namespace App\Rules;

use App\Services\YoutubeMemberArchiveSyncService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RuntimeException;

class OfficialYoutubeChannelUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $officialChannelId = config('services.youtube.channel_id');

        if (!$officialChannelId) {
            $fail('アルジャン公式チャンネルIDが設定されていません。管理者に連絡してください。');
            return;
        }

        try {
            $video = app(YoutubeMemberArchiveSyncService::class)
                ->fetchVideosByUrls([$value])
                ->first();
        } catch (RuntimeException $e) {
            $fail('YouTube動画の確認中にエラーが発生しました。時間をおいて再度お試しください。');
            return;
        }

        if (!$video || !$video['channel_id']) {
            $fail('動画のチャンネルを確認できませんでした。URLを確認してください。');
            return;
        }

        if ($video['channel_id'] !== $officialChannelId) {
            $fail('アルジャン公式チャンネル以外の動画は登録できません。');
        }
    }
}
