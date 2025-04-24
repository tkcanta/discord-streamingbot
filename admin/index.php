<?php
/**
 * Citrus 配信通知 Bot - 管理画面
 */

session_start();

// 必要なファイルを読み込み
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../twitch_api.php';
require_once __DIR__ . '/../youtube_api.php';

// ベーシック認証（簡易的な保護）
// 実際の運用ではより堅牢な認証方法が推奨されます
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) || 
    $_SERVER['PHP_AUTH_USER'] !== 'admin' || $_SERVER['PHP_AUTH_PW'] !== 'password') {
    header('WWW-Authenticate: Basic realm="Citrus Bot Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo '認証が必要です';
    exit;
}

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

// アクション処理
$action = $_GET['action'] ?? '';

// チャンネルリクエスト承認
if ($action === 'approve_request' && isset($_GET['id'])) {
    $requestId = $_GET['id'];
    $result = $db->approveChannelRequest($requestId);
    
    if ($result) {
        setFlashMessage("リクエストを承認しました。");
    } else {
        setFlashMessage("リクエストの承認に失敗しました。", "danger");
    }
    
    header('Location: index.php?page=requests');
    exit;
}

// チャンネルリクエスト拒否
if ($action === 'reject_request' && isset($_GET['id'])) {
    $requestId = $_GET['id'];
    $result = $db->rejectChannelRequest($requestId);
    
    if ($result) {
        setFlashMessage("リクエストを拒否しました。");
    } else {
        setFlashMessage("リクエストの拒否に失敗しました。", "danger");
    }
    
    header('Location: index.php?page=requests');
    exit;
}

// Twitchチャンネル追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_twitch') {
    if (isset($_POST['channel_name']) && !empty($_POST['channel_name'])) {
        $channelName = trim($_POST['channel_name']);
        
        // Twitchチャンネル情報取得
        $twitchAPI = new TwitchAPI();
        $channelInfo = $twitchAPI->getChannelInfo($channelName);
        
        if ($channelInfo) {
            $result = $db->addTwitchChannel($channelInfo['id'], $channelInfo['display_name']);
            
            if ($result) {
                setFlashMessage("Twitchチャンネル「{$channelInfo['display_name']}」を追加しました。");
            } else {
                setFlashMessage("Twitchチャンネルの追加に失敗しました。", "danger");
            }
        } else {
            setFlashMessage("Twitchチャンネル「{$channelName}」が見つかりませんでした。", "danger");
        }
    } else {
        setFlashMessage("チャンネル名を入力してください。", "danger");
    }
    
    header('Location: index.php?page=twitch');
    exit;
}

// YouTubeチャンネル追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_youtube') {
    if (isset($_POST['channel_id']) && !empty($_POST['channel_id'])) {
        $channelId = trim($_POST['channel_id']);
        
        // YouTubeチャンネル情報取得
        $youtubeAPI = new YouTubeAPI();
        $channelInfo = $youtubeAPI->getChannelInfo($channelId);
        
        if ($channelInfo) {
            $result = $db->addYoutubeChannel(
                $channelId, 
                $channelInfo['snippet']['title']
            );
            
            if ($result) {
                setFlashMessage("YouTubeチャンネル「{$channelInfo['snippet']['title']}」を追加しました。");
            } else {
                setFlashMessage("YouTubeチャンネルの追加に失敗しました。", "danger");
            }
        } else {
            setFlashMessage("YouTubeチャンネルIDが無効です。", "danger");
        }
    } else {
        setFlashMessage("チャンネルIDを入力してください。", "danger");
    }
    
    header('Location: index.php?page=youtube');
    exit;
}

// Discord Webhook追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_webhook') {
    if (isset($_POST['server_name']) && !empty($_POST['server_name']) && 
        isset($_POST['webhook_url']) && !empty($_POST['webhook_url'])) {
        
        $serverName = trim($_POST['server_name']);
        $webhookUrl = trim($_POST['webhook_url']);
        $template = $_POST['message_template'] ?? '';
        
        // URLの形式チェック（簡易的）
        if (filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            $result = $db->addDiscordWebhook($serverName, $webhookUrl, $template);
            
            if ($result) {
                setFlashMessage("Discord Webhook「{$serverName}」を追加しました。");
            } else {
                setFlashMessage("Discord Webhookの追加に失敗しました。", "danger");
            }
        } else {
            setFlashMessage("無効なWebhook URLです。", "danger");
        }
    } else {
        setFlashMessage("サーバー名とWebhook URLを入力してください。", "danger");
    }
    
    header('Location: index.php?page=webhooks');
    exit;
}

