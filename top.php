<?php
date_default_timezone_set('Asia/Tokyo');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$now_jst = date('Y-m-d H:i:s');

// 自分の参加予定（終了時刻前のみ取得）
$stmt = $pdo->prepare("SELECT p.*, a.status, DATEDIFF(p.practice_date, CURDATE()) as days_left FROM practices p JOIN practice_attendance a ON p.id = a.practice_id WHERE a.user_id = ? AND CONCAT(p.practice_date, ' ', p.end_time) > ? AND p.is_cancelled = 0 AND a.status IN ('参加', '途中', 'ドタ参', 'ドタ途中参') ORDER BY p.practice_date ASC");
$stmt->execute([$user_id, $now_jst]);
$my_upcoming_practices = $stmt->fetchAll();

// 次回の練習と参加者リスト用（終了時刻前のみ取得）
$stmt = $pdo->prepare("SELECT * FROM practices WHERE CONCAT(practice_date, ' ', end_time) > ? AND is_cancelled = 0 ORDER BY practice_date ASC LIMIT 1");
$stmt->execute([$now_jst]);
$next_practice = $stmt->fetch();

$p_full = []; $p_half = []; $p_dota = []; $p_dota_half = [];
if ($next_practice) {
    $stmt = $pdo->prepare("SELECT COALESCE(u.display_name, u.name_kana) as show_name, a.status FROM practice_attendance a JOIN users u ON a.user_id = u.id WHERE a.practice_id = ? AND a.status IN ('参加', '途中', 'ドタ参', 'ドタ途中参')");
    $stmt->execute([$next_practice['id']]);
    $all_p = $stmt->fetchAll();
    foreach ($all_p as $p) {
        if ($p['status'] === '参加') $p_full[] = $p['show_name'];
        elseif ($p['status'] === '途中') $p_half[] = $p['show_name'];
        elseif ($p['status'] === 'ドタ参') $p_dota[] = $p['show_name'];
        elseif ($p['status'] === 'ドタ途中参') $p_dota_half[] = $p['show_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOP - KiddyKiddsFreshers</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="global-navbar">
            <div class="logo-area">
                <span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span>
                <img src="logo.png" alt="logo" onerror="this.style.display='none'">    
                <strong>TOP</strong>
            </div>
        </header>
        <main class="content-body">
            
            <div class="main-card">
                <h3 style="color: #4a86e8; margin-top:0;">あなたの参加予定</h3>
                <?php if (count($my_upcoming_practices) > 0): ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($my_upcoming_practices as $p): ?>
                            <li style="padding: 15px; border-bottom: 1px solid #eee; margin-bottom: 10px; background: #fdfdfd; border-radius: 8px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="font-size: 1.1em;"><?php echo date('n/j', strtotime($p['practice_date'])); ?> (<?php echo htmlspecialchars($p['location']); ?>)</strong><br>
                                        <span style="color: #666; font-size: 0.85em;"><?php echo date('H:i', strtotime($p['start_time'])); ?> - <?php echo date('H:i', strtotime($p['end_time'])); ?></span><br>
                                        <span style="display:inline-block; margin-top:5px; padding:2px 8px; background:#e2e8f0; border-radius:12px; font-size:0.8em;"><?php echo $p['status']; ?></span>
                                    </div>
                                    <div style="font-weight: bold; font-size: 1.2em; <?php echo ($p['days_left'] <= 7) ? 'color: #dc3545;' : 'color: #28a745;'; ?>">
                                        <?php if($p['days_left'] == 0) echo "本日！"; else echo "あと " . $p['days_left'] . " 日"; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color:#666;">現在、参加予定の練習はありません。</p>
                <?php endif; ?>
                <a href="attendance_list.php" class="btn-submit" style="display:block; text-align:center; margin-top:20px; text-decoration:none;">出欠を入力・変更する</a>
            </div>

            <?php if ($next_practice): ?>
            <div class="main-card">
                <h3 style="margin-top:0;">次回の参加者 (<?php echo date('n/j', strtotime($next_practice['practice_date'])); ?>)</h3>
                <div style="background: #f0f2f5; padding: 15px; border-radius: 8px;">
                    <p style="margin:0 0 10px 0; font-weight:bold;">合計: <?php echo count($p_full) + count($p_half) + count($p_dota) + count($p_dota_half); ?>名</p>
                    
                    <strong style="color:#4a86e8;">参加 (<?php echo count($p_full); ?>名)</strong>
                    <ul style="margin: 5px 0 10px 20px; padding: 0;">
                        <?php foreach ($p_full as $n) echo "<li>".htmlspecialchars($n)."</li>"; ?>
                    </ul>
                    
                    <strong style="color:#17a2b8;">途中参加 (<?php echo count($p_half); ?>名)</strong>
                    <ul style="margin: 5px 0 10px 20px; padding: 0;">
                        <?php foreach ($p_half as $n) echo "<li>".htmlspecialchars($n)."</li>"; ?>
                    </ul>

                    <strong style="color:#f39c12;">ドタ参 (<?php echo count($p_dota); ?>名)</strong>
                    <ul style="margin: 5px 0 10px 20px; padding: 0;">
                        <?php foreach ($p_dota as $n) echo "<li>".htmlspecialchars($n)."</li>"; ?>
                    </ul>

                    <strong style="color:#cf2f3a;">ドタ途中参 (<?php echo count($p_dota_half); ?>名)</strong>
                    <ul style="margin: 5px 0 10px 20px; padding: 0;">
                        <?php foreach ($p_dota_half as $n) echo "<li>".htmlspecialchars($n)."</li>"; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>