# Among Us戦績（記録追加・解析下書き・レギュレーション・集計）

対象機能: 「記録の追加・更新」「解析結果確認画面」。

## 1. データモデルの関係

```
ArchiveSection (1次会) ─1:1─ AmongusRecord ─1:N─ AmongusMatch ─1:N─ AmongusMatchMemberResult
                                    │
                                    ├─1:N─ AmongusRegulation（phase: normal / changed）
                                    ├─1:N─ AmongusRegulationChange（action: add / remove）
                                    └─N:N─ Member（amongus_record_members、当日の参加者）

AmongusAnalysisDraft（ArchiveSection・Memberを参照するだけの独立ステージングテーブル。
                       AmongusRecord/AmongusMatchとは直接の外部キー関係を持たない）
```

- `amongus_records`: `archive_section_id` に `unique` 制約（`2026_04_20_051640`, 17行目）→ 1セクション1レコード。`regulation_change`（`none`/`changed`）, `regulation_change_match_number`, `is_completed`(bool) を持つ。
- `amongus_matches`: `match_number` はレコード内でunique（`2026_04_21_051641:19`）。`win_side`, `memo`。
- `amongus_match_member_results`: `(amongus_match_id, member_id)` でunique。`result`（win/lose）, `role_id`。
- `amongus_record_members`: レコードとメンバーのN:N（当日の参加者）。
- `amongus_regulations`: `phase` = normal/changed ごとにcrew/impostor/neutralの役職・人数を保持。同一phaseの複数行を許容（unique制約なし）。
- `amongus_regulation_changes`: `changed` phaseに至った差分（role_id, count, action）。
- `amongus_analysis_drafts`: `archive_section_id` と自由入力の `member_name`/`role_name`（`members`/`roles` へのFKではない）を持つ。`status`（pending/approved/rejected）。

## 2. 記録の追加・更新（`app/Http/Controllers/Admin/AmongusRecordController.php`）

- カスタムバリデーションルールクラスは存在せず、`store()` 内で `$request->validate()` による直接検証（`161-189`）。`result` は `win`/`lose` のみ（引き分けなし）。`submit_action` は `save` か `complete`。
- **win/lose は管理者が試合ごと・メンバーごとに手動入力する**。`role_id` と `win_side` から `result` を自動算出するロジックは無い。
- `AmongusMatch.win_side` はこの手動フローでは一切設定されない（`302-305`）。解析下書きの `apply()` 経由でのみセットされうる。→ **手動入力フローでは「個人のwin/lose」と「試合のwin_side」が独立した項目で、整合性チェックが無い。**
- 保存は都度トランザクションで、そのレコードに紐づく `matches`・`regulations`・`regulationChanges` を**一旦全削除してから再作成**する（`226-232`）。
- 試合行は `role_id` と `result` の両方が埋まっているメンバー結果が1件以上ある場合のみ作成される。空の試合は保存されない（`292-300`）。
- `is_completed` は `submit_action === 'complete'` の時のみ true。それ以外は下書き（未完了）として保存される（`214`）。
- レギュレーション行は `regulation_change === 'changed'` の場合のみ保存される（`257`）。

## 3. 解析結果確認画面

Laravelコマンド `amongus:analyze-primary-videos`（`routes/console.php:999-1164`）:
- 対象は `section_type=primary` かつ `game_genre='Among Us'` のセクションで、メンバーの `video_url` が登録されているもののみ（`1008-1012`）。0次会・2次会・3次会・特別回・Among Us以外のゲームは対象外（設計方針は `docs/amongus-analysis-draft-design.md` にも明記）。
- `scripts/amongus/analyze_video.py` を `Process` 経由で実行し、結果を `status='pending'` で `amongus_analysis_drafts` にupsert。スクリプト失敗時もプレースホルダのpendingドラフトを作成する（`makePendingAmongusAnalysisDraftRows`, `1216-1241`、confidence=0）。

`app/Http/Controllers/Admin/AmongusAnalysisDraftController.php`:
- `approve()`/`reject()`（`62-74`）は `status` を切り替えるだけ。
- `apply()`（`76-154`）が本登録への昇格処理。**`status='approved'` の下書きのみが対象**（`85`）。
- `match_number`・`member_name`・`role_name`・`result` が全て揃っている必要がある（`97`）。
- `member_name`/`role_name` は**完全一致文字列**で `Member`/`Role` に解決する（`103-104`）。一致しない場合はその行をスキップ（警告のみ、自動作成はしない）。
- `AmongusRecord` は `firstOrCreate`（`112-114`、無ければ自動作成）。`AmongusMatch` も `match_number` で `firstOrCreate`し、`win_side` はドラフト側の値で**未設定の場合のみ**セット（既存値を上書きしない, `125-127`）。
- 既存の `AmongusMatchMemberResult` がある場合、`overwrite` を明示的にチェックしない限りスキップ（デフォルトは非破壊, `133-136`）。

## 4. レギュレーション（AmongusRegulation / AmongusRegulationChange）の実際の扱い

`normal`（当日の基本レギュレーション）と `changed`（途中変更後）の2フェーズをスナップショットとして保存する設計。**しかし現状、集計・戦績表示のどのコードもこれらのテーブルを参照・JOINしていない**（`AmongusStatsController` 含め、`regulations`/`regulationChanges`/`regulation_change` を読む箇所は無い）。今のところ純粋な記録用データであり、勝敗判定や集計には影響しない。

## 5. 集計（`app/Http/Controllers/Stats/AmongusStatsController.php`）

- `index()`（日別, `16-111`）: 日付ごとに `AmongusMatchMemberResult` から `member_id` 単位で勝率を集計。**`is_completed` による絞り込みはなし**（未完了レコードも含まれる）。順位はタイを同順位にする方式（`67-85`）。
- `total()`（通算, `113-230`）: `is_completed = true` かつセクションの `game_genre = 'Among Us'` のレコードのみに限定（`142-147`）。日別集計とは違い、未完了・下書きは通算から除外される。役職タイプ（crew/impostor/neutral）別勝率も算出（`182-215`）、年月フィルタ対応。
- **最少試合数の閾値、ゲスト参加者の除外、参加回数フィルタは日別・通算のどちらにも存在しない。** 対象となる `AmongusMatchMemberResult` に1件でも登場したメンバーは全員集計対象になり、分母0の場合は勝率0%として表示される（`59-60`, `195-197`）。