// Twitchチャンネル削除
if ($action === 'delete_twitch' && isset($_GET['id'])) {
    $channelId = $_GET['id'];
    $result = $db->removeTwitchChannel($channelId);
    
    if ($result) {
        setFlashMessage("Twitchチャンネルを削除しました。");
    } else {
        setFlashMessage("Twitchチャンネルの削除に失敗しました。", "danger");
    }
    
    header('Location: index.php?page=twitch');
    exit;
}

// YouTubeチャンネル削除
if ($action === 'delete_youtube' && isset($_GET['id'])) {
    $channelId = $_GET['id'];
    $result = $db->removeYoutubeChannel($channelId);
    
    if ($result) {
        setFlashMessage("YouTubeチャンネルを削除しました。");
    } else {
        setFlashMessage("YouTubeチャンネルの削除に失敗しました。", "danger");
    }
    
    header('Location: index.php?page=youtube');
    exit;
}

// Discord Webhook削除
if ($action === 'delete_webhook' && isset($_GET['id'])) {
    $webhookId = $_GET['id'];
    $result = $db->removeDiscordWebhook($webhookId);
    
    if ($result) {
        setFlashMessage("Discord Webhookを削除しました。");
    } else {
        setFlashMessage("Discord Webhookの削除に失敗しました。", "danger");
    }
    
    header('Location: index.php?page=webhooks');
    exit;
}

// 表示するページを決定
$page = $_GET['page'] ?? 'dashboard';
$validPages = ['dashboard', 'twitch', 'youtube', 'webhooks', 'settings', 'requests'];

if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}

// 保留中のリクエスト数を取得
$pendingRequests = $db->getAllChannelRequests('pending');
$pendingRequestCount = count($pendingRequests);

