# Among Us 戦績解析下書き設計

## 目的

一次会の `Among Us` 配信だけを対象に、Pythonで動画解析した戦績候補を下書きとして保存する。

解析結果はそのまま本登録しない。管理画面で人が動画内時刻・実時間・根拠を確認し、承認したものだけ既存の戦績テーブルへ反映する。

## 対象条件

解析対象は次の条件をすべて満たすアーカイブに限定する。

- `archive_sections.section_type = primary`
- `archive_sections.game_genre = Among Us`
- `archive_section_member.video_url` が登録されている
- YouTubeの通常動画または配信アーカイブURLである

0次会、2次会、3次会、特別回、Among Us以外のゲームは対象外にする。

### 除外理由

あるじゃんはAmong Us配信グループであり、一次会のAmong Usが看板コンテンツにあたる。そのため一次会のAmong Usだけを戦績集計の対象として特別扱いする。0次会・2次会・3次会・特別回はその他の位置づけであり、現時点では戦績解析の対象に含めない。

## 全体フロー

1. Laravelコマンドで一次会 `Among Us` の配信URLを抽出する。
2. Pythonスクリプトに動画URL、配信開始時刻、参加者情報を渡す。
3. Python側で字幕、音声文字起こし、画像/OCRを使って戦績候補を作る。
4. 解析結果を下書きテーブルに保存する。
5. 管理画面で下書きを確認する。
6. 承認された下書きだけ `amongus_records`、`amongus_matches`、`amongus_match_member_results` に反映する。

## Python解析の役割

Pythonは確定登録ではなく、候補作成だけを担当する。

主な処理:

- 字幕がある場合は字幕を取得する。
- 字幕がない場合は音声を文字起こしする。
- リザルト画面や役職表示が必要な場合は動画フレームを切り出してOCRまたは画像解析する。
- 試合番号、動画内時刻、実時間、役職、勝敗、根拠テキスト、信頼度をJSONで返す。

利用候補:

- `yt-dlp`: 字幕取得、動画メタデータ取得
- `ffmpeg`: 音声抽出、フレーム抽出
- `Whisper`: 音声文字起こし
- `OpenCV`: フレーム処理
- OCRまたはVision API: 画面内テキスト認識

## 下書きテーブル案

テーブル名:

```text
amongus_analysis_drafts
```

カラム案:

```text
id
archive_section_id
member_id
video_url
match_number
video_timestamp_seconds
video_timestamp_label
estimated_real_time
member_name
role_name
result
win_side
evidence_text
confidence
status
memo
raw_payload
created_at
updated_at
```

### カラム説明

`archive_section_id`  
対象の一次会 `Among Us` セクションID。

`member_id`  
解析元の配信者。配信者が不明な候補は `null` を許可してもよい。

`video_url`  
確認用の動画URL。

`match_number`  
推定された試合番号。

`video_timestamp_seconds`  
動画内の秒数。動画リンクに `?t=` を付けるために使う。

`video_timestamp_label`  
人が読むための動画内時刻。例: `01:23:45`

`estimated_real_time`  
配信開始時刻から推定した実時間。例: `2026-05-07 22:23:45`

`member_name`  
戦績対象の参加者名。DBの `members.name` と完全一致しない場合の確認にも使う。

`role_name`  
推定役職名。DBの `roles.name` と照合する。

`result`  
`win` または `lose`。不明なら `null`。

`win_side`  
勝利陣営。例: `crew`, `impostor`, `neutral`, `unknown`

`evidence_text`  
判断根拠。字幕、文字起こし、OCR結果など。

`confidence`  
信頼度。0から100の整数。

`status`  
確認状態。

```text
pending   未確認
approved  承認済み
rejected  不採用
```

`memo`  
人間が確認時に残すメモ。

`raw_payload`  
Pythonの生JSON。後から解析ロジックを検証するために残す。

## 下書きJSON形式

PythonからLaravelへ返すJSONは次の形にする。

```json
{
  "archive_section_id": 123,
  "source_video_url": "https://www.youtube.com/watch?v=XXXXXXXXXXX",
  "source_member_name": "ハッチャン",
  "matches": [
    {
      "match_number": 1,
      "video_timestamp_seconds": 5025,
      "video_timestamp_label": "01:23:45",
      "estimated_real_time": "2026-05-07 22:23:45",
      "win_side": "crew",
      "evidence_text": "クルー勝利のリザルト画面を検出",
      "confidence": 82,
      "results": [
        {
          "member_name": "ハッチャン",
          "role_name": "シェリフ",
          "result": "win",
          "confidence": 78,
          "evidence_text": "本人視点の役職表示と勝利画面"
        }
      ]
    }
  ]
}
```

## Laravelコマンド案

解析実行:

```bash
php artisan amongus:analyze-primary-videos --date=2026-05-07
```

期間指定:

```bash
php artisan amongus:analyze-primary-videos --from=2026-05-01 --to=2026-05-31
```

再解析:

```bash
php artisan amongus:analyze-primary-videos --date=2026-05-07 --overwrite-drafts
```

## 管理画面案

追加画面:

```text
/admin/stats/amongus/analysis-drafts
```

表示項目:

- 日付
- 試合番号
- 配信者
- 動画リンク
- 動画内時刻
- 実時間
- 参加者
- 推定役職
- 推定勝敗
- 勝利陣営
- 根拠テキスト
- 信頼度
- ステータス
- メモ

