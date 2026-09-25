# メンバー管理・管理者認証

対象機能: 「メンバー追加・編集」画面、および管理画面全体の認証。

## 1. Memberモデル

`app/Models/Member.php`。fillable（`9-19`）: `name, avatar, description, youtube_url, youtube_channel_id, x_url, twitch_url, other_platform, other_url`。

カラムは4つのマイグレーションに分かれて追加:
- 初期: `id, name, avatar`（`2025_12_13_141639`）
- `description, youtube_url, x_url, twitch_url`（`2026_05_19_135124`）
- `youtube_channel_id`（`2026_06_17_000001`）
- `other_platform, other_url`（`2026_06_18_000001`）

**「卒業」「在籍中/引退」等のステータスを表すカラムは存在しない。**論理削除フラグも無い。もしそうした状態管理を想定していたなら未実装。

### メイン/サブメンバー

DBカラムではなく、`Member.php` 内のハードコードされた固定リストで判定している:
- `mainMemberNames()`（`52-63`）: 固定7名
- `subMemberNames()`（`65-80`）: 固定11名
- `isMainMemberName()`（`82-85`）
- `hasEnoughMainMembers()`（`87-97`）: 選択されたメンバーのうち `mainMemberNames()` に該当する人数が2人以上か判定。「セクション本登録の必須条件」としてカレンダーアーカイブ・特別回の新規登録で使われる（[02-archive-calendar.md](02-archive-calendar.md#6-本登録時のメインメンバー2人以上ルール), [03-special-episodes.md](03-special-episodes.md#3-新規登録-store-36-99)）。

`database/seeders/MemberSeeder.php` には100名以上のメンバーが登録されているが、その大半はメイン・サブどちらのリストにも含まれない（＝ゲスト扱い）。**このリストはコード内の固定配列なので、新メンバーがメイン/サブ入りする場合はコード修正が必要**（DB登録だけでは反映されない）。

### YouTube連携

`youtube_url`（生URL）と `youtube_channel_id`（解決済みチャンネルID）は別カラム。`youtube_channel_id` は通常、`youtube_url` を入力した際に `YoutubeMemberArchiveSyncService` が自動解決する（手動入力を想定した経路ではない、下記参照）。

## 2. バリデーション（`app/Http/Controllers/Admin/MemberController.php`）

専用の FormRequest や `app/Rules/` のカスタムルールクラスは使われていない（`memberDataRules()`, `88-101`、`store()`=`42`, `update()`=`56` から共通利用）。

- `name`: required, string, max:255
- その他フィールド: nullable, string, max（URL系2048 / channel_id・other_platform は255）

**URL形式の検証、重複名チェック、公式チャンネルかどうかの制限は無い。**（`app/Rules/OfficialYoutubeChannelUrl.php` というルールクラス自体は存在するが、`MemberController` からは参照されていない。使われているのは `AdminArchiveController` の公式編集動画URLのみ。）

`youtube_url` が変更された場合、`YoutubeMemberArchiveSyncService` により `youtube_channel_id` と `avatar` を自動同期する（`MemberController.php:68-74, 103-146`）。

## 3. 管理者認証（Admin / Role は無関係な別概念）

- `Admin`（`app/Models/Admin.php`）: `Authenticatable` を継承する独立モデル。`name, email, password` のみ。`admins` テーブル（`2026_04_15_093158`）。
- 認証ガード: `config/auth.php:44-47, 73-76` で `admin` ガード（セッション方式）を `admins` プロバイダ/`Admin` モデルに紐付け。公開側ユーザー（`web`ガード/`User`モデル）とは完全に独立。
- **`Role` モデル（`app/Models/Role.php`）は管理者の権限とは無関係。** これはAmong Usのゲーム内役職マスタ（`name`, `type`∈crew/impostor/neutral）であり、`04-amongus-stats.md` の文脈でのみ使われる。
- **管理者に権限・ロールの区別は存在しない。** `admins` テーブルの全行が管理画面の全機能へ同一のフルアクセス権を持つ。
- ミドルウェア `app/Http/Middleware/AdminAuthenticate.php:12-19`（エイリアス `admin.auth`, `bootstrap/app.php:15`）が `Auth::guard('admin')->check()` を確認し、未ログインなら `admin.login` へリダイレクト。全管理ルートグループに適用（`routes/web.php:30, 89`）。
- ログイン処理: `app/Http/Controllers/Admin/Auth/AdminLoginController.php`。`admin` ガードに対して `attempt()`、ログイン時セッション再生成、ログアウト時セッション無効化+トークン再生成。

## 4. 管理コントローラ一覧（`app/Http/Controllers/Admin/`）

| コントローラ | 役割 |
|---|---|
| MemberController | メンバーCRUD、YouTube自動同期 |
| AdminArchiveController | カレンダーアーカイブ編集（[02](02-archive-calendar.md)） |
| AdminSpecialArchiveController | 特別回の管理（[03](03-special-episodes.md)） |
| AmongusAnalysisDraftController | 解析下書きの承認/却下/反映（[04](04-amongus-stats.md)） |
| AmongusRecordController | Among Us戦績の記録追加・更新（[04](04-amongus-stats.md)） |
| ArchiveImportController | インポート系パイプライン（fetch/review/commit/bulkCommit/discard） |
| CalendarController（Admin配下） | 管理カレンダー表示（index のみ） |
| StatsController | **中身が空のクラス。メソッド無し、デッドコード。**stats関連ルートは他コントローラ（AmongusRecordController等）が担当している |
| Auth/AdminLoginController | 管理者ログイン/ログアウト |

## 5. User vs Admin

完全に別系統の認証。
- `User`（`app/Models/User.php`）: 公開サイト側ユーザー（コメント投稿者を想定）。`comments()` リレーション（`49-52`）あり。`web`ガード/`users`プロバイダ。
- `Admin`（`app/Models/Admin.php`）: 内部スタッフ用。リレーション無し、`factory` トレイト無し。`admin`ガード/`admins`プロバイダ、専用テーブル。

両者は `Authenticatable` を継承している以外の共通点はなく、`config/auth.php:38-48` で完全に独立したガードとして定義されている。
