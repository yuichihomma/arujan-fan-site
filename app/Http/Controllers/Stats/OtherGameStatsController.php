<?php

namespace App\Http\Controllers\Stats;

use App\Http\Controllers\Controller;
use App\Models\ArchiveSection;
use Illuminate\Http\Request;

class OtherGameStatsController extends Controller
{
    public function index()
    {
        $sections = ArchiveSection::whereNotNull('game_genre')
            ->where('game_genre', '!=', '')
            ->where('game_genre', '!=', 'among us')
            ->get();

        $popularGames = $sections //人気ゲームランキングTOP3
            ->groupBy('game_genre')
            ->map(function ($items, $game) {
                return [
                    'game' => $game,
                    'count' => $items->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(3)
            ->values();

        $recentGames = ArchiveSection::with('onedayarchive')// 最近遊んだゲーム TOP3
            ->whereNotNull('game_genre')
            ->where('game_genre', '!=', '')
            ->where('game_genre', '!=', 'among us')
            ->get()
            ->sortByDesc(function ($section){
                return $section->onedayarchive->event_date;
            })
            ->take(3)
            ->map(function ($section) {
                return [
                    'game' => $section->game_genre,
                    'date' => $section->onedayarchive->event_date,
                    ];
            })
            ->values();
            
        $gameArchives = $sections // ゲームごとの日付一覧
            ->pluck('game_genre')
            ->unique()
            ->sort()
            ->values();

        return view('stats.other-games.index', compact(
            'popularGames',
            'recentGames',
            'gameArchives'
            ));

    }

    public function show($game)
    {
        $archives = ArchiveSection::with('onedayarchive')
        ->where('game_genre', $game)
        ->get()
        ->sortByDesc(function ($section) {
            return $section->onedayarchive->event_date;
        });

    return view('stats.other-games.show', compact('game', 'archives'));
    }

}
