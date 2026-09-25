# カレンダーアーカイブ編集（セクション構成・関連ルール）

対象機能: 「カレンダーアーカイブ編集」画面。実装の中心は `app/Http/Controllers/Admin/AdminArchiveController.php`（`CalendarController.php` ではない点に注意。`CalendarController` は公開側の閲覧専用）。

## 1. データモデル

- `Onedayarchive`（`app/Models/Onedayarchive.php`）: 1日単位のアーカイブ本体。`event_date`, `no_stream`, `title`, `official_title`, `official_video_url`, `official_edited_title`, `official_edited_video_url`, `thumbnail_url`, `description` を持つ。`sections()` で `ArchiveSection` に1対多。
- `ArchiveSection`（`app/Models/ArchiveSection.php:8-13`）: 1日の中の区分（0次会〜4次会・特別回）。`onedayarchive_id`, `section_type`, `game_genre`, `section_title` が fillable。**`video_url` カラムはマイグレーション（`2026_04_18_035952_add_video_url_to_archive_sections_table.php`）で追加されているが `$fillable` に含まれず、`AdminArchiveController` からも書き込まれていない未使用カラム**。実際の動画URLはメンバーごとの中間テーブルに持つ（下記）。
- `archive_section_member` ピボット: `video_url`, `needs_review`, `review_reason`（`2026_07_23_222758`）, `no_stream`（`2026_08_14_094327`）。`no_stream` は「参加したが配信なしと確認済み」を表し、単に `video_url` 未入力の状態と区別するためのフラグ。

## 2. セクション種別と時間帯

`section_type` の正式な値は `AdminArchiveController::SECTION_CONFIGS`（`AdminArchiveController.php:16-22`）で定義:

| section_type | 表示名 |
|---|---|
| pre | 0次会 |
| primary | 1次会 |
| secondary | 2次会 |
| third | 3次会 |
| fourth | 4次会 |
| special | 特別回（別コントローラで管理、[03-special-episodes.md](03-special-episodes.md)） |

時間帯の境界は `app/Services/Archive/SectionTimeWindows.php`（イベント日0:00からの分オフセット）:

| section_type | 範囲 |
|---|---|
| pre | 0〜1260分（〜21:00） |
| primary | 1260〜1410分（21:00〜23:30） |
| secondary | 1410〜1530分（23:30〜翌1:30） |
| third | 1530〜1650分（翌1:30〜翌3:30） |
| fourth | 1650〜1920分（翌3:30〜翌8:00） |

`MIN_OVERLAP_MINUTES = 10` で境界付近の誤判定をガード。`special` はこの時間窓ロジックの対象外（クラスのdocblockに明記）。

## 3. 急遽不参加（ArchiveSectionAbsentMember）

`app/Models/ArchiveSectionAbsentMember`。`event_date` + `section_type` + `member_id` の組でユニーク（`2026_08_14_160033_create_archive_section_absent_members_table.php:14-19`）。

存在理由（モデルのdocblockより）: 参加者抽出を再実行すると、他メンバーの動画概要欄に名前が残っている限り、一度手動で外したメンバーが何度でも復活してしまう。これを恒久的に抑止するための除外リスト。

- `AdminArchiveController::markAbsent()`（`307-321`）: `section_type`（5種のみ）と `member_id` を検証し、`firstOrCreate` で登録。すでにそのセクションに保存済みなら pivot からも `detach`。
- `ParticipantComputationService.php:104-114`: 抽出候補を作る際、対象日・セクションの絶対不参加リストに載っているメンバーIDを除外する。→ [01-archive-extraction.md](01-archive-extraction.md#4-急遽不参加による除外)

## 4. ゲームジャンル

- `Game`（`app/Models/Game.php`）: `name` のみの単純なマスタ。`GameSeeder` で投入。
- `GameGenreInferenceService`（`app/Services/GameGenreInferenceService.php`）: 動画のタイトル・概要欄（定型文除去後）・タグを対象に、`Game.name` と18タイトル分のエイリアス（`78-97`、例: Among Us/VALORANT/Apex/Minecraft 等の日本語表記ゆれ）にスコアを付けてマッチング。
  - タイトル一致 +100 / タグ一致 +70 / 概要欄一致 +25（ただし **Among Usの概要欄一致だけ +5** — 定型文による誤検出を避けるための特別扱い、`65行目`）。
  - キーワードは長い順に評価（`uasort`, `100行目`）し、「マリオカート」等の短い部分一致による衝突を防ぐ。
  - 最高スコアのジャンルを採用（同点時は配列順に依存、明確な優先順位はない）。
- `AdminArchiveController::autofillGames()`（`246-282`）は、**`game_genre` が空、または文字列 `'Among Us'` のときだけ**推定結果で上書きする（手動設定済みの他ジャンルは保護される）。

## 5. 公式編集動画

`Onedayarchive` の `official_edited_title` / `official_edited_video_url` に1日1件だけ保持（各セクションのメンバー別 `video_url` とは別物）。`official_edited_video_url` は `app/Rules/OfficialYoutubeChannelUrl.php` でバリデーションされる（`AdminArchiveController.php:171`）が、無編集版の `official_video_url` にはこのバリデーションはかかっていない。

## 6. 本登録時の「メインメンバー2人以上」ルール

`Member::hasEnoughMainMembers()`（`app/Models/Member.php:87-97`、docblock: 「セクション本登録の必須条件」）は、選択されたメンバーIDのうち `mainMemberNames()`（`52-63`, 固定7名）に該当する人数が2人以上いるかを判定する。

`AdminArchiveController::update()` のバリデーション（`176-190`）では、**新規にセクションを登録する場合のみ**この閾値を要求する。既存の入力済みセクションを編集する場合はこのチェックをスキップする（`193-194`のコメントに明記）。

## 7. その他のコントローラ

- `CalendarController.php`: 公開側カレンダー閲覧のみ（読み取り専用）。`no_stream=false` の `Onedayarchive` を月表示、Among Usセクションを含む日をハイライト。
- `HomeController.php`: トップページ用に最新動画をYouTube Data APIから取得（`YOUTUBE_API_KEY`/`YOUTUBE_CHANNEL_ID` 環境変数使用）。DBアクセスなし。
- `MemberController.php`: メンバー一覧を main/sub/guest に分類して表示。Among Us参加記録から初回・最終参加日を算出（一般セクション参加ではなく `AmongusRecord` 経由のみ）。
- `YoutubeController.php`: **`routes/web.php` に一切登録されておらず未使用（デッドコード）**。`HomeController` とほぼ同一処理をチャンネルIDのハードコードで行っている。
