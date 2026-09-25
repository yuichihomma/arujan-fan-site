<?php

namespace App\Services\Archive;

use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * 配信のタイトル・概要欄に書かれている参加者名（例: NORISTRYさんのツイキャスタイトルにある
 * 「ハッチャン／なつぴょん／バブルケーキ...」のような列挙）から、既存メンバーを検出する。
 */
class ParticipantNameExtractor
{
    /**
     * @param Collection<int, Member> $members
     * @return Collection<int, Member>
     */
    public function extract(string $text, Collection $members): Collection
    {
        return $members->filter(fn (Member $member) => $this->containsName($text, $member->name))->values();
    }

    private function containsName(string $text, string $name): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        // 名前の前後が文字・数字で無い場合（単語境界）だけ一致とみなす。
        // これが無いと、英数字名("Is")が英単語("is")の一部に、漢字名("結")が
        // 別の熟語("結果")の一部に、それぞれ誤って一致してしまう。
        // 英数字名は大文字小文字も区別する（"is"のような一般的な単語との衝突を避けるため）。
        $isAsciiName = (bool) preg_match('/^[a-zA-Z0-9_]+$/', $name);

        return (bool) preg_match(
            '/(?<![\p{L}\p{N}])' . preg_quote($name, '/') . '(?![\p{L}\p{N}])/u' . ($isAsciiName ? '' : 'i'),
            $text
        );
    }
}
