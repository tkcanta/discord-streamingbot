<?php
/**
 * Citrus 配信通知 Bot - データベース操作クラス
 */

require_once __DIR__ . '/config.php';

class Database {
    private $conn = null;
    
    /**
     * データベース接続を初期化
     */
    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            $this->logError("データベース接続エラー: " . $e->getMessage());
            die("データベース接続に失敗しました。");
        }
    }
    
    /**
     * データベーステーブルの初期化
     */
    public function initializeTables() {
        try {
            // Twitchチャンネルテーブルの作成
            $this->conn->exec("CREATE TABLE IF NOT EXISTS twitch_channels (
                channel_id VARCHAR(50) PRIMARY KEY,
                channel_name VARCHAR(100) NOT NULL,
                last_stream_id VARCHAR(50) DEFAULT NULL,
                is_live BOOLEAN DEFAULT FALSE,
                last_checked DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            // YouTubeチャンネルテーブルの作成
            $this->conn->exec("CREATE TABLE IF NOT EXISTS youtube_channels (
                channel_id VARCHAR(50) PRIMARY KEY,
                channel_name VARCHAR(100) NOT NULL,
                last_video_id VARCHAR(50) DEFAULT NULL,
                is_live BOOLEAN DEFAULT FALSE,
                last_checked DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Discord Webhookテーブルの作成
            $this->conn->exec("CREATE TABLE IF NOT EXISTS discord_webhooks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                server_name VARCHAR(100) NOT NULL,
                webhook_url VARCHAR(255) NOT NULL,
                message_template TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            // チャンネル登録リクエストテーブルの作成
            $this->conn->exec("CREATE TABLE IF NOT EXISTS channel_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                platform ENUM('twitch', 'youtube') NOT NULL,
                channel_id VARCHAR(50) NOT NULL,
                channel_name VARCHAR(100) NOT NULL,
                requester_name VARCHAR(100) NOT NULL,
                requester_email VARCHAR(255) NOT NULL,
                reason TEXT,
                status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY (platform, channel_id)
            )");
            
            return true;
        } catch (PDOException $e) {
            $this->logError("テーブル初期化エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Twitchチャンネルを追加
     */
    public function addTwitchChannel($channelId, $channelName) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO twitch_channels (channel_id, channel_name) 
                VALUES (:channel_id, :channel_name)
                ON DUPLICATE KEY UPDATE channel_name = :channel_name_update
            ");
            
            $stmt->bindValue(':channel_id', $channelId);
            $stmt->bindValue(':channel_name', $channelName);
            $stmt->bindValue(':channel_name_update', $channelName);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            $this->logError("Twitchチャンネル追加エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * YouTubeチャンネルを追加
     */
    public function addYoutubeChannel($channelId, $channelName) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO youtube_channels (channel_id, channel_name) 
                VALUES (:channel_id, :channel_name)
                ON DUPLICATE KEY UPDATE channel_name = :channel_name_update
            ");
            
            $stmt->bindValue(':channel_id', $channelId);
            $stmt->bindValue(':channel_name', $channelName);
            $stmt->bindValue(':channel_name_update', $channelName);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            $this->logError("YouTubeチャンネル追加エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Twitchチャンネルを削除
     */
    public function removeTwitchChannel($channelId) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM twitch_channels WHERE channel_id = :channel_id");
            return $stmt->execute([':channel_id' => $channelId]);
        } catch (PDOException $e) {
            $this->logError("Twitchチャンネル削除エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * YouTubeチャンネルを削除
     */
    public function removeYoutubeChannel($channelId) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM youtube_channels WHERE channel_id = :channel_id");
            return $stmt->execute([':channel_id' => $channelId]);
        } catch (PDOException $e) {
            $this->logError("YouTubeチャンネル削除エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Discord Webhookを追加
     */
    public function addDiscordWebhook($serverName, $webhookUrl, $messageTemplate = '') {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO discord_webhooks (server_name, webhook_url, message_template) 
                VALUES (:server_name, :webhook_url, :message_template)
            ");
            
            return $stmt->execute([
                ':server_name' => $serverName,
                ':webhook_url' => $webhookUrl,
                ':message_template' => $messageTemplate
            ]);
        } catch (PDOException $e) {
            $this->logError("Discord Webhook追加エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Discord Webhookを削除
     */
    public function removeDiscordWebhook($id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM discord_webhooks WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            $this->logError("Discord Webhook削除エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 全てのTwitchチャンネルを取得
     */
    public function getAllTwitchChannels() {
        try {
            $stmt = $this->conn->query("SELECT * FROM twitch_channels");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError("Twitchチャンネル取得エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 全てのYouTubeチャンネルを取得
     */
    public function getAllYoutubeChannels() {
        try {
            $stmt = $this->conn->query("SELECT * FROM youtube_channels");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError("YouTubeチャンネル取得エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 全てのDiscord Webhookを取得
     */
    public function getAllDiscordWebhooks() {
        try {
            $stmt = $this->conn->query("SELECT * FROM discord_webhooks");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError("Discord Webhook取得エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Twitchチャンネルの配信状態を更新
     */
    public function updateTwitchStreamStatus($channelId, $isLive, $streamId = null) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE twitch_channels 
                SET is_live = :is_live, 
                    last_stream_id = :stream_id,
                    last_checked = NOW() 
                WHERE channel_id = :channel_id
            ");
            
            return $stmt->execute([
                ':is_live' => $isLive ? 1 : 0,
                ':stream_id' => $streamId,
                ':channel_id' => $channelId
            ]);
        } catch (PDOException $e) {
            $this->logError("Twitch配信状態更新エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * YouTubeチャンネルの配信状態を更新
     */
    public function updateYoutubeStreamStatus($channelId, $isLive, $videoId = null) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE youtube_channels 
                SET is_live = :is_live, 
                    last_video_id = :video_id,
                    last_checked = NOW() 
                WHERE channel_id = :channel_id
            ");
            
            return $stmt->execute([
                ':is_live' => $isLive ? 1 : 0,
                ':video_id' => $videoId,
                ':channel_id' => $channelId
            ]);
        } catch (PDOException $e) {
            $this->logError("YouTube配信状態更新エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * エラーログを記録
     */
    private function logError($message) {
        if (LOG_ENABLED) {
            $logDir = dirname(LOG_FILE);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents(
                LOG_FILE, 
                "[$timestamp] [ERROR] $message\n", 
                FILE_APPEND
            );
        }
    }
    
    /**
     * デストラクタ - 接続を閉じる
     */
    public function __destruct() {
        $this->conn = null;
    }

    // チャンネル登録リクエストの追加
    public function addChannelRequest($platform, $channelId, $channelName, $requesterName, $requesterEmail, $reason) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO channel_requests (platform, channel_id, channel_name, requester_name, requester_email, reason) 
                                         VALUES (?, ?, ?, ?, ?, ?)
                                         ON DUPLICATE KEY UPDATE 
                                         channel_name = ?, requester_name = ?, requester_email = ?, reason = ?, status = 'pending'");
            
            return $stmt->execute([$platform, $channelId, $channelName, $requesterName, $requesterEmail, $reason, 
                                  $channelName, $requesterName, $requesterEmail, $reason]);
        } catch (PDOException $e) {
            $this->logError("チャンネルリクエスト追加エラー: " . $e->getMessage());
            return false;
        }
    }

    // 全てのチャンネル登録リクエストを取得
    public function getAllChannelRequests($status = null) {
        try {
            $sql = "SELECT * FROM channel_requests";
            if ($status) {
                $sql .= " WHERE status = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$status]);
            } else {
                $stmt = $this->conn->query($sql);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("チャンネルリクエスト取得エラー: " . $e->getMessage());
            return [];
        }
    }

    // チャンネル登録リクエストのステータス更新
    public function updateChannelRequestStatus($requestId, $status) {
        try {
            $stmt = $this->conn->prepare("UPDATE channel_requests SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $requestId]);
        } catch (PDOException $e) {
            $this->logError("リクエストステータス更新エラー: " . $e->getMessage());
            return false;
        }
    }

    // チャンネル登録リクエストの承認と実際のチャンネル追加
    public function approveChannelRequest($requestId) {
        try {
            $this->conn->beginTransaction();
            
            // リクエスト情報を取得
            $stmt = $this->conn->prepare("SELECT * FROM channel_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                throw new Exception("リクエストが見つかりません");
            }

            // プラットフォームに基づいてチャンネルを追加
            $success = false;
            if ($request['platform'] === 'twitch') {
                $success = $this->addTwitchChannel($request['channel_id'], $request['channel_name']);
            } else if ($request['platform'] === 'youtube') {
                $success = $this->addYoutubeChannel($request['channel_id'], $request['channel_name']);
            }
            
            if (!$success) {
                throw new Exception("チャンネル追加に失敗しました");
            }
            
            // リクエストのステータスを更新
            $updateStmt = $this->conn->prepare("UPDATE channel_requests SET status = 'approved' WHERE id = ?");
            $updateStmt->execute([$requestId]);
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->logError("リクエスト承認エラー: " . $e->getMessage());
            return false;
        }
    }

    // チャンネル登録リクエストの拒否
    public function rejectChannelRequest($requestId) {
        return $this->updateChannelRequestStatus($requestId, 'rejected');
    }
} 