<?php
/**
 * Citrus 配信通知 Bot - チャンネル追加リクエストフォーム
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// セッション開始
session_start();

// データベース接続
$db = new Database();

// フラッシュメッセージ処理
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// リクエスト送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 必須フィールドの確認
    if (
        !empty($_POST['platform']) && 
        !empty($_POST['channel_id']) && 
        !empty($_POST['channel_name']) && 
        !empty($_POST['requester_name']) && 
        !empty($_POST['requester_email'])
    ) {
        $platform = $_POST['platform'];
        $channelId = trim($_POST['channel_id']);
        $channelName = trim($_POST['channel_name']);
        $requesterName = trim($_POST['requester_name']);
        $requesterEmail = trim($_POST['requester_email']);
        $reason = trim($_POST['reason'] ?? '');
        
        // メールアドレスの簡易検証
        if (!filter_var($requesterEmail, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('有効なメールアドレスを入力してください。', 'danger');
        } else {
            // リクエストをデータベースに保存
            $result = $db->addChannelRequest(
                $platform, 
                $channelId, 
                $channelName, 
                $requesterName, 
                $requesterEmail, 
                $reason
            );
            
            if ($result) {
                setFlashMessage('チャンネル追加リクエストを受け付けました。管理者の承認をお待ちください。');
            } else {
                setFlashMessage('リクエストの保存に失敗しました。すでに同じチャンネルがリクエストされている可能性があります。', 'danger');
            }
        }
    } else {
        setFlashMessage('すべての必須項目を入力してください。', 'danger');
    }
}

// フラッシュメッセージ取得
$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チャンネル追加リクエスト - Citrus 配信通知 Bot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .container {
            max-width: 700px;
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 500;
        }
        .required:after {
            content: " *";
            color: red;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>チャンネル追加リクエスト</h1>
            <p class="text-muted">TwitchまたはYouTubeのチャンネル追加をリクエストできます</p>
        </div>
        
        <?php if ($flashMessage): ?>
        <div class="alert alert-<?php echo $flashMessage['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $flashMessage['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-3">
                <label class="form-label required">プラットフォーム</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="platform" id="platform_twitch" value="twitch" checked>
                    <label class="form-check-label" for="platform_twitch">
                        Twitch
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="platform" id="platform_youtube" value="youtube">
                    <label class="form-check-label" for="platform_youtube">
                        YouTube
                    </label>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="channel_id" class="form-label required">チャンネルID</label>
                <input type="text" class="form-control" id="channel_id" name="channel_id" required>
                <div class="form-text" id="channel_id_help">
                    <span class="twitch-help">TwitchのチャンネルIDまたはユーザー名</span>
                    <span class="youtube-help" style="display:none;">YouTubeのチャンネルID (例: UC...)</span>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="channel_name" class="form-label required">チャンネル名</label>
                <input type="text" class="form-control" id="channel_name" name="channel_name" required>
            </div>
            
            <div class="mb-3">
                <label for="requester_name" class="form-label required">あなたの名前</label>
                <input type="text" class="form-control" id="requester_name" name="requester_name" required>
            </div>
            
            <div class="mb-3">
                <label for="requester_email" class="form-label required">メールアドレス</label>
                <input type="email" class="form-control" id="requester_email" name="requester_email" required>
                <div class="form-text">承認結果の通知のために使用されます</div>
            </div>
            
            <div class="mb-3">
                <label for="reason" class="form-label">リクエスト理由</label>
                <textarea class="form-control" id="reason" name="reason" rows="3"></textarea>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">リクエスト送信</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // プラットフォーム選択に応じてヘルプテキストを切り替える
        document.querySelectorAll('input[name="platform"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'twitch') {
                    document.querySelector('.twitch-help').style.display = 'inline';
                    document.querySelector('.youtube-help').style.display = 'none';
                } else {
                    document.querySelector('.twitch-help').style.display = 'none';
                    document.querySelector('.youtube-help').style.display = 'inline';
                }
            });
        });
    </script>
</body>
</html> 