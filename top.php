<?php
// セッションを再開
session_start();

// user_idを持っていなければ、ログイン画面に強制送還
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ログインしている人の名前を変数に
$user_name = $_SESSION['name_kana'];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>TOP</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="top-container">
        <header class="top-header">
            <h2><?php echo htmlspecialchars($user_name); ?> さん</h2>
            <a href="logout.php" class="btn-logout">ログアウト</a>
        </header>

        <div class="menu-grid">
            <a href="events.php" class="menu-card">
                <h3>練習参加登録</h3>
                <p>フレ団練の出欠を入力します</p>
            </a>

            <a href="payment.php" class="menu-card">
                <h3>借金・チャージ状況</h3>
                <p>自分の参加履歴と、未払いの金額を確認します</p>
            </a>

            <a href="admin.php" class="menu-card admin-card">
                <h3>管理画面</h3>
                <p>新しい練習の作成や、全体の集計を行います</p>
            </a>
        </div>
    </div>
</body>
</html>