<?php
/**
 * Citrus 配信通知 Bot - インストールスクリプト
 */

// PHPのバージョンチェック
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('PHP 7.4以上が必要です。現在のバージョン: ' . PHP_VERSION);
}

// 必要な拡張機能のチェック
$requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    die('以下の拡張機能が必要です: ' . implode(', ', $missingExtensions));
}

// 設定ファイルの存在チェック
if (!file_exists(__DIR__ . '/config.php')) {
    die('config.phpが見つかりません。config.php.exampleをconfig.phpにコピーし、適切に設定してください。');
}

// ディレクトリパーミッションチェック
if (!is_writable(__DIR__)) {
    die('現在のディレクトリに書き込み権限がありません。');
}

// ログディレクトリの作成
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0755, true)) {
        die('ログディレクトリの作成に失敗しました。');
    }
}

// 必要なファイルの読み込み
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// データベース接続テスト
try {
    $db = new Database();
    echo "データベース接続成功\n";
    
    // テーブル初期化
    $result = $db->initializeTables();
    if ($result) {
        echo "データベーステーブルの初期化成功\n";
    } else {
        die('データベーステーブルの初期化に失敗しました。');
    }
} catch (Exception $e) {
    die('データベース接続エラー: ' . $e->getMessage());
}

// テスト用のWebhook登録（必要に応じて）
if (isset($_GET['setup_test']) && $_GET['setup_test'] === '1') {
    $result = $db->addDiscordWebhook(
        'テストサーバー',
        'https://discord.com/api/webhooks/your_test_webhook_url',
        ''
    );
    
    if ($result) {
        echo "テスト用Webhookの登録成功\n";
    } else {
        echo "テスト用Webhookの登録失敗\n";
    }
}

// cron設定の説明
echo "\n";
echo "--------------------------------------------\n";
echo "インストール成功！\n";
echo "--------------------------------------------\n";
echo "次のステップ:\n";
echo "1. 以下のコマンドをcronに追加してください（5分ごとの実行例）:\n";
echo "   */5 * * * * php " . __DIR__ . "/check_streams.php\n\n";
echo "2. 管理画面を使用して、監視するチャンネルとWebhookを設定してください。\n";
echo "--------------------------------------------\n";

// 設定に応じて追加情報表示
if (DEBUG_MODE) {
    echo "\n警告: デバッグモードが有効です。本番環境では無効にしてください。\n";
} 