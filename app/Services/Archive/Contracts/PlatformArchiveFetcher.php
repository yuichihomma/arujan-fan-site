<?php

namespace App\Services\Archive\Contracts;

use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface PlatformArchiveFetcher
{
    /**
     * このフェッチャーが対応するプラットフォーム識別子（youtube/twitch/twitcasting）。
     */
    public function platform(): string;

    /**
     * このメンバーがこのプラットフォームにアカウント登録済みか。
     */
    public function supports(Member $member): bool;

    /**
     * 指定期間のアーカイブ候補を取得する。正規化済みの連想配列のコレクションを返す。
     * 各要素: platform, member_id, member_name, video_id, url, title,
     *         published_at(Carbon|null), duration_seconds(int|null), is_live_archive(bool), date(Y-m-d)
     *
     * $requireArujanTagをfalseにすると、タイトル/概要欄に「アルジャン」を含むかの判定をスキップする。
     * 呼び出し側が別の手段（他メンバーの動画からの参加者抽出など）で既にアルジャン参加をほぼ確定できている
     * 場合に、本人の投稿に「アルジャン」の文字列が無いだけの動画も拾えるようにするためのオプション。
     */
    public function fetchCandidates(Member $member, Carbon $from, Carbon $to, bool $requireArujanTag = true): Collection;
}
