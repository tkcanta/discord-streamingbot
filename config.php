<?php
/**
 * Citrus 配信通知 Bot - 設定ファイル
 */

// データベース設定
define('DB_HOST', 'localhost');     // データベースホスト
define('DB_NAME', 'citrus_bot');    // データベース名
define('DB_USER', 'citrus_user');      // データベースユーザー名
define('DB_PASS', 'your_secure_password');      // データベースパスワード
define('DB_PORT', 3306);            // データベースポート

// API設定
// Twitch API
define('TWITCH_CLIENT_ID', 'your_twitch_client_id');
define('TWITCH_CLIENT_SECRET', 'your_twitch_client_secret');

// YouTube API
define('YOUTUBE_API_KEY', 'your_youtube_api_key');

// 動作設定
define('CHECK_INTERVAL', 300);      // チェック間隔（秒）
define('LOG_ENABLED', true);        // ログ有効/無効
define('LOG_FILE', __DIR__ . '/logs/citrus.log'); // ログファイルパス
define('DEBUG_MODE', true);        // デバッグモードを有効に変更

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// エラー表示設定
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ログディレクトリ確認とデバッグメッセージ（LOG_FILE定義後に移動）
if (!is_dir(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0777, true);
}
file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] DEBUG: スクリプト実行開始\n", FILE_APPEND); 