<?php
/**
 * Citrus 配信通知 Bot - YouTube API操作クラス
 */

require_once __DIR__ . '/config.php';

class YouTubeAPI {
    private $apiKey;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->apiKey = YOUTUBE_API_KEY;
    }
    
    /**
     * チャンネル情報を取得
     */
    public function getChannelInfo($channelId) {
        $url = "https://www.googleapis.com/youtube/v3/channels?part=snippet&id=" . urlencode($channelId) . "&key=" . $this->apiKey;
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($status !== 200) {
            $this->logError("YouTubeチャンネル情報取得失敗: HTTP $status");
            return false;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['items']) || empty($data['items'])) {
            return false;
        }
        
        return $data['items'][0];
    }
    
    /**
     * チャンネルIDからライブ配信状態を取得
     */
    public function getLiveStreamStatus($channelId) {
        // まずはチャンネルの実行中のライブ配信を検索
        $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" . urlencode($channelId) . 
               "&eventType=live&type=video&maxResults=1&key=" . $this->apiKey;
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($status !== 200) {
            $this->logError("YouTube配信状態取得失敗: HTTP $status");
            return false;
        }
        
        $data = json_decode($response, true);
        
        // 配信していない場合
        if (!isset($data['items']) || empty($data['items'])) {
            return ['is_live' => false];
        }
        
        $videoItem = $data['items'][0];
        $videoId = $videoItem['id']['videoId'];
        
        // 動画の詳細情報を取得
        $videoDetails = $this->getVideoDetails($videoId);
        if (!$videoDetails) {
            // 詳細情報が取得できなかった場合は基本情報のみ返す
            return [
                'is_live' => true,
                'video_id' => $videoId,
                'title' => $videoItem['snippet']['title'],
                'channel_title' => $videoItem['snippet']['channelTitle'],
                'thumbnail_url' => $videoItem['snippet']['thumbnails']['high']['url'],
                'url' => 'https://www.youtube.com/watch?v=' . $videoId
            ];
        }
        
        // 詳細情報と合わせて返す
        return [
            'is_live' => true,
            'video_id' => $videoId,
            'title' => $videoItem['snippet']['title'],
            'channel_title' => $videoItem['snippet']['channelTitle'],
            'thumbnail_url' => $videoItem['snippet']['thumbnails']['high']['url'],
            'url' => 'https://www.youtube.com/watch?v=' . $videoId,
            'viewer_count' => $videoDetails['viewer_count'] ?? 0,
            'started_at' => $videoDetails['started_at'] ?? null
        ];
    }
    
    /**
     * 動画の詳細情報を取得
     */
    private function getVideoDetails($videoId) {
        $url = "https://www.googleapis.com/youtube/v3/videos?part=liveStreamingDetails,snippet&id=" . urlencode($videoId) . "&key=" . $this->apiKey;
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($status !== 200) {
            $this->logError("YouTube動画詳細取得失敗: HTTP $status");
            return false;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['items']) || empty($data['items'])) {
            return false;
        }
        
        $videoItem = $data['items'][0];
        
        // ライブ配信詳細があるか確認
        if (!isset($videoItem['liveStreamingDetails'])) {
            return false;
        }
        
        return [
            'viewer_count' => $videoItem['liveStreamingDetails']['concurrentViewers'] ?? 0,
            'started_at' => $videoItem['liveStreamingDetails']['actualStartTime'] ?? null
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