操作:

- 動画を該当時刻で開く
- 承認
- 不採用
- 役職・勝敗・試合番号を修正
- 承認済みだけ本登録

## 本登録ルール

承認済みの下書きだけ本登録する。

登録先:

- `amongus_records`
- `amongus_matches`
- `amongus_match_member_results`

反映時のルール:

- `archive_section_id` ごとに `amongus_records` を作成または取得する。
- `match_number` ごとに `amongus_matches` を作成または更新する。
- `member_name` は `members.name` に照合する。
- `role_name` は `roles.name` に照合する。
- 照合できない参加者や役職は本登録せず、下書きに警告を残す。
- 既存の手入力データがある場合は、上書きしない設定をデフォルトにする。

## 注意点

動画解析だけで全員分の役職や勝敗を確定できるとは限らない。

特に次のケースは誤判定しやすい。

- 配信者視点にリザルト画面が映っていない
- 役職表示が一瞬しか映らない
- MOD役職名が字幕やOCRで崩れる
- 複数試合の区切りが曖昧
- 参加者名の表記ゆれがある

そのため、初期実装では自動本登録をしない。必ず下書き確認を挟む。

## 最初の実装範囲

初期実装では次の範囲に絞る。

1. 下書きテーブルを作る。
2. 一次会 `Among Us` の配信URLを抽出するLaravelコマンドを作る。
3. Pythonへ渡す入力JSONを作る。
4. Pythonのダミー解析結果を下書き保存できるようにする。
5. 管理画面で下書き一覧を見られるようにする。

本格的な動画解析、Whisper、OCR、Vision API連携は次の段階で追加する。

## 追記: フレーム解析による役職補完（2026-07-23検討）

### 背景

字幕・音声文字起こしだけでは役職特定に限界がある。

- 配信者が自分の役職を言葉で名乗るとは限らない。
- 即死などで発言機会自体がないケースがある。
- このグループの配信は試合中に役職が頻繁に変わる（転向など）ため、開始時役職だけでは最終結果を代用できない。

### 追加方針: フレーム画像解析

字幕検出に加えて、動画フレームを画像として切り出し、画面に表示される役職・リザルト情報を直接読み取る。

処理手順:

1. 字幕・音声のヒット時刻（勝利/敗北アナウンス、緊急会議のキーワードなど）を起点に、前後数秒〜十数秒の範囲だけフレームを切り出す（`ffmpeg`。全編抽出は避け、対象区間だけに絞る）。
2. `ffmpeg` のシーンチェンジ検出（`select='gt(scene,0.3)'` 等）で、画面が大きく切り替わった瞬間だけを候補フレームに絞り込む。
3. 絞り込んだ候補フレームを画像として保存し、Vision解析（OCRまたはVision API等）で役職・勝敗テキストを読み取る。
4. 読み取り結果を試合番号・タイムスタンプと紐付けて下書きに記録する。

短時間（1フレームのみ等）しか表示されない画面でも、対象区間を絞った上で全フレーム抽出すれば理論上は取りこぼさない。

### 役職チェックポイントの多重化

このグループは試合中に役職が変わりやすいため、開始時役職だけでは最終結果を代用できない。複数のチェックポイントを設け、時系列で役職の変化を追う設計にする。

1. **開始時の役職（基準値）**
   - 各配信者は自分の役職を本人視点で見ているため、開始直後の役職公開演出は本人の役職に限り比較的取りこぼしにくい。
   - あくまで基準値であり、最終結果の代用にはしない。
2. **会議中に映る役職（中間チェックポイント、複数回発生しうる）**
   - Among Usの会議画面は暗転して全員のアイコンが並ぶ独特なUIのため、シーンチェンジ検出で機械的に見つけやすい。
   - 字幕上の「緊急会議」「通報」等のキーワードも会議開始の手がかりに使える。
   - 役職がいつ変化したかを特定する手がかりになる。
3. **最終リザルト画面の役職（最重要、確定値）**
   - このグループでは役職が頻繁に変わるため、開始時役職では代用できない。最終リザルトが最優先の情報源。
   - 1人の配信者の画面だけでは全員分揃うとは限らない（結果画面をすぐ閉じる、UI上スクロールしないと見えない等）ため、**同じ試合に参加している複数配信者のフレームを突き合わせて、欠けている役職を補完する**。

チェックポイント間で矛盾（例: 会議中はクルー役職だったが最終はインポスター）があれば「転向あり」として記録し、下書きの `evidence_text` に経緯を残す。

### 優先順位

1. 最終リザルト画面の役職（最重要、複数配信者のフレームを突き合わせて補完）
2. 会議中に映る役職（転向タイミングの特定・矛盾チェック）
3. 開始時役職（基準値・矛盾チェック用途）
4. 字幕・音声（タイムスタンプの目印、勝敗陣営の推定に利用。個人の役職特定には使わない）

### 今後の検討事項

- 複数配信者のフレームをどう名寄せ・突合するか（試合番号・実時間帯での紐付け方法）。
- シーンチェンジ検出のしきい値調整（会議画面・リザルト画面それぞれの検出精度）。
- Vision解析の実行主体（Claude API連携か、別のOCR/Vision APIか）。
