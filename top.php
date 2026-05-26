<?php
date_default_timezone_set('Asia/Tokyo');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$now_jst = date('Y-m-d H:i:s');

$weeks = ['日', '月', '火', '水', '木', '金', '土'];

$stmt = $pdo->prepare("SELECT p.*, a.status, DATEDIFF(p.practice_date, CURDATE()) as days_left FROM practices p JOIN practice_attendance a ON p.id = a.practice_id WHERE a.user_id = ? AND CONCAT(p.practice_date, ' ', p.end_time) > ? AND p.is_cancelled = 0 AND a.status IN ('参加', '途中', '途中参', 'ドタ参', 'ドタ途中参', 'お手伝い') ORDER BY p.practice_date ASC");
$stmt->execute([$user_id, $now_jst]);
$my_upcoming_practices = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM practices WHERE CONCAT(practice_date, ' ', end_time) > ? AND is_cancelled = 0 ORDER BY practice_date ASC LIMIT 1");
$stmt->execute([$now_jst]);
$next_practice = $stmt->fetch();

$p_full = []; $p_half = []; $p_dota = []; $p_dota_half = []; $p_help = [];
if ($next_practice) {
    $r_stmt = $pdo->prepare("SELECT user_id, role_type FROM practice_roles WHERE practice_id = ?");
    $r_stmt->execute([$next_practice['id']]);
    $role_map = [];
    foreach($r_stmt->fetchAll() as $row){
        $role_map[$row['user_id']][] = $row['role_type'];
    }

    $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(u.display_name, ''), u.name_kana) as show_name, a.status, u.generation, u.id as uid FROM practice_attendance a JOIN users u ON a.user_id = u.id WHERE a.practice_id = ? AND a.status != '欠席'");
    $stmt->execute([$next_practice['id']]);
    $all_p = $stmt->fetchAll();
    
    foreach ($all_p as $p) {
        $name_disp = htmlspecialchars($p['show_name']);
        if (isset($role_map[$p['uid']])) {
            $name_disp .= " <span style='font-size:0.85em; color:#666; font-weight:normal;'>(" . implode('・', $role_map[$p['uid']]) . ")</span>";
        }

        if ($p['generation'] == 0) { $p_help[] = $name_disp; }
        elseif ($p['status'] === '参加') { $p_full[] = $name_disp; }
        elseif ($p['status'] === '途中' || $p['status'] === '途中参') { $p_half[] = $name_disp; }
        elseif ($p['status'] === 'ドタ参') { $p_dota[] = $name_disp; }
        elseif ($p['status'] === 'ドタ途中参') { $p_dota_half[] = $name_disp; }
        elseif ($p['status'] === 'お手伝い') { $p_help[] = $name_disp; }
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
                        <?php foreach ($my_upcoming_practices as $p): 
                            $w_idx = date('w', strtotime($p['practice_date']));
                        ?>
                            <li style="padding: 15px; border-bottom: 1px solid #eee; margin-bottom: 10px; background: #fdfdfd; border-radius: 8px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="font-size: 1.1em;"><?php echo date('n/j', strtotime($p['practice_date'])) . '(' . $weeks[$w_idx] . ')'; ?> (<?php echo htmlspecialchars($p['location']); ?><?php if(!empty($p['court_number'])) echo ' ' . htmlspecialchars($p['court_number']); ?>)</strong><br>
                                        <span style="color: #666; font-size: 0.85em;"><?php echo date('H:i', strtotime($p['start_time'])); ?> - <?php echo date('H:i', strtotime($p['end_time'])); ?></span><br>
                                        
                                        <?php if (!empty($p['gender_target'])): ?>
                                            <span style="display:inline-block; margin-top:2px; font-size:0.8em; font-weight:bold; color: #d35400;">【<?php echo htmlspecialchars($p['gender_target']); ?>】</span><br>
                                        <?php endif; ?>

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

            <?php if ($next_practice): 
                $nw_idx = date('w', strtotime($next_practice['practice_date']));
            ?>
            <div class="main-card">
                <h3 style="margin-top:0; margin-bottom:15px;">次回の練習</h3>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                    <div style="font-size: 1.1em; font-weight: bold; color: #333;">
                        <?php echo date('n/j', strtotime($next_practice['practice_date'])) . '(' . $weeks[$nw_idx] . ')'; ?> 
                        <?php echo date('H:i', strtotime($next_practice['start_time'])); ?> - <?php echo date('H:i', strtotime($next_practice['end_time'])); ?>
                        <?php if (!empty($next_practice['gender_target'])): ?>
                            <span style="color: #d35400; margin-left:5px; font-size:0.9em;">【<?php echo htmlspecialchars($next_practice['gender_target']); ?>】</span>
                        <?php endif; ?>
                    </div>
                    <div style="border: 2px solid #4a86e8; color: #4a86e8; padding: 4px 12px; border-radius: 4px; font-weight: bold;">
                        <?php echo htmlspecialchars($next_practice['location']); ?><?php if(!empty($next_practice['court_number'])) echo htmlspecialchars($next_practice['court_number']); ?>
                    </div>
                </div>

                <div style="background: #f0f2f5; padding: 15px; border-radius: 8px;">
                    <p style="margin:0 0 10px 0; font-weight:bold;">合計: <?php echo count($p_full) + count($p_half) + count($p_dota) + count($p_dota_half) + count($p_help); ?>名</p>
                    
                    <strong style="color:#4a86e8;">参加 (<?php echo count($p_full); ?>名)</strong>
                    <ul style="margin: 5px 0 10px 20px; padding: 0;">
                        <?php foreach ($p_full as $n) echo "<li>".$n."</li>"; ?>
                    </ul>
                    
                    <strong style="color:#17a2b8;">途中参加 (<?php echo count($p_half); ?>名)</strong>
                    <ul style="margin: 5px 0 10px 20px; padding: 0;">
                        <?php foreach ($p_half as $n) echo "<li>".$n."</li>"; ?>
                    </ul>

                    <strong style="color:#f39c12;">ドタ参 (<?php echo count($p_dota); ?>名)</strong>
                    <ul style="margin: 5px 0 10px 20px; padding: 0;">
                        <?php foreach ($p_dota as $n) echo "<li>".$n."</li>"; ?>
                    </ul>

                    <strong style="color:#cf2f3a;">ドタ途中参 (<?php echo count($p_dota_half); ?>名)</strong>
                    <ul style="margin: 5px 0 10px 20px; padding: 0;">
                        <?php foreach ($p_dota_half as $n) echo "<li>".$n."</li>"; ?>
                    </ul>

                    <?php if(count($p_help) > 0): ?>
                    <strong style="color:#28a745;">お手伝い (<?php echo count($p_help); ?>名)</strong>
                    <ul style="margin: 5px 0 10px 20px; padding: 0;">
                        <?php foreach ($p_help as $n) echo "<li>".$n."</li>"; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>