<?php

namespace App\Services;

use App\Models\Game;
use App\Services\Archive\DescriptionBoilerplateStripper;

class GameGenreInferenceService
{
    public function inferFromVideos(iterable $videos): ?string
    {
        $scores = [];

        foreach ($videos as $video) {
            $this->addScores(
                $scores,
                $video['title'] ?? '',
                $video['description'] ?? '',
                $video['tags'] ?? []
            );
        }

        if (empty($scores)) {
            return null;
        }

        arsort($scores);

        return array_key_first($scores);
    }

    public function inferFromText(string $title, string $description = '', array $tags = []): ?string
    {
        $scores = [];
        $this->addScores($scores, $title, $description, $tags);

        if (empty($scores)) {
            return null;
        }

        arsort($scores);

        return array_key_first($scores);
    }

    private function addScores(array &$scores, string $title, string $description, array $tags): void
    {
        $title = mb_strtolower($title);
        $description = mb_strtolower(DescriptionBoilerplateStripper::strip($description));
        $tagsText = mb_strtolower(implode(' ', $tags));

        foreach ($this->gameKeywords() as $gameName => $keywords) {
            foreach ($keywords as $keyword) {
                $keyword = mb_strtolower($keyword);

                if ($keyword === '') {
                    continue;
                }

                if (str_contains($title, $keyword)) {
                    $scores[$gameName] = ($scores[$gameName] ?? 0) + 100;
                }

                if (str_contains($tagsText, $keyword)) {
                    $scores[$gameName] = ($scores[$gameName] ?? 0) + 70;
                }

                if (str_contains($description, $keyword)) {
                    $scores[$gameName] = ($scores[$gameName] ?? 0) + ($gameName === 'Among Us' ? 5 : 25);
                }
            }
        }
    }

    private function gameKeywords(): array
    {
        static $keywords = null;

        if ($keywords !== null) {
            return $keywords;
        }

        $keywords = Game::query()
            ->pluck('name')
            ->mapWithKeys(fn (string $name) => [$name => [$name]])
            ->all();

        $aliases = [
            'Among Us' => ['Among Us', 'AmongUs', 'アモアス', 'あもあす', '宇宙人狼', '近アモ'],
            'めっちゃカメレオン' => ['めっちゃカメレオン', 'めちゃ亀', 'めちゃカメ部', 'めっちゃカメレオン部'],
            'VALORANT' => ['VALORANT', 'ヴァロラント', 'バロラント', 'ヴァロ'],
            'Project Winter' => ['Project Winter', 'プロジェクトウィンター', '雪山人狼'],
            'GeoGuessr' => ['GeoGuessr', 'ジオゲッサー'],
            'Golf It!' => ['Golf It', 'GolfIt'],
            'Overwatch' => ['Overwatch', 'オーバーウォッチ', 'OW2'],
            'マリオカート' => ['マリオカート', 'マリカ'],
            'Minecraft' => ['Minecraft', 'マインクラフト', 'マイクラ'],
            'Apex Legends' => ['Apex Legends', 'Apex', 'エーペックス'],
            'Board Game Arena' => ['Board Game Arena', 'BGA', 'ボドゲアリーナ'],
            'マーダーミステリー' => ['マーダーミステリー', 'マダミス'],
            'コードネーム' => ['コードネーム', 'Codenames'],
            'Feign' => ['Feign', 'Feignイン'],
            '桃太郎電鉄' => ['桃太郎電鉄', '桃鉄'],
            '雀魂' => ['雀魂', 'じゃんたま'],
            'R.E.P.O' => ['R.E.P.O', 'REPO'],
            'DEATH NOTE Killer Within' => ['DEATH NOTE Killer Within', 'デスノート'],
        ];

        foreach ($aliases as $gameName => $gameAliases) {
            $keywords[$gameName] = array_values(array_unique(array_merge(
                $keywords[$gameName] ?? [],
                $gameAliases
            )));
        }

        uasort($keywords, fn (array $a, array $b) => max(array_map('mb_strlen', $b)) <=> max(array_map('mb_strlen', $a)));

        return $keywords;
    }
}
