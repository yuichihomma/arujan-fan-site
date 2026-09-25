<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [

            // ======================
            // クルー
            // ======================
            ['name' => 'クルーメイト', 'type' => 'crew'],
            ['name' => 'シェリフ', 'type' => 'crew'],
            ['name' => 'メイヤー', 'type' => 'crew'],
            ['name' => 'ドクター', 'type' => 'crew'],
            ['name' => 'スパイ', 'type' => 'crew'],
            ['name' => 'スニッチ', 'type' => 'crew'],
            ['name' => 'エンジニア', 'type' => 'crew'],
            ['name' => 'サイエンティスト', 'type' => 'crew'],
            ['name' => 'トラッパー', 'type' => 'crew'],
            ['name' => 'バスカー', 'type' => 'crew'],
            ['name' => 'ベイト', 'type' => 'crew'],
            ['name' => 'ジャスティス', 'type' => 'crew'],
            ['name' => 'オラクル', 'type' => 'crew'],
            ['name' => 'コレイター', 'type' => 'crew'],
            ['name' => 'シア', 'type' => 'crew'],
            ['name' => 'ドッペルゲンガー', 'type' => 'crew'],
            ['name' => 'ネクロマンサー', 'type' => 'crew'],
            ['name' => 'ナイススワッパー', 'type' => 'crew'],
            ['name' => 'クライマー', 'type' => 'crew'],
            ['name' => 'エコー', 'type' => 'crew'],
            ['name' => 'アルケミスト', 'type' => 'crew'],
            ['name' => 'DJ', 'type' => 'crew'],
            ['name' => 'スライム', 'type' => 'crew'],
            ['name' => 'コメット', 'type' => 'crew'],
            ['name' => 'サンタ', 'type' => 'crew'],
            ['name' => 'ナイスゲッサー', 'type' => 'crew'],
            ['name' => '天秤', 'type' => 'crew'],
            ['name' => 'スター', 'type' => 'crew'],
            ['name' => '賢者', 'type' => 'crew'],
            ['name' => 'さつまいも', 'type' => 'crew'],
            ['name' => 'パン屋', 'type' => 'crew'],
            ['name' => 'トリレンマ（クルー）', 'type' => 'crew'],
            ['name' => 'ユビキタス', 'type' => 'crew'],
            ['name' => 'ポラリス', 'type' => 'crew'],
            ['name' => 'ベーカリー', 'type' => 'crew'],




            // ======================
            // インポスター
            // ======================
            ['name' => 'バブルガン', 'type' => 'impostor'],
            ['name' => 'マフィア', 'type' => 'impostor'],
            ['name' => 'シリアルキラー', 'type' => 'impostor'],
            ['name' => 'ヴァンパイア', 'type' => 'impostor'],
            ['name' => 'ウォーロック', 'type' => 'impostor'],
            ['name' => 'シェイプシフター', 'type' => 'impostor'],
            ['name' => 'スナイパー', 'type' => 'impostor'],
            ['name' => 'イビルゲッサー', 'type' => 'impostor'],
            ['name' => 'ジャマー', 'type' => 'impostor'],
            ['name' => 'マッドメイト', 'type' => 'impostor'],
            ['name' => 'マッドメイトスワッパー', 'type' => 'impostor'],
            ['name' => 'イリュージョナー', 'type' => 'impostor'],
            ['name' => 'スカルプター', 'type' => 'impostor'],
            ['name' => 'カモフラージャー', 'type' => 'impostor'],
            ['name' => 'アマルガム', 'type' => 'impostor'],
            ['name' => 'ギムレット', 'type' => 'impostor'],
            ['name' => 'モーフィング', 'type' => 'impostor'],
            ['name' => 'レイダー', 'type' => 'impostor'],
            ['name' => 'ハダル', 'type' => 'impostor'],
            ['name' => 'バウンティハンター', 'type' => 'impostor'],
            ['name' => 'シノビ', 'type' => 'impostor'],
            ['name' => 'エクリプス', 'type' => 'impostor'],
            ['name' => 'マジシャン', 'type' => 'impostor'],
            ['name' => 'スラッガー', 'type' => 'impostor'],
            ['name' => 'トリガーハッピー', 'type' => 'impostor'],
            ['name' => 'ペンギン', 'type' => 'impostor'],
            ['name' => 'バンシー', 'type' => 'impostor'],
            ['name' => 'リモコン', 'type' => 'impostor'],
            ['name' => 'トリレンマ（インポスター）', 'type' => 'impostor'],
            ['name' => 'スナッチャー', 'type' => 'impostor'],
            ['name' => 'アマルガム', 'type' => 'impostor'],
            ['name' => 'ウィッチ', 'type' => 'impostor'],



            // ======================
            // 第三陣営
            // ======================
            ['name' => 'ジャッカル波動砲', 'type' => 'neutral'],
            ['name' => 'サイドキック', 'type' => 'neutral'],
            ['name' => 'ジャッカル', 'type' => 'neutral'],
            ['name' => 'ジェスター', 'type' => 'neutral'],
            ['name' => 'アーソニスト', 'type' => 'neutral'],
            ['name' => 'ヴァルチャー', 'type' => 'neutral'],
            ['name' => '恋人', 'type' => 'neutral'],
            ['name' => 'テロリスト', 'type' => 'neutral'],
            ['name' => '仕事人', 'type' => 'neutral'],
            ['name' => '純愛者', 'type' => 'neutral'],
            ['name' => 'ジャッカルレイダー', 'type' => 'neutral'],
            ['name' => 'パパラッチ', 'type' => 'neutral'],
            ['name' => 'プレイグ', 'type' => 'neutral'],
            ['name' => 'ジャッカルイリュージョナー', 'type' => 'neutral'],
            ['name' => 'ヴァニティ', 'type' => 'neutral'],
            ['name' => 'ジャッカルろくろ首', 'type' => 'neutral'],
            ['name' => 'ジャッカルバブルガン', 'type' => 'neutral'],
            ['name' => 'ギャンブラー', 'type' => 'neutral'],
            ['name' => 'ジャッカルイビルゲッサー', 'type' => 'neutral'],
            ['name' => 'ジャッカルモーフィング', 'type' => 'neutral'],
            ['name' => 'ジャッカルクリーピング', 'type' => 'neutral'],
            ['name' => 'ジャッカルノヴァ', 'type' => 'neutral'],
            ['name' => 'アンカー', 'type' => 'neutral'],
            ['name' => 'ジャッカルハダル', 'type' => 'neutral'],
            ['name' => '陰陽師', 'type' => 'neutral'],
            ['name' => '式神', 'type' => 'neutral'],
            ['name' => 'モイラ', 'type' => 'neutral'],
            ['name' => 'ジャッカルギムレット', 'type' => 'neutral'],
            ['name' => 'トリレンマ（第三陣営）', 'type' => 'neutral'],
            ['name' => 'ラバーズ', 'type' => 'neutral'],




        ];

        foreach ($roles as $role) {
        Role::updateOrCreate(
            ['name' => $role['name']],
            ['type' => $role['type']]
        );
    }
    }
}