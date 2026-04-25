<?php
// エラーの原因を画面に表示させる設定（デバッグ用）
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// ログインしていない場合はログイン画面へ
if (!isset($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit; 
}
$user_name = $_SESSION['name_kana'];

try {
    // 直近の（今日以降の）練習を取得
    $stmt = $pdo->prepare("SELECT * FROM practices WHERE practice_date >= CURDATE() AND is_cancelled = 0 ORDER BY practice_date ASC LIMIT 1");
    $stmt->execute();
    $next_practice = $stmt->fetch();

    $participants_full = []; 
    $participants_half = [];
    if ($next_practice) {
        $stmt = $pdo->prepare("SELECT COALESCE(u.display_name, u.name_kana) as show_name, a.status FROM practice_attendance a JOIN users u ON a.user_id = u.id WHERE a.practice_id = ? AND a.status IN ('参加', '途中')");
        $stmt->execute([$next_practice['id']]);
        $all_participants = $stmt->fetchAll();
        
        foreach ($all_participants as $p) {
            if ($p['status'] === '参加') {
                $participants_full[] = $p['show_name'];
            } else {
                $participants_half[] = $p['show_name'];
            }
        }
    }
} catch (PDOException $e) {
    // データベース関連の致命的なエラーが起きた場合はここで画面に出力する
    die("<div style='padding:20px; background:#f8d7da; color:#721c24; border-radius:5px;'><strong>データベースエラーが発生しました：</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>");
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"><title>ホーム - FreshTSystem</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-wrapper">
        <header class="top-bar">
            <strong>🏠 ホーム</strong>
        </header>

        <main class="content-body">
            <?php if ($next_practice): ?>
            <div class="main-card">
                <h3 style="color: #4a86e8; margin-top:0;"> 次回の練習：<?php echo date('n月j日', strtotime($next_practice['practice_date'])); ?></h3>
                <p>
                    <?php echo date('H:i', strtotime($next_practice['start_time'])); ?> - <?php echo date('H:i', strtotime($next_practice['end_time'])); ?><br>
                    <?php echo htmlspecialchars($next_practice['location']); ?>
                </p>
                <a href="attendance_list.php" class="btn-submit" style="display:inline-block; margin: 15px 0; text-decoration: none;">出欠を入力・変更する</a>

                <div style="margin-top: 20px; background: #f0f2f5; padding: 15px; border-radius: 8px;">
                    <h4 style="margin-top:0;"> 参加予定者 (合計: <?php echo count($participants_full) + count($participants_half); ?>名)</h4>
                    
                    <strong style="color: #4a86e8;">参加 (<?php echo count($participants_full); ?>名)</strong>
                    <ul style="margin: 5px 0 15px 20px; padding: 0;">
                        <?php foreach ($participants_full as $name) echo "<li style='margin-bottom:3px;'>" . htmlspecialchars($name) . "</li>"; ?>
                        <?php if (empty($participants_full)) echo "<li>なし</li>"; ?>
                    </ul>
                    
                    <strong style="color: #17a2b8;">途中参(<?php echo count($participants_half); ?>名)</strong>
                    <ul style="margin: 5px 0 15px 20px; padding: 0;">
                        <?php foreach ($participants_half as $name) echo "<li style='margin-bottom:3px;'>" . htmlspecialchars($name) . "</li>"; ?>
                        <?php if (empty($participants_half)) echo "<li>なし</li>"; ?>
                    </ul>
                </div>
            </div>
            <?php else: ?>
            <div class="main-card">
                <h3 style="margin:0; color:#666;">今後の練習予定はまだありません。</h3>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>