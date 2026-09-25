<?php

namespace App\Services\Archive\Contracts;

interface SingleVideoLookupFetcher extends PlatformArchiveFetcher
{
    /**
     * $urlがこのプラットフォームの動画URLの形をしているか（API呼び出しなしの形状チェックのみ）。
     */
    public function supportsUrl(string $url): bool;

    /**
     * $url1件の動画情報（タイトル・概要欄など）を取得する。
     * 見つからない場合はnullを返す（呼び出し側はエラーではなく「動画が見つからない」として扱う）。
     *
     * @return array{platform: string, url: string, title: string, description: string}|null
     */
    public function fetchVideoByUrl(string $url): ?array;
}
