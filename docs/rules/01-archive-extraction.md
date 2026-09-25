# 配信アーカイブ取得・参加者抽出条件

対象機能: 「カレンダーアーカイブ編集」→「ゲーム内容と参加メンバーを抽出する」ボタン。

エントリポイント: `app/Http/Controllers/Admin/AdminArchiveController.php` の `computeParticipants` 系アクション → `app/Services/Archive/ParticipantComputationService.php::computeForDate()`。

## 1. 対象動画の絞り込み条件

`app/Services/Archive/YoutubeArchiveFetcher.php`

- アカウント登録済みメンバー全員のチャンネルから、対象日 0:00 〜 翌9:00 に公開された動画を候補にする（`ParticipantComputationService.php:38`）。
- `$requireArujanTag = true` の場合のみ、`isArujanTaggedVideo()`（`YoutubeArchiveFetcher.php:93-97`）でフィルタする。

  ```php
  private function isArujanTaggedVideo(array $video): bool
  {
      return str_contains($video['title'] ?? '', 'アルジャン')
          || str_contains(DescriptionBoilerplateStripper::strip($video['description'] ?? ''), 'アルジャン');
  }
  ```

  **判定条件はタイトルまたは概要欄に「アルジャン」という文字列が含まれるかどうかのみ。**
  コード内コメント（`YoutubeArchiveFetcher.php:73-76`）には「メンバー個人チャンネルにはソロ配信・他コラボ等も混在するため、アルジャンタグ付きのみを対象にする」と書かれているが、**実装は文字列一致だけで、参加人数やコラボかどうかの判定は一切行っていない**。概要欄の自己紹介・所属タグなど、セッション内容と無関係な箇所に「アルジャン」という語が含まれるだけで通過する。

### ★不具合（2026-07-15）

オシオンさんのソロ配信が、概要欄等の「アルジャン」という語を含む定型文により `isArujanTaggedVideo()` を通過し、後述のロジックでそのまま0次会の参加者として登録された。**このソロ/コラボ判定の欠落が直接原因。**

## 2. セクションへの振り分け

`app/Services/Archive/ParticipantComputationService.php:48-84`

- 動画の `published_at` を `SectionTimeWindows::windowContaining()` に渡し、該当する1セクション（0次会〜4次会）のみに計上する（延長配信で複数セクションにまたがっても、開始時刻の属する1セクションだけを見る。理由: コメント`55-58`「同じ顔ぶれでのアルジャン継続とは限らない」）。
- `primaryType === 'pre'`（0次会）と判定された場合、前日基準でも `windowContaining` した結果が `pre` 以外になるなら、前日配信の延長とみなしてスキップする（`68-74`）。日またぎの0次会誤爆対策。
- **通過した動画は無条件でそのセクションの参加者として登録される**（`79-80`: `$sectionVideos[$primaryType][] = $row;` / `$sectionMemberIds[$primaryType][(int) $row['member_id']] = true;`）。参加人数の閾値チェックはこの経路には存在しない。
- タイトル・概要欄（定型文除去後）から `ParticipantNameExtractor::extract()`（`77行目`）で他メンバー名を検出し、検出できた分も同じセクションの参加者に追加する（`82-84`）。

## 3. 「2人以上一致」の閾値はここでは使われていない

`app/Services/Archive/ArchiveImportOrchestrator.php:24`

```php
const MIN_ROSTER_NAME_MATCHES = 2;
```

この閾値は `fetchUntaggedFallback()`（同ファイル `206-217`）でのみ使用される。これは「他メンバーの動画からすでに参加者として抽出済みのメンバーについて、`requireArujanTag=false` でタグ判定なしに動画を探す」別経路であり、**「ゲーム内容と参加メンバーを抽出する」ボタン（`ParticipantComputationService::computeForDate`）の経路では使われていない**。つまりソロ/コラボを区別できる唯一の閾値ロジックが、今回問題が起きた経路には適用されていなかった。

## 4. 急遽不参加による除外

`app/Services/Archive/ParticipantComputationService.php:104-114`

対象日・セクションについて `app/Models/ArchiveSectionAbsentMember` に登録されているメンバーIDは、抽出候補から除外される。詳細は [02-archive-calendar.md](02-archive-calendar.md#急遽不参加-archivesectionabsentmember) を参照。

## 5. 定型文除去

`app/Services/Archive/DescriptionBoilerplateStripper.php`

概要欄からバナーブロック・リンク見出し・ハッシュタグ行・イベント告知段落（`📅日程|出演者|チケット|OPEN|START|■` 等にマッチ）を除去してから名前抽出・アルジャンタグ判定に使う。ただし**「アルジャン」という語を含む一般的な自己紹介文・所属タグを除去するルールは無い**ため、そこに単語が残っていれば1節の判定をすり抜ける。
