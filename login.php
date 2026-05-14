<?php
session_start();
require_once 'db.php';

// 自動ログイン処理
if (!isset($_SESSION['user_id']) && isset($_COOKIE['kiddy_auto_login'])) {
    $user_id = $_COOKIE['kiddy_auto_login'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['generation'] = $user['generation'];
        $_SESSION['name_kana'] = $user['name_kana'];
        header('Location: top.php');
        exit;
    }
}

// すでに通常のログイン状態の場合
if (isset($_SESSION['user_id'])) {
    header('Location: top.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KiddyKiddsFreshers - ログイン</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <div class="login_logo-area">
                <img src="login_logo.png" alt="logo" onerror="this.style.display='none'">
            </div>

            <h1>KiddyKiddsFreshers<br>ログイン</h1>
            <p>代と、フルネーム（ひらがな）を入力してﾈ</p>
            <p style="font-size: 0.85em; color: #e74c3c; margin-top: 0; margin-bottom: 25px; font-weight: bold;">
                ※お手伝いは代に「0」を入力してください
            </p>
            
            <form action="login_action.php" method="POST">
                <div class="input-group">
                    <label>代</label>
                    <input type="number" name="generation" required placeholder="例：50">
                </div>
                
                <div class="input-group">
                    <label>名前（ひらがな）</label>
                    <input type="text" name="name_kana" placeholder="例：なかがわしんじ" required>
                </div>
                
                <button type="submit">ログイン</button>
            </form>
        </div>
    </div>
</body>
</html>