<?php
/**
 * Citrus 配信通知 Bot - テーブル初期化スクリプト
 */

// 必要なファイルを読み込み
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

echo "Citrus Bot - テーブル初期化ツール\n";

try {
    // データベース接続
    $db = new Database();
    echo "データベース接続成功\n";
    
    // テーブル初期化
    $result = $db->initializeTables();
    if ($result) {
        echo "データベーステーブルの初期化成功\n";
        echo "channel_requestsテーブルが正常に作成されました。\n";
    } else {
        echo "データベーステーブルの初期化に失敗しました。\n";
    }
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}

// テーブルが存在するか確認
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $tables = ['twitch_channels', 'youtube_channels', 'discord_webhooks', 'channel_requests'];
    
    echo "\nテーブル存在確認:\n";
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "- $table: 存在します\n";
        } else {
            echo "- $table: 存在しません\n";
        }
    }
} catch (PDOException $e) {
    echo "テーブル確認エラー: " . $e->getMessage() . "\n";
}

echo "\n初期化処理完了\n"; 