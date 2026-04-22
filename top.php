<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_name = $_SESSION['name_kana'];

// 直近の（今日以降の）練習を取得
$stmt = $pdo->prepare("SELECT * FROM practices WHERE practice_date >= CURDATE() AND is_cancelled = 0 ORDER BY practice_date ASC LIMIT 1");
$stmt->execute();
$next_practice = $stmt->fetch();

// 直近の練習の参加者を取得して、表示用に分ける
$participants_full = [];
$participants_half = [];
if ($next_practice) {
    $stmt = $pdo->prepare("
        SELECT u.name_kana, a.status 
        FROM practice_attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.practice_id = ? AND a.status IN ('フル', '途中')
    ");
    $stmt->execute([$next_practice['id']]);
    $all_participants = $stmt->fetchAll();
    
    foreach ($all_participants as $p) {
        if ($p['status'] === 'フル') {
            $participants_full[] = $p['name_kana'];
        } else {
            $participants_half[] = $p['name_kana'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ホーム - 練習管理</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* 参加者リスト用の追加デザイン */
        .participant-list { margin-top: 20px; text-align: left; background: #f8f9fa; padding: 15px; border-radius: 8px; }
        .participant-list ul { margin: 5px 0 15px 20px; padding: 0; color: #444; }
        .participant-list li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="top-container">
        <?php include 'sidebar.php'; // サイドメニューを作った場合はここで読み込み ?>
        
        <header class="top-header">
            <h2>ホーム</h2>
            <p><?php echo htmlspecialchars($user_name); ?> さん</p>
        </header>

        <?php if ($next_practice): ?>
        <div class="main-card">
            <h3 style="color: #007bff;"> 次回の練習：<?php echo date('n月j日', strtotime($next_practice['practice_date'])); ?></h3>
            <p>
                <?php echo date('H:i', strtotime($next_practice['start_time'])); ?> - <?php echo date('H:i', strtotime($next_practice['end_time'])); ?><br>
                <?php echo htmlspecialchars($next_practice['location']); ?>
            </p>
            
            <a href="attendance_list.php" class="btn-submit" style="display:inline-block; margin: 15px 0; text-decoration: none;">出欠を入力・変更する</a>

            <div class="participant-list">
                <h4> 参加予定者 (合計: <?php echo count($participants_full) + count($participants_half); ?>名)</h4>
                
                <strong>フル参加 (<?php echo count($participants_full); ?>名)</strong>
                <ul>
                    <?php foreach ($participants_full as $name) echo "<li>" . htmlspecialchars($name) . "</li>"; ?>
                    <?php if (empty($participants_full)) echo "<li>なし</li>"; ?>
                </ul>

                <strong>途中参加 (<?php echo count($participants_half); ?>名)</strong>
                <ul>
                    <?php foreach ($participants_half as $name) echo "<li>" . htmlspecialchars($name) . "</li>"; ?>
                    <?php if (empty($participants_half)) echo "<li>なし</li>"; ?>
                </ul>
            </div>
        </div>
        <?php else: ?>
        <div class="main-card">
            <h3>今後の練習予定はまだありません。</h3>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>