// 各ページのデータ取得
$twitchChannels = ($page === 'dashboard' || $page === 'twitch') ? $db->getAllTwitchChannels() : [];
$youtubeChannels = ($page === 'dashboard' || $page === 'youtube') ? $db->getAllYoutubeChannels() : [];
$webhooks = ($page === 'dashboard' || $page === 'webhooks') ? $db->getAllDiscordWebhooks() : [];
$channelRequests = ($page === 'requests') ? $db->getAllChannelRequests() : [];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citrus 配信通知 Bot - 管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 20px; }
        .navbar { margin-bottom: 20px; }
        .flash-message { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1 class="text-center mb-4">Citrus 配信通知 Bot</h1>
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav">
                            <li class="nav-item">
                                <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard">ダッシュボード</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $page === 'twitch' ? 'active' : '' ?>" href="index.php?page=twitch">Twitch</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $page === 'youtube' ? 'active' : '' ?>" href="index.php?page=youtube">YouTube</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $page === 'webhooks' ? 'active' : '' ?>" href="index.php?page=webhooks">Webhooks</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $page === 'settings' ? 'active' : '' ?>" href="index.php?page=settings">設定</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $page === 'requests' ? 'active' : '' ?>" href="index.php?page=requests">
                                    リクエスト
                                    <?php if ($pendingRequestCount > 0): ?>
                                    <span class="badge bg-danger"><?= $pendingRequestCount ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>
        
        <?php
        // フラッシュメッセージ表示
        $flashMessage = getFlashMessage();
        if ($flashMessage) {
            echo '<div class="alert alert-' . $flashMessage['type'] . ' flash-message">' . 
                $flashMessage['message'] . '</div>';
        }
        ?>
        
        <main>
            <?php if ($page === 'dashboard'): ?>
                <h2>ダッシュボード</h2>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Twitchチャンネル</h5>
                                <p class="card-text display-4"><?= count($twitchChannels) ?></p>
                                <a href="index.php?page=twitch" class="btn btn-primary btn-sm">管理</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">YouTubeチャンネル</h5>
                                <p class="card-text display-4"><?= count($youtubeChannels) ?></p>
                                <a href="index.php?page=youtube" class="btn btn-primary btn-sm">管理</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Discord Webhook</h5>
                                <p class="card-text display-4"><?= count($webhooks) ?></p>
                                <a href="index.php?page=webhooks" class="btn btn-primary btn-sm">管理</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($pendingRequestCount > 0): ?>
                <div class="alert alert-warning">
                    <strong>未処理のリクエストがあります！</strong> 
                    <p><?= $pendingRequestCount ?>件のチャンネル追加リクエストが承認待ちです。</p>
                    <a href="index.php?page=requests" class="btn btn-warning btn-sm">リクエスト管理へ</a>
                </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <h3>システム情報</h3>
                    <table class="table">
                        <tr>
                            <th>PHPバージョン</th>
                            <td><?= PHP_VERSION ?></td>
                        </tr>
                        <tr>
                            <th>設定ファイル</th>
                            <td><?= file_exists(__DIR__ . '/../config.php') ? '読み込み済み' : '未読み込み' ?></td>
                        </tr>
                        <tr>
                            <th>ログディレクトリ</th>
                            <td><?= is_writable(dirname(LOG_FILE)) ? '書き込み可能' : '書き込み不可' ?></td>
                        </tr>
                    </table>
                </div>
                
            <?php elseif ($page === 'twitch'): ?>
                <h2>Twitchチャンネル管理</h2>
                
                <form method="post" action="index.php?action=add_twitch" class="mb-4">
                    <div class="input-group">
                        <input type="text" name="channel_name" class="form-control" placeholder="Twitchユーザー名" required>
                        <button type="submit" class="btn btn-primary">追加</button>
                    </div>
                    <small class="text-muted">例: xQc, pokimane など</small>
                </form>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>チャンネル名</th>
                            <th>ステータス</th>
                            <th>最終配信ID</th>
                            <th>最終チェック</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($twitchChannels as $channel): ?>
                            <tr>
                                <td><?= htmlspecialchars($channel['channel_id']) ?></td>
                                <td><?= htmlspecialchars($channel['channel_name']) ?></td>
                                <td><?= $channel['is_live'] ? '<span class="badge bg-success">配信中</span>' : '<span class="badge bg-secondary">オフライン</span>' ?></td>
                                <td><?= htmlspecialchars($channel['last_stream_id'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($channel['last_checked']) ?></td>
                                <td>
                                    <a href="index.php?action=delete_twitch&id=<?= urlencode($channel['channel_id']) ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('本当に削除しますか？');">削除</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($twitchChannels)): ?>
                            <tr>
                                <td colspan="6" class="text-center">登録されているチャンネルはありません。</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
            <?php elseif ($page === 'youtube'): ?>
                <h2>YouTubeチャンネル管理</h2>
                
                <form method="post" action="index.php?action=add_youtube" class="mb-4">
                    <div class="input-group">
                        <input type="text" name="channel_id" class="form-control" placeholder="YouTubeチャンネルID" required>
                        <button type="submit" class="btn btn-danger">追加</button>
                    </div>
                    <small class="text-muted">例: UCsVz2qkd_oGXGC66fcH4SFA など</small>
                </form>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>チャンネル名</th>
                            <th>ステータス</th>
                            <th>最終動画ID</th>
                            <th>最終チェック</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($youtubeChannels as $channel): ?>
                            <tr>
                                <td><?= htmlspecialchars($channel['channel_id']) ?></td>
                                <td><?= htmlspecialchars($channel['channel_name']) ?></td>
                                <td><?= $channel['is_live'] ? '<span class="badge bg-success">配信中</span>' : '<span class="badge bg-secondary">オフライン</span>' ?></td>
                                <td><?= htmlspecialchars($channel['last_video_id'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($channel['last_checked']) ?></td>
                                <td>
                                    <a href="index.php?action=delete_youtube&id=<?= urlencode($channel['channel_id']) ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('本当に削除しますか？');">削除</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($youtubeChannels)): ?>
                            <tr>
                                <td colspan="6" class="text-center">登録されているチャンネルはありません。</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
            <?php elseif ($page === 'webhooks'): ?>
                <h2>Discord Webhook管理</h2>
                
                <form method="post" action="index.php?action=add_webhook" class="mb-4">
                    <div class="mb-3">
                        <label for="server_name" class="form-label">サーバー名</label>
                        <input type="text" name="server_name" id="server_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="webhook_url" class="form-label">Webhook URL</label>
                        <input type="text" name="webhook_url" id="webhook_url" class="form-control" required>
                        <small class="text-muted">Discord サーバー設定 → 連携サービス → ウェブフック から取得できます</small>
                    </div>
                    <div class="mb-3">
                        <label for="message_template" class="form-label">メッセージテンプレート（オプション）</label>
                        <textarea name="message_template" id="message_template" class="form-control" rows="3"></textarea>
                        <small class="text-muted">カスタム通知メッセージを設定できます。空白の場合はデフォルトのメッセージが使用されます。</small>
                    </div>
                    <button type="submit" class="btn btn-primary">追加</button>
                </form>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>サーバー名</th>
                            <th>Webhook URL</th>
                            <th>作成日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($webhooks as $webhook): ?>
                            <tr>
                                <td><?= htmlspecialchars($webhook['id']) ?></td>
                                <td><?= htmlspecialchars($webhook['server_name']) ?></td>
                                <td>
                                    <div class="text-truncate" style="max-width: 300px;">
                                        <?= htmlspecialchars($webhook['webhook_url']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($webhook['created_at']) ?></td>
                                <td>
                                    <a href="index.php?action=delete_webhook&id=<?= urlencode($webhook['id']) ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('本当に削除しますか？');">削除</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($webhooks)): ?>
                            <tr>
                                <td colspan="5" class="text-center">登録されているWebhookはありません。</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
            <?php elseif ($page === 'settings'): ?>
                <h2>設定</h2>
                
                <div class="alert alert-info">
                    この画面では設定ファイルの内容を表示しています。設定を変更するには、サーバー上の <code>config.php</code> ファイルを編集してください。
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>設定項目</th>
                            <th>値</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>データベースホスト</td>
                            <td><?= htmlspecialchars(DB_HOST) ?></td>
                        </tr>
                        <tr>
                            <td>データベース名</td>
                            <td><?= htmlspecialchars(DB_NAME) ?></td>
                        </tr>
                        <tr>
                            <td>データベースユーザー</td>
                            <td><?= htmlspecialchars(DB_USER) ?></td>
                        </tr>
                        <tr>
                            <td>Twitch Client ID</td>
                            <td><?= htmlspecialchars(substr(TWITCH_CLIENT_ID, 0, 5) . '...') ?></td>
                        </tr>
                        <tr>
                            <td>YouTube API Key</td>
                            <td><?= htmlspecialchars(substr(YOUTUBE_API_KEY, 0, 5) . '...') ?></td>
                        </tr>
                        <tr>
                            <td>チェック間隔</td>
                            <td><?= htmlspecialchars(CHECK_INTERVAL) ?> 秒</td>
                        </tr>
                        <tr>
                            <td>ログ有効</td>
                            <td><?= LOG_ENABLED ? 'はい' : 'いいえ' ?></td>
                        </tr>
                        <tr>
                            <td>ログファイル</td>
                            <td><?= htmlspecialchars(LOG_FILE) ?></td>
                        </tr>
                        <tr>
                            <td>デバッグモード</td>
                            <td><?= DEBUG_MODE ? 'はい' : 'いいえ' ?></td>
                        </tr>
                    </tbody>
                </table>
                
            <?php elseif ($page === 'requests'): ?>
                <h2>チャンネルリクエスト管理</h2>
                
                <?php if (empty($channelRequests)): ?>
                    <div class="alert alert-info">
                        チャンネル追加リクエストはありません。
                    </div>
                <?php else: ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title h5 mb-0">保留中のリクエスト</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>プラットフォーム</th>
                                        <th>チャンネルID</th>
                                        <th>チャンネル名</th>
                                        <th>リクエスト者</th>
                                        <th>リクエスト日時</th>
                                        <th>状態</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($channelRequests as $request): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($request['id']) ?></td>
                                            <td>
                                                <?php if ($request['platform'] === 'twitch'): ?>
                                                    <span class="badge bg-purple">Twitch</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">YouTube</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($request['channel_id']) ?></td>
                                            <td><?= htmlspecialchars($request['channel_name']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($request['requester_name']) ?>
                                                <small class="d-block text-muted"><?= htmlspecialchars($request['requester_email']) ?></small>
                                            </td>
                                            <td><?= date('Y/m/d H:i', strtotime($request['created_at'])) ?></td>
                                            <td>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">保留中</span>
                                                <?php elseif ($request['status'] === 'approved'): ?>
                                                    <span class="badge bg-success">承認済</span>
                                                <?php elseif ($request['status'] === 'rejected'): ?>
                                                    <span class="badge bg-danger">拒否済</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <a href="index.php?action=approve_request&id=<?= urlencode($request['id']) ?>" 
                                                       class="btn btn-sm btn-success" 
                                                       onclick="return confirm('このリクエストを承認しますか？');">承認</a>
                                                    <a href="index.php?action=reject_request&id=<?= urlencode($request['id']) ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('このリクエストを拒否しますか？');">拒否</a>
                                                <?php else: ?>
                                                    <span class="text-muted">処理済み</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title h5 mb-0">リクエストページへのリンク</h3>
                    </div>
                    <div class="card-body">
                        <p>以下のURLをユーザーに共有して、チャンネル追加リクエストを受け付けることができます：</p>
                        
                        <?php
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                        $host = $_SERVER['HTTP_HOST'];
                        $requestUrl = $protocol . $host . str_replace('/admin', '', dirname($_SERVER['REQUEST_URI'])) . '/request.php';
                        ?>
                        
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($requestUrl) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard(this)">コピー</button>
                        </div>
                    </div>
                </div>
                
                <script>
                    function copyToClipboard(button) {
                        const input = button.previousElementSibling;
                        input.select();
                        document.execCommand('copy');
                        button.innerText = 'コピー済み';
                        setTimeout(() => {
                            button.innerText = 'コピー';
                        }, 2000);
                    }
                </script>
                
            <?php endif; ?>
        </main>
        
        <footer class="mt-5 text-center text-muted">
            <p>Citrus 配信通知 Bot &copy; <?= date('Y') ?></p>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
