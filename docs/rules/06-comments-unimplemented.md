# コメント機能（★未実装）

対象機能（想定）: 管理画面「コメントの返信・削除」。

## 結論: この機能はコード上に存在しない

以下を確認済み:

- `app/Http/Controllers/` および `app/Http/Controllers/Admin/` に `CommentController` は存在しない。
- `routes/web.php`（全101行）に `comment` を含むルートは1件も無い（`grep -n -i "comment" routes/web.php` の結果が空）。
- `resources/views` にコメント関連のビューは無い。
- `Comment::create()` を呼んでいるのは `database/seeders/CommentSeeder.php` の1箇所のみで、これはテスト用の固定文言（`'テストコメントです！'`）を挿入するだけのシーダー。

つまり、**コメントの投稿・返信・削除のいずれの操作も、現状のコードでは一切実行できない。** 管理画面に「コメントの返信・削除」というメニュー項目がある場合、リンク先が存在しないか、何も処理しないプレースホルダになっているはずです。

## 現状存在するもの（未配線のモデル・テーブルのみ）

`app/Models/Comment.php`（19行）:
```php
public function user() { return $this->belongsTo(User::class); }
public function onedayarchive() { return $this->belongsTo(Onedayarchive::class); }
```

マイグレーション `database/migrations/2026_04_15_033652_create_comments_table.php`（`14-20`）のカラム:
`id, user_id(FK, cascade), onedayarchive_id(FK, cascade), content(text), timestamps`

この時点で分かる設計上の制約（実装されれば、の話）:
- **`name` や返信先を示す `parent_id` は無い。** 現状のテーブル構造ではスレッド返信（コメントへの返信）は表現できない。「返信」を実装する場合はカラム追加が必要。
- **承認/非承認・非表示等のモデレーション用ステータスカラムが無い。**
- **`user_id` が必須の外部キー**なので、匿名の名前だけでの投稿は現テーブル構造では不可（`User`としてログイン済みである必要がある）。
- コメントは `onedayarchive_id` に紐づく設計＝1日のアーカイブ単位。セクション単位（0次会/1次会等）には紐づけられない。

`app/Http/Controllers/OnedayarchiveController.php` も存在するが、`index()` が `Onedayarchive::all()` を返して存在しないビュー `onedayarchives.index` を呼ぶだけで、ルート未登録のデッドコード。コメント機能同様、着手途中で止まっている可能性が高い。

## このドキュメントでやってほしいこと

「コメントの返信・削除」を今後実装するなら、少なくとも以下を決める必要がある:
1. 返信（スレッド化）を表現するカラム設計（`parent_id` 等）の追加
2. 削除を物理削除にするか論理削除（`deleted_at`）にするか
3. 管理者による「返信」は誰の `User` として投稿されるのか（Admin用の投稿経路が要る）
4. 表示/非表示のモデレーション状態を持たせるか
