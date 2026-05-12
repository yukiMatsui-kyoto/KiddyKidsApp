if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$now_jst = date('Y-m-d H:i:s');


<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>概要・問い合わせ - FreshTSystem</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="global-navbar">
            <div class="logo-area">
                <span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span>
                <img src="logo.png" alt="logo" onerror="this.style.display='none'">    
                <strong>ホーム</strong>
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
