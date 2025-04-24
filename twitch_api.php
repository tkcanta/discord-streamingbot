<?php
/**
 * Citrus 配信通知 Bot - Twitch API操作クラス
 */

require_once __DIR__ . '/config.php';

class TwitchAPI {
    private $clientId;
    private $clientSecret;
    private $accessToken;
    private $tokenExpiration = 0;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->clientId = TWITCH_CLIENT_ID;
        $this->clientSecret = TWITCH_CLIENT_SECRET;
    }
    
    /**
     * アクセストークンを取得
     */
    public function getAccessToken() {
        // トークンが有効期限内であれば再利用
        if ($this->accessToken && time() < $this->tokenExpiration) {
            return $this->accessToken;
        }
        
        // 新しいトークンを取得
        $url = "https://id.twitch.tv/oauth2/token";
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials'
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($status !== 200) {
            $this->logError("Twitchアクセストークン取得失敗: HTTP $status");
            return false;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            $this->logError("Twitchアクセストークン取得失敗: レスポンス不正");
            return false;
        }
        
        $this->accessToken = $data['access_token'];
        $this->tokenExpiration = time() + $data['expires_in'] - 300; // 5分前に期限切れと判断
        
        return $this->accessToken;
    }
    
    /**
     * チャンネル情報を取得
     */
    public function getChannelInfo($channelName) {
        $token = $this->getAccessToken();
        if (!$token) {
            return false;
        }
        
        $url = "https://api.twitch.tv/helix/users?login=" . urlencode($channelName);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $token,
                "Client-ID: " . $this->clientId
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($status !== 200) {
            $this->logError("Twitchチャンネル情報取得失敗: HTTP $status");
            return false;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['data']) || empty($data['data'])) {
            return false;
        }
        
        return $data['data'][0];
    }
    
    /**
     * 配信状態を取得
     */
    public function getStreamStatus($channelId) {
        $token = $this->getAccessToken();
        if (!$token) {
            return false;
        }
        
        $url = "https://api.twitch.tv/helix/streams?user_id=" . urlencode($channelId);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $token,
                "Client-ID: " . $this->clientId
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($status !== 200) {
            $this->logError("Twitch配信状態取得失敗: HTTP $status");
            return false;
        }
        
        $data = json_decode($response, true);
        
        // 配信していない場合は空のデータ配列
        if (!isset($data['data']) || empty($data['data'])) {
            return ['is_live' => false];
        }
        
        // 配信中
        $streamData = $data['data'][0];
        return [
            'is_live' => true,
            'stream_id' => $streamData['id'],
            'title' => $streamData['title'],
            'viewer_count' => $streamData['viewer_count'],
            'started_at' => $streamData['started_at'],
            'thumbnail_url' => str_replace('{width}x{height}', '1280x720', $streamData['thumbnail_url']),
            'user_id' => $streamData['user_id'],
            'user_name' => $streamData['user_name'],
            'url' => 'https://twitch.tv/' . $streamData['user_login']
        ];
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