# 特別回の管理

対象機能: 「特別回の管理」画面。実装: `app/Http/Controllers/Admin/AdminSpecialArchiveController.php`。ルート: `admin.archives.special.index/store/update`（`routes/web.php:43-45`）。

## 1. 「特別回」の実体

独立したモデルではなく、`ArchiveSection.section_type = 'special'` という値で表現される（旧名 `external`。`2026_06_20_000001_rename_external_archive_sections_to_special.php` でリネーム）。通常の0次会〜4次会と同じ `ArchiveSection` テーブルを使う。

## 2. 一覧（`index()`, `15-34`）

`section_type = 'special'` かつ `whereHas('members')`（参加者が1人以上登録済み）の `ArchiveSection` を `onedayarchive`・`members` 付きでイベント日降順に一覧表示。

## 3. 新規登録（`store()`, `36-99`）

バリデーション:
- `event_date`: required, date
- `game_genre`: nullable, string, max:255
- `members.*.selected` / `members.*.video_url`（video_urlはmax:2048）

登録ルール:
1. **対象日にすでに通常アーカイブ（`Onedayarchive::hasNonSpecialContent()` が true）が存在する場合は登録を拒否**（`49行目`）。特別回は通常アーカイブと同じ日に共存できない。
2. **選択メンバーが1人以上、かつ `Member::hasEnoughMainMembers($selectedIds)` を満たす必要がある**。満たさない場合「アルジャンのメインメンバーが2人以上いないと登録できません」で拒否（`59-63`）。カレンダーアーカイブ編集の新規登録時と同じ閾値ロジックを使い回している。
3. 対象日の `Onedayarchive` が無ければ空の公式フィールドで新規作成し、`ArchiveSection` を `section_title = '特別回'` で `firstOrCreate`。
4. メンバーのpivotデータは `sync` ではなく **`syncWithoutDetaching`** を使う（`93行目`のコメントに明記: 既存参加者を消さないため）。

## 4. 更新（`update()`, `101-126`）

対象セクションの `section_type` が `'special'` でなければ404（`103-105`）。`game_genre` と各メンバーの `video_url`（`updateExistingPivot`）のみ更新可能。

## 5. `Onedayarchive` 側の相互排他ルール

`app/Models/Onedayarchive.php`
- `hasNonSpecialContent()`（`30-36`）: 特別回以外の `section_type` で、かつ参加者が1人以上いるセクションが存在するか。
- `hasSpecialSection()`（`42-48`）: 特別回のセクションで、かつ参加者が1人以上いるセクションが存在するか。

いずれも「空セクション（参加者0人）はカウントしない」点に注意。この2つのメソッドにより、1日は「通常アーカイブ」か「特別回」のどちらか一方としてのみ実質的に成立する設計になっている。
