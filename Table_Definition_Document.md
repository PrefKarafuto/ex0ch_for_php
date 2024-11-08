## ex0ch for PHP テーブル定義書

### 1. カテゴリテーブル（**categories**）

**説明**: 電子掲示板のカテゴリ情報を管理します。

| カラム名          | データ型 | NOT NULL | PRIMARY KEY | FOREIGN KEY | デフォルト値 | 説明                         |
|-------------------|----------|----------|-------------|-------------|--------------|------------------------------|
| **category_id**   | INTEGER  | YES      | YES         |             |              | カテゴリの識別子（ユニーク） |
| category_name     | TEXT     | YES      |             |             |              | カテゴリ名                   |
| description       | TEXT     |          |             |             |              | カテゴリの説明               |
| **board_count**   | INTEGER  | YES      |             |             | 0            | 所属する掲示板数             |

---

### 2. 掲示板テーブル（**boards**）

**説明**: 各カテゴリ内の掲示板情報を管理します。

| カラム名          | データ型 | NOT NULL | PRIMARY KEY | FOREIGN KEY                        | デフォルト値 | 説明                         |
|-------------------|----------|----------|-------------|------------------------------------|--------------|------------------------------|
| **board_id**      | INTEGER  | YES      | YES         |                                    |              | 掲示板の識別子（ユニーク）   |
| category_id       | INTEGER  | YES      |             | REFERENCES categories(category_id) |              | 所属するカテゴリの識別子     |
| board_name        | TEXT     | YES      |             |                                    |              | 掲示板名                     |
| description       | TEXT     |          |             |                                    |              | 掲示板の説明                 |
| local_rules       | TEXT     |          |             |                                    |              | ローカルルールのテキストデータ |
| **thread_count**  | INTEGER  | YES      |             |                                    | 0            | 所属するスレッド数           |

---

### 3. スレッドテーブル（**threads**）

**説明**: 掲示板内のスレッド情報を管理します。

| カラム名          | データ型 | NOT NULL | PRIMARY KEY       | FOREIGN KEY                   | デフォルト値 | 説明                                                     |
|-------------------|----------|----------|-------------------|-------------------------------|--------------|----------------------------------------------------------|
| **board_id**      | INTEGER  | YES      | YES（複合主キー） | REFERENCES boards(board_id)   |              | 所属する掲示板の識別子                                   |
| **thread_id**     | INTEGER  | YES      | YES（複合主キー） |                               |              | スレッドの識別子（掲示板内でユニーク）                   |
| title             | TEXT     | YES      |                   |                               |              | スレッドタイトル                                         |
| is_active         | BOOLEAN  | YES      |                   |                               | 1            | アクティブ状態（1: アクティブ、0: アーカイブ）           |
| attributes        | TEXT     |          |                   |                               |              | 任意の属性（JSON形式など）                               |
| created_at        | INTEGER  | YES      |                   |                               |              | 作成日時（UNIXTIME（ミリ秒））                            |
| **last_post_at**  | INTEGER  | YES      |                   |                               |              | 最終投稿日時（UNIXTIME（ミリ秒））                        |
| **post_count**    | INTEGER  | YES      |                   |                               | 1            | 投稿数（初期値は1、スレッド作成時の投稿を含む）          |

---

### 4. 投稿テーブル（**posts**）

**説明**: スレッド内の投稿情報を管理します。

| カラム名          | データ型 | NOT NULL | PRIMARY KEY       | FOREIGN KEY                                           | デフォルト値 | 説明                                                 |
|-------------------|----------|----------|-------------------|-------------------------------------------------------|--------------|------------------------------------------------------|
| post_id           | INTEGER  | YES      |                   |                                                       |              | 投稿の識別子（自動増分）                             |
| **board_id**      | INTEGER  | YES      | YES（複合主キー） | REFERENCES threads(board_id)                          |              | 所属する掲示板の識別子                               |
| **thread_id**     | INTEGER  | YES      | YES（複合主キー） | REFERENCES threads(thread_id)                         |              | 所属するスレッドの識別子                             |
| **post_number**   | INTEGER  | YES      | YES（複合主キー） |                                                       |              | スレッド内での投稿番号（1から始まる連番）            |
| name              | TEXT     |          |                   |                                                       |              | 投稿者の名前                                         |
| email             | TEXT     |          |                   |                                                       |              | 投稿者のメールアドレス                               |
| body              | TEXT     | YES      |                   |                                                       |              | 投稿内容                                             |
| created_at        | INTEGER  | YES      |                   |                                                       |              | 投稿日時（UNIXTIME（ミリ秒））                         |
| id                | TEXT     | YES      |                   |                                                       |              | 自動生成された投稿ID                                 |
| ip_address        | TEXT     |          |                   |                                                       |              | 投稿者のIPアドレス（アクティブ時のみ保存）           |
| user_agent        | TEXT     |          |                   |                                                       |              | 投稿者のユーザーエージェント（アクティブ時のみ保存） |
| session_id        | TEXT     |          |                   |                                                       |              | セッションID（アクティブ時のみ保存）                 |

---

### 5. セッションテーブル（**sessions**）

**説明**: ユーザーのセッション情報を管理します。

| カラム名          | データ型 | NOT NULL | PRIMARY KEY | FOREIGN KEY | デフォルト値 | 説明                             |
|-------------------|----------|----------|-------------|-------------|--------------|----------------------------------|
| session_id        | TEXT     | YES      | YES         |             |              | セッションの識別子                |
| user_info         | TEXT     |          |             |             |              | ユーザー情報（名前、メールなど）   |
| created_at        | INTEGER  | YES      |             |             |              | セッション開始日時                |
| last_activity     | INTEGER  |          |             |             |              | 最終アクセス日時                  |

---

### 6. システム設定テーブル（**system_config**）

**説明**: システム全体の設定情報を管理します。

| カラム名          | データ型 | NOT NULL | PRIMARY KEY | FOREIGN KEY | デフォルト値 | 説明              |
|-------------------|----------|----------|-------------|-------------|--------------|-------------------|
| key               | TEXT     | YES      | YES         |             |              | 設定項目のキー     |
| value             | TEXT     |          |             |             |              | 設定項目の値       |

---

### 7. 掲示板設定テーブル（**board_config**）

**説明**: 各掲示板の設定情報を管理します。

| カラム名          | データ型 | NOT NULL | PRIMARY KEY       | FOREIGN KEY                 | デフォルト値 | 説明              |
|-------------------|----------|----------|-------------------|-----------------------------|--------------|-------------------|
| **board_id**      | INTEGER  | YES      | YES（複合主キー） | REFERENCES boards(board_id) |              | 掲示板の識別子     |
| key               | TEXT     | YES      | YES（複合主キー） |                             |              | 設定項目のキー     |
| value             | TEXT     |          |                   |                             |              | 設定項目の値       |

---
