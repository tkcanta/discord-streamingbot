# Citrus 配信通知 Bot

TwitchとYouTubeの配信開始を検知し、Discordに通知するPHPアプリケーションです。

## 特徴

- Twitch配信開始と配信終了の検知と通知
- YouTube配信開始と配信終了の検知と通知
- Discord Webhookによる通知
- Xserverなどの共有ホスティングで動作
- 管理画面によるチャンネルとWebhook設定
- 一般ユーザー向けチャンネル追加リクエスト機能
- MariaDB/MySQLをデータベースとして使用
- Bootstrap 5によるレスポンシブなWebインターフェース

## 必要条件

- PHP 7.4以上
- MariaDB 10.5またはMySQL 5.7以上
- cron（定期実行用）
- curl, PDO, JSON PHP拡張機能
- SSL証明書（API通信の安全性確保）

## インストール方法

1. ファイルをサーバーにアップロード
2. `config.php` の設定内容を環境に合わせて変更
   - データベース設定
   - Twitch API キー
   - YouTube API キー
3. `install.php` または `initialize_tables.php` をブラウザで実行し、初期セットアップを完了
4. cronの設定
5. 管理画面からチャンネルとWebhookを設定

## 設定方法

### 1. APIキーの取得

#### Twitch APIキー取得手順
1. [Twitch Developer Console](https://dev.twitch.tv/console/apps) でアカウント登録
2. 新しいアプリケーションを作成
3. Client IDとClient Secretを取得

#### YouTube APIキー取得手順
1. [Google Cloud Console](https://console.cloud.google.com/) でプロジェクト作成
2. YouTube Data API v3 を有効化
3. APIキーを作成

### 2. config.php の設定

```php
// データベース設定
define('DB_HOST', 'localhost');     // データベースホスト
define('DB_NAME', 'citrus_bot');    // データベース名
define('DB_USER', 'username');      // データベースユーザー名
define('DB_PASS', 'password');      // データベースパスワード

// API設定
define('TWITCH_CLIENT_ID', 'your_twitch_client_id');
define('TWITCH_CLIENT_SECRET', 'your_twitch_client_secret');
define('YOUTUBE_API_KEY', 'your_youtube_api_key');
```

### 3. データベース初期化

インストール時またはテーブル構造に問題がある場合は、以下のいずれかの方法でデータベーステーブルを初期化してください：

- `install.php` をブラウザで実行
- または `initialize_tables.php` をブラウザで実行

### 4. cronの設定

以下のコマンドを5分ごとに実行するように設定します：

```
*/5 * * * * php /path/to/check_streams.php
```

Xserverの場合、コントロールパネルからcronジョブを追加できます。

### 5. 管理画面

`admin/index.php` にアクセスし、以下のデフォルト認証情報でログイン：

- ユーザー名: admin
- パスワード: password

> **重要**: 実運用前にadmin/index.phpのベーシック認証設定を変更することを推奨します。
> **重要**: ユーザー名とパスワードは変更推奨

## 使用方法

### 管理者向け機能

1. 管理画面にログイン
2. 「Twitch」タブでTwitchチャンネルを追加
3. 「YouTube」タブでYouTubeチャンネルを追加
4. 「Webhooks」タブでDiscord WebhookのURLを設定
5. 「リクエスト」タブでユーザーからのチャンネル追加リクエストを管理
6. cronが定期的に配信状態をチェックし、配信開始時に通知を送信

### 一般ユーザー向け機能

1. メインページ（`request.php`）にアクセス
2. チャンネル追加リクエストフォームに必要情報を入力
   - プラットフォーム（TwitchまたはYouTube）
   - チャンネルID
   - チャンネル名
   - リクエスト者名
   - メールアドレス
   - リクエスト理由（オプション）
3. 送信後、管理者の承認を待つ

## フォルダ構成

```
/
├── admin/              - 管理画面
│   └── index.php       - 管理画面メイン
├── logs/               - ログファイル格納ディレクトリ
├── config.php          - 設定ファイル
├── config.php.example  - 設定ファイルのサンプル
├── database.php        - データベース操作クラス
├── twitch_api.php      - Twitch API操作クラス
├── youtube_api.php     - YouTube API操作クラス
├── discord_webhook.php - Discord通知クラス
├── request.php         - チャンネル追加リクエストフォーム
├── check_streams.php   - 配信チェックスクリプト（cronで実行）
├── install.php         - インストールスクリプト
├── initialize_tables.php - テーブル初期化スクリプト
├── README.md           - このファイル
└── 仕様書.md           - 詳細な仕様書
```

## セキュリティに関する注意

- 管理画面のベーシック認証情報は必ず変更してください
- API鍵は外部に漏れないよう注意してください
- 本番環境ではデバッグモードをオフにしてください
- ログファイルのアクセス制限を適切に設定してください

## トラブルシューティング

- ログファイルが作成されない場合は、logs/ディレクトリのパーミッションを確認
- APIキーが無効の場合は、有効期限や利用制限を確認
- データベース接続エラーが発生する場合は接続情報を確認
- データベーステーブルが存在しないエラーが出る場合は `initialize_tables.php` を実行
- チャンネルリクエスト機能が動作しない場合は、テーブルが正しく初期化されているか確認

## データベース構造

システムは以下の4つのテーブルを使用します：

1. `twitch_channels` - Twitchチャンネル情報
2. `youtube_channels` - YouTubeチャンネル情報
3. `discord_webhooks` - Discord Webhook設定
4. `channel_requests` - チャンネル追加リクエスト

テーブル構造の詳細は `仕様書.md` を参照してください。

## ライセンス

MITライセンス

## 作者

cantacancan

---

問題が発生した場合や機能改善の提案がある場合は、Issueを作成してください。
