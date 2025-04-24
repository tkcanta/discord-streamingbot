<?php
/**
 * Citrus 配信通知 Bot - 配信状態チェックスクリプト
 * cronで定期的に実行される
 */

// 必要なファイルを読み込み
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/twitch_api.php';
require_once __DIR__ . '/youtube_api.php';
require_once __DIR__ . '/discord_webhook.php';

// スクリプトのメモリ制限を引き上げ
ini_set('memory_limit', '256M');
// 実行時間制限を延長（秒単位）
set_time_limit(300);

/**
 * ログ記録関数
 */
function logMessage($message, $type = 'INFO') {
    if (LOG_ENABLED) {
        $logDir = dirname(LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents(
            LOG_FILE, 
            "[$timestamp] [$type] $message\n", 
            FILE_APPEND
        );
    }
}

/**
 * 配信チェック処理のメイン関数
 */
function checkStreams() {
    // 開始時間を記録
    $startTime = microtime(true);
    logMessage("配信チェック処理を開始");
    
    try {
        // 各APIと機能のインスタンスを作成
        $db = new Database();
        $twitchAPI = new TwitchAPI();
        $youtubeAPI = new YouTubeAPI();
        $discord = new DiscordWebhook();
        
        // データベーステーブルが存在することを確認
        $db->initializeTables();
        
        // Discord Webhookを取得
        $webhooks = $db->getAllDiscordWebhooks();
        
        if (empty($webhooks)) {
            logMessage("登録されたWebhookがありません");
            return;
        }
        
        // Twitch配信のチェック
        checkTwitchStreams($db, $twitchAPI, $discord, $webhooks);
        
        // YouTube配信のチェック
        checkYoutubeStreams($db, $youtubeAPI, $discord, $webhooks);
        
        // 処理時間を計算
        $executionTime = round(microtime(true) - $startTime, 2);
        logMessage("配信チェック処理を完了 ($executionTime 秒)");
        
    } catch (Exception $e) {
        logMessage("エラーが発生しました: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Twitch配信のチェック処理
 */
function checkTwitchStreams($db, $twitchAPI, $discord, $webhooks) {
    // 監視対象のTwitchチャンネルを取得
    $channels = $db->getAllTwitchChannels();
    
    if (empty($channels)) {
        logMessage("監視対象のTwitchチャンネルがありません");
        return;
    }
    
    logMessage("Twitchチャンネル " . count($channels) . "件をチェック中");
    
    foreach ($channels as $channel) {
        // 配信状態を取得
        $streamStatus = $twitchAPI->getStreamStatus($channel['channel_id']);
        
        if ($streamStatus === false) {
            logMessage("Twitchチャンネル {$channel['channel_name']} の配信状態取得に失敗", 'ERROR');
            continue;
        }
        
        // 配信開始検知
        if ($streamStatus['is_live'] && !$channel['is_live']) {
            logMessage("新規配信を検知: Twitch - {$channel['channel_name']}");
            
            // データベースの状態を更新
            $db->updateTwitchStreamStatus(
                $channel['channel_id'], 
                true, 
                $streamStatus['stream_id']
            );
            
            // 各Webhookに通知を送信
            foreach ($webhooks as $webhook) {
                $result = $discord->sendNotification(
                    $webhook['webhook_url'],
                    $streamStatus,
                    'twitch'
                );
                
                if ($result) {
                    logMessage("Discord通知送信成功: {$webhook['server_name']}");
                } else {
                    logMessage("Discord通知送信失敗: {$webhook['server_name']}", 'ERROR');
                }
            }
        }
        // 配信終了検知
        else if (!$streamStatus['is_live'] && $channel['is_live']) {
            logMessage("配信終了を検知: Twitch - {$channel['channel_name']}");
            
            // データベースの状態を更新
            $db->updateTwitchStreamStatus(
                $channel['channel_id'], 
                false, 
                null
            );
        }
        // その他の状態変化なし
        else {
            // 最終チェック時間だけ更新
            $db->updateTwitchStreamStatus(
                $channel['channel_id'], 
                $streamStatus['is_live'], 
                $streamStatus['is_live'] ? $streamStatus['stream_id'] : null
            );
        }
        
        // APIレート制限回避のため少し待機
        usleep(500000); // 0.5秒待機
    }
}

/**
 * YouTube配信のチェック処理
 */
function checkYoutubeStreams($db, $youtubeAPI, $discord, $webhooks) {
    // 監視対象のYouTubeチャンネルを取得
    $channels = $db->getAllYoutubeChannels();
    
    if (empty($channels)) {
        logMessage("監視対象のYouTubeチャンネルがありません");
        return;
    }
    
    logMessage("YouTubeチャンネル " . count($channels) . "件をチェック中");
    
    foreach ($channels as $channel) {
        // 配信状態を取得
        $streamStatus = $youtubeAPI->getLiveStreamStatus($channel['channel_id']);
        
        if ($streamStatus === false) {
            logMessage("YouTubeチャンネル {$channel['channel_name']} の配信状態取得に失敗", 'ERROR');
            continue;
        }
        
        // 配信開始検知
        if ($streamStatus['is_live'] && !$channel['is_live']) {
            logMessage("新規配信を検知: YouTube - {$channel['channel_name']}");
            
            // データベースの状態を更新
            $db->updateYoutubeStreamStatus(
                $channel['channel_id'], 
                true, 
                $streamStatus['video_id']
            );
            
            // 各Webhookに通知を送信
            foreach ($webhooks as $webhook) {
                $result = $discord->sendNotification(
                    $webhook['webhook_url'],
                    $streamStatus,
                    'youtube'
                );
                
                if ($result) {
                    logMessage("Discord通知送信成功: {$webhook['server_name']}");
                } else {
                    logMessage("Discord通知送信失敗: {$webhook['server_name']}", 'ERROR');
                }
            }
        }
        // 配信終了検知
        else if (!$streamStatus['is_live'] && $channel['is_live']) {
            logMessage("配信終了を検知: YouTube - {$channel['channel_name']}");
            
            // データベースの状態を更新
            $db->updateYoutubeStreamStatus(
                $channel['channel_id'], 
                false, 
                null
            );
        }
        // その他の状態変化なし
        else {
            // 最終チェック時間だけ更新
            $db->updateYoutubeStreamStatus(
                $channel['channel_id'], 
                $streamStatus['is_live'], 
                $streamStatus['is_live'] ? $streamStatus['video_id'] : null
            );
        }
        
        // APIレート制限回避のため少し待機
        usleep(500000); // 0.5秒待機
    }
}

// スクリプト実行
checkStreams(); 