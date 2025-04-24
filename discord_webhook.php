<?php
/**
 * Citrus 配信通知 Bot - Discord Webhook送信クラス
 */

require_once __DIR__ . '/config.php';

class DiscordWebhook {
    /**
     * 通知を送信
     */
    public function sendNotification($webhookUrl, $streamData, $platform) {
        // プラットフォームによって色を変更
        $color = ($platform === 'twitch') ? 0x6441A4 : 0xFF0000; // TwitchはPurple, YouTubeはRed
        
        // タイムスタンプをフォーマット
        $startedAt = isset($streamData['started_at']) ? date('Y-m-d H:i:s', strtotime($streamData['started_at'])) : date('Y-m-d H:i:s');
        
        // 視聴者数
        $viewerCount = isset($streamData['viewer_count']) ? $streamData['viewer_count'] : 0;
        
        // エンベッドを作成
        $embed = [
            'title' => $streamData['title'],
            'type' => 'rich',
            'description' => ($platform === 'twitch') 
                ? "{$streamData['user_name']}さんが配信を開始しました！" 
                : "{$streamData['channel_title']}さんが配信を開始しました！",
            'url' => $streamData['url'],
            'timestamp' => date('c'),
            'color' => $color,
            'thumbnail' => [
                'url' => ($platform === 'twitch') 
                    ? "https://static-cdn.jtvnw.net/jtv_user_pictures/{$streamData['user_id']}-profile_image-300x300.png" 
                    : null
            ],
            'image' => [
                'url' => $streamData['thumbnail_url']
            ],
            'footer' => [
                'text' => 'Citrus 配信通知 Bot'
            ],
            'fields' => [
                [
                    'name' => 'プラットフォーム',
                    'value' => ($platform === 'twitch') ? 'Twitch' : 'YouTube',
                    'inline' => true
                ],
                [
                    'name' => '配信開始時間',
                    'value' => $startedAt,
                    'inline' => true
                ]
            ]
        ];
        
        // 視聴者数がある場合は追加
        if ($viewerCount > 0) {
            $embed['fields'][] = [
                'name' => '視聴者数',
                'value' => number_format($viewerCount) . '人',
                'inline' => true
            ];
        }
        
        // ペイロードを組み立て
        $payload = [
            'embeds' => [$embed]
        ];
        
        // JSONにエンコード
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Webhookに送信
        return $this->sendWebhook($webhookUrl, $jsonPayload);
    }
    
    /**
     * カスタムメッセージテンプレートで通知を送信（オプション）
     */
    public function sendCustomNotification($webhookUrl, $streamData, $platform, $template) {
        // テンプレートにプレースホルダがあれば置換
        $placeholders = [
            '{title}' => $streamData['title'],
            '{channel_name}' => ($platform === 'twitch') ? $streamData['user_name'] : $streamData['channel_title'],
            '{url}' => $streamData['url'],
            '{started_at}' => isset($streamData['started_at']) ? date('Y-m-d H:i:s', strtotime($streamData['started_at'])) : date('Y-m-d H:i:s'),
            '{viewer_count}' => isset($streamData['viewer_count']) ? number_format($streamData['viewer_count']) : '0',
            '{platform}' => ($platform === 'twitch') ? 'Twitch' : 'YouTube',
            '{thumbnail}' => $streamData['thumbnail_url']
        ];
        
        foreach ($placeholders as $placeholder => $value) {
            $template = str_replace($placeholder, $value, $template);
        }
        
        // 基本的な構造のJSONペイロードを作成
        $payload = [
            'content' => $template
        ];
        
        // JSONにエンコード
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Webhookに送信
        return $this->sendWebhook($webhookUrl, $jsonPayload);
    }
    
    /**
     * Webhookに実際に送信する
     */
    private function sendWebhook($webhookUrl, $jsonPayload) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $webhookUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonPayload)
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($status < 200 || $status >= 300) {
            $this->logError("Discord通知送信失敗: HTTP $status - $response");
            return false;
        }
        
        return true;
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
} 