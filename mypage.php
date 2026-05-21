<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name_kana'];

// ★曜日の配列を追加
$weeks = ['日', '月', '火', '水', '木', '金', '土'];

$stmt = $pdo->prepare("SELECT display_name FROM users WHERE id = ?"); $stmt->execute([$user_id]); $current_display_name = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT p.id as practice_id, p.practice_date, p.location, p.start_time, p.end_time, a.status, a.is_penalty FROM practice_attendance a JOIN practices p ON a.practice_id = p.id WHERE a.user_id = :u_id AND p.practice_date <= CURDATE() AND p.is_cancelled = 0 ORDER BY p.practice_date DESC");
$stmt->execute([':u_id' => $user_id]); $my_history = $stmt->fetchAll();

function getPracticeStats($practice, $attendees) {
    $start = strtotime($practice['start_time']); $end = strtotime($practice['end_time']);
    $practice_hours = ($end - $start) / 3600; if ($practice_hours <= 0) $practice_hours = 2;

    $total_weight = 0; $total_hours = 0; $user_stats = [];
    foreach ($attendees as $a) {
        $st = $a['status']; $pen = $a['is_penalty'] ?? 0; $w = 0; $h = 0;
        
        if ($st === '参加' || $st === 'ドタ参' || $st === 'ドタ途中参' || $pen == 1) { $w = 1.0; $h = $practice_hours; } 
        elseif ($st === '途中' || $st === '途中参') {
            if (abs($practice_hours - 2.0) < 0.1) { $w = 0.5; $h = 1.0; } 
            elseif (abs($practice_hours - 3.0) < 0.1) { $w = 2/3; $h = 2.0; } 
            else { $w = 0.5; $h = $practice_hours * 0.5; }
        } elseif ($st === 'お手伝い') { $w = 0; $h = 0; }
        
        if (isset($a['override_weight']) && $a['override_weight'] !== null) $w = (float)$a['override_weight'];
        if (isset($a['override_hours']) && $a['override_hours'] !== null) $h = (float)$a['override_hours'];

        $user_stats[$a['user_id']] = ['weight' => $w, 'hours' => $h];
        $total_weight += $w; $total_hours += $h;
    }
    return ['total_weight' => $total_weight, 'total_hours' => $total_hours, 'user_stats' => $user_stats];
}

$stmt = $pdo->prepare("SELECT * FROM practices WHERE is_published = 1 AND is_cancelled = 0");
$stmt->execute();
$published_practices = $stmt->fetchAll();

$total_court_fee = 0; $my_total_hours = 0; $all_users_total_hours = 0;
$has_published = count($published_practices) > 0;

foreach ($published_practices as $p) {
    $stmt = $pdo->prepare("SELECT user_id, status, is_penalty, override_weight, override_hours FROM practice_attendance WHERE practice_id = ?"); $stmt->execute([$p['id']]); $atts = $stmt->fetchAll();
    $stats = getPracticeStats($p, $atts);
    
    if ($stats['total_weight'] > 0 && isset($stats['user_stats'][$user_id])) {
        $total_court_fee += ($p['facility_fee'] / $stats['total_weight']) * $stats['user_stats'][$user_id]['weight'];
    }
    $all_users_total_hours += $stats['total_hours'];
    if (isset($stats['user_stats'][$user_id])) { $my_total_hours += $stats['user_stats'][$user_id]['hours']; }
}

$total_ball_fee_pool = $pdo->query("SELECT SUM(amount) FROM global_expenses WHERE expense_type = 'ball'")->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT e.amount, (SELECT COUNT(*) FROM global_expense_payers WHERE expense_id = e.id) as payer_count FROM global_expenses e JOIN global_expense_payers p ON e.id = p.expense_id WHERE e.expense_type = 'misc' AND p.user_id = ?");
$stmt->execute([$user_id]);
$my_misc_list = $stmt->fetchAll();
$total_misc_fee = 0;
foreach($my_misc_list as $m) {
    if ($m['payer_count'] > 0) $total_misc_fee += $m['amount'] / $m['payer_count'];
}

$my_ball_fee = ($all_users_total_hours > 0) ? ($total_ball_fee_pool * ($my_total_hours / $all_users_total_hours)) : 0;
$total_confirmed_fee = round($total_court_fee) + round($my_ball_fee) + round($total_misc_fee);
?>
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>マイページ</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="global-navbar"><div class="logo-area"><span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span><strong>マイページ</strong></div></header>
        <main class="content-body">
            <div class="main-card">
                <h3 style="margin-top:0;"><?php echo htmlspecialchars($user_name); ?> さんの表示名</h3>
                <form action="update_profile.php" method="POST" style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" name="display_name" value="<?php echo htmlspecialchars($current_display_name ?? ''); ?>" placeholder="表示名（ニックネーム）" style="padding: 8px; flex: 1; border: 1px solid #ccc; border-radius: 4px;">
                    <button type="submit" class="btn-submit">保存</button>
                </form>
            </div>
            <div class="main-card" style="text-align: center; background: #fff4f4; border: 2px solid #ffcccc;">
                <h3 style="margin: 0; color: #666;">現在確定の支払い合計</h3>
                <div style="font-size: 3em; font-weight: bold; color: #d35400; margin: 10px 0;">
                    <?php if ($has_published): ?>¥<?php echo number_format($total_confirmed_fee); ?><?php else: ?><span style="font-size: 0.6em; color: #999;">未公開</span><?php endif; ?>
                </div>
                <?php if ($has_published): ?>
                    <div style="color: #555; font-size: 0.85em; margin-bottom: 5px; line-height:1.5;">
                        【内訳】<br>コート代: ¥<?php echo number_format(round($total_court_fee)); ?> / ボール代: ¥<?php echo number_format(round($my_ball_fee)); ?><br>
                        その他雑費: ¥<?php echo number_format(round($total_misc_fee)); ?> / 総練習時間: <?php echo $my_total_hours; ?>h
                    </div>
                <?php endif; ?>
                <p style="margin: 0; color: #999; font-size: 0.9em;">※確定分のみ表示されています(フレ団終了後)                
                </p>
            </div>
            <div class="main-card">
                <h3 style="margin-top:0;">練習参加履歴</h3>
                <table class="practice-table" style="display:block; overflow-x:auto; white-space:nowrap;">
                    <thead><tr style="background:#f8f9fa;"><th>日付</th><th>コート</th><th>時間</th><th>状況</th></tr></thead>
                    <tbody>
                        <?php foreach ($my_history as $record): 
                            $w_idx = date('w', strtotime($record['practice_date']));
                        ?>
                        <tr>
                            <td><?php echo date('n/j', strtotime($record['practice_date'])) . '(' . $weeks[$w_idx] . ')'; ?></td>
                            <td><?php echo htmlspecialchars($record['location']); ?></td>
                            <td style="font-size:0.85em; color:#666;"><?php echo date('H:i', strtotime($record['start_time'])); ?>-<?php echo date('H:i', strtotime($record['end_time'])); ?></td>
                            <td><?php if ($record['is_penalty']) echo '<span style="color:#dc3545; font-weight:bold;">7日以内ｷｬﾝｾﾙ料</span>'; else echo htmlspecialchars($record['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>