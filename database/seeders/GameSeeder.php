<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Game;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            'Among Us',
            'VALORANT',
            'Feign',
            'Project Winter',
            'GeoGuessr',
            'Golf It!',
            'Overwatch',
            'Windrose',
            'マリオカート',
            'Faaast Penguin',
            'NORTH HUNT',
            'Cooking Simulator 2 Better Together',
            'ルドー',
            'MAYDAY PROTOCOL',
            'ドカポンキングダム コネクト',
            '桃太郎電鉄',
            'AIアートインポスター',
            'super battle golf',
            'MIMESIS',
            'Minecraft',
            'PEAK',
            '雀魂',
            'RUST ベッドウォーズ',
            'オインクゲームズ',
            'Slay the SPIRE2',
            'Party Animals',
            'Clean Up Earth',
            'お邪魔者/Saboteur',
            'おしゃべりキング!',
            'カービィのエアライダー',
            'Star Rupture',
            'WASDトリの冒険',
            'みんなで空気読み',
            'Apex Legends',
            'Board Game Arena',
            'HYTALE',
            'Cursed Companions',
            'YAPYAP',
            'マリオテニスフィーバー',
            'LORT',
            'Killer inn',
            'DisasterBand',
            'LINK penguins',
            '利用規約に同意したい',
            'WEST HUNT',
            'ITO',
            'Among Us 3D',
            'MISERY',
            'R.E.P.O',
            'ARC Raiders',
            'Escape Simulator 2',
            'Back room',
            'A GENTLEMENS',
            'マーダーミステリー',
            'コードネーム',
            'DEATH NOTE Killer Within',
            'Gamble With Your Friends',
            'BOMBANANA',
            'Scam Line',
            'FROG SQWAD',
            'FOLLOW US',
            'Scam Line',
            'OCTOPinbs',
            'ドカポンキングダム コネクト',
            'Unicycle Together',
            'PRATFALL',
            'Vampire Crawlers',
            'Climber Animals: Together',




        ];

        foreach ($games as $game) {
            Game::firstOrCreate(['name' => $game]);
        }
    }
}