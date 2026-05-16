<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['is_admin'])) { header('Location: admin_login.php'); exit; }

// データベース自動拡張（立替用の枠を追加）
try {
    $pdo->exec("ALTER TABLE practices ADD COLUMN booked_by INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE global_expenses ADD COLUMN paid_by INT DEFAULT NULL");
} catch (PDOException $e) {}

if (isset($_POST['delete_user_id'])) {
    $del_id = $_POST['delete_user_id'];
    $pdo->prepare("DELETE FROM practice_attendance WHERE user_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$del_id]);
    header("Location: admin_accounting.php"); exit;
}
if (isset($_POST['publish_all'])) {
    $pdo->prepare("UPDATE practices SET is_published = 1 WHERE practice_date <= CURDATE()")->execute();
    header("Location: admin_accounting.php?published=1"); exit;
}

$users = $pdo->query("SELECT id, name_kana, generation FROM users ORDER BY generation DESC, name_kana ASC")->fetchAll();
$practices = $pdo->query("SELECT * FROM practices WHERE practice_date <= CURDATE() AND is_cancelled = 0")->fetchAll();

function getPracticeStats($practice, $attendees) {
    $start = strtotime($practice['start_time']); $end = strtotime($practice['end_time']);
    $practice_hours = ($end - $start) / 3600; if ($practice_hours <= 0) $practice_hours = 2;

    $total_weight = 0; $total_hours = 0; $user_stats = [];
    foreach ($attendees as $a) {
        $st = $a['status']; $pen = $a['is_penalty'] ?? 0; $w = 0; $h = 0;
        
        if ($a['generation'] == 0) {
            $w = 0; $h = 0;
        } else {
            if ($st === '参加' || $st === 'ドタ参' || $st === 'ドタ途中参' || $pen == 1) { $w = 1.0; $h = $practice_hours; } 
            elseif ($st === '途中' || $st === '途中参') {
                if (abs($practice_hours - 2.0) < 0.1) { $w = 0.5; $h = 1.0; } 
                elseif (abs($practice_hours - 3.0) < 0.1) { $w = 2/3; $h = 2.0; } 
                else { $w = 0.5; $h = $practice_hours * 0.5; }
            } elseif ($st === 'お手伝い') { $w = 0; $h = 0; }
        }
        
        if (isset($a['override_weight']) && $a['override_weight'] !== null) $w = (float)$a['override_weight'];
        if (isset($a['override_hours']) && $a['override_hours'] !== null) $h = (float)$a['override_hours'];

        $user_stats[$a['user_id']] = ['weight' => $w, 'hours' => $h];
        $total_weight += $w; $total_hours += $h;
    }
    return ['total_weight' => $total_weight, 'total_hours' => $total_hours, 'user_stats' => $user_stats];
}

$user_court_fee = []; $user_misc_fee = []; $user_hours = []; $user_advance = [];
foreach ($users as $u) { $user_court_fee[$u['id']] = 0; $user_misc_fee[$u['id']] = 0; $user_hours[$u['id']] = 0; $user_advance[$u['id']] = 0; }
$all_users_total_hours = 0;

foreach ($practices as $p) {
    // コート代の立替
    if (!empty($p['booked_by']) && isset($user_advance[$p['booked_by']])) {
        $user_advance[$p['booked_by']] += $p['facility_fee'];
    }

    $atts = $pdo->prepare("SELECT u.generation, a.user_id, a.status, a.is_penalty, a.override_weight, a.override_hours FROM practice_attendance a JOIN users u ON a.user_id = u.id WHERE a.practice_id = ?"); 
    $atts->execute([$p['id']]); $attendees = $atts->fetchAll();
    $stats = getPracticeStats($p, $attendees);
    
    $c_unit = ($stats['total_weight'] > 0) ? ($p['facility_fee'] / $stats['total_weight']) : 0;
    foreach ($stats['user_stats'] as $uid => $data) {
        if (isset($user_court_fee[$uid])) { $user_court_fee[$uid] += $c_unit * $data['weight']; $user_hours[$uid] += $data['hours']; }
    }
    $all_users_total_hours += $stats['total_hours'];
}

$total_ball_fee = $pdo->query("SELECT SUM(amount) FROM global_expenses WHERE expense_type = 'ball'")->fetchColumn() ?: 0;
$misc_expenses = $pdo->query("SELECT * FROM global_expenses WHERE expense_type = 'misc'")->fetchAll();

foreach ($misc_expenses as $exp) {
    // ★雑費の立替をカウント
    if (!empty($exp['paid_by']) && isset($user_advance[$exp['paid_by']])) {
        $user_advance[$exp['paid_by']] += $exp['amount'];
    }

    //フレ以外も割り勘の対象にする（u.generation != 0 を削除）
    $stmt = $pdo->prepare("SELECT p.user_id FROM global_expense_payers p JOIN users u ON p.user_id = u.id WHERE p.expense_id = ?");
    $stmt->execute([$exp['id']]);
    $valid_payer_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($valid_payer_ids) > 0) {
        $m_unit = $exp['amount'] / count($valid_payer_ids);
        foreach ($valid_payer_ids as $uid) { if (isset($user_misc_fee[$uid])) $user_misc_fee[$uid] += $m_unit; }
    }
}

// ボール代の立替
$ball_expenses = $pdo->query("SELECT * FROM global_expenses WHERE expense_type = 'ball'")->fetchAll();
foreach ($ball_expenses as $exp) {
    if (!empty($exp['paid_by']) && isset($user_advance[$exp['paid_by']])) {
        $user_advance[$exp['paid_by']] += $exp['amount'];
    }
}

$user_totals = []; $user_ball_fee = [];
foreach ($users as $u) {
    $uid = $u['id'];
    $b_fee = ($all_users_total_hours > 0) ? ($total_ball_fee * ($user_hours[$uid] / $all_users_total_hours)) : 0;
    $user_ball_fee[$uid] = $b_fee;
    // 合計 ＝ 負担分 － 立替分
    $user_totals[$uid] = round($user_court_fee[$uid]) + round($b_fee) + round($user_misc_fee[$uid]) - round($user_advance[$uid]);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><title>全体会計</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="global-navbar"><div class="logo-area"><span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span><strong>全体会計名簿</strong></div></header>
        <main class="content-body">
            <div class="main-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="margin:0;">今日までの全練習の合計</h3>
                    <div style="display: flex; gap: 10px;">
                        <a href="export_roster.php" class="btn-submit" style="background:#28a745; text-decoration:none;">CSV出力(Excel出力)</a>
                        <form method="POST"><button type="submit" name="publish_all" class="btn-publish" onclick="return confirm('一括公開しますか？')">全員に一括公開</button></form>
                    </div>
                </div>
                <p style="color: #666; font-size: 0.9em;">※総練習時間: <?php echo $all_users_total_hours; ?> 時間 / 総ボール代: ¥<?php echo number_format($total_ball_fee); ?></p>

                <table class="practice-table" style="display:block; overflow-x:auto; white-space:nowrap;">
                    <thead><tr style="background:#4a86e8; color:white;"><th>代</th><th>名前</th><th>最終金額</th><th>ｺｰﾄ代</th><th>ﾎﾞｰﾙ代</th><th>雑費</th><th style="color:#ffcccc;">立替済(ﾏｲﾅｽ)</th><th>時間</th><th>操作</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            // フレ以外で合計が0円なら非表示（マイナスやプラスがあれば表示）
                            if ($u['generation'] == 0 && $user_totals[$u['id']] == 0) continue; 
                        ?>
                        <tr style="<?php echo ($user_totals[$u['id']] < 0) ? 'background:#fff3f3;' : ''; ?>">
                            <td><?php echo $u['generation']; ?></td>
                            <td><strong><?php echo htmlspecialchars($u['name_kana']); ?></strong></td>
                            <td style="font-weight:bold; color:<?php echo ($user_totals[$u['id']] < 0) ? '#0056b3' : (($user_totals[$u['id']] == 0) ? '#999' : '#d35400'); ?>;">
                                <?php echo ($user_totals[$u['id']] <= 0 ? '' : '¥') . number_format($user_totals[$u['id']]); ?>
                            </td>
                            <td style="font-size:0.85em; color:#666;">¥<?php echo number_format(round($user_court_fee[$u['id']])); ?></td>
                            <td style="font-size:0.85em; color:#666;">¥<?php echo number_format(round($user_ball_fee[$u['id']])); ?></td>
                            <td style="font-size:0.85em; color:#666;">¥<?php echo number_format(round($user_misc_fee[$u['id']])); ?></td>
                            <td style="font-size:0.85em; color:#dc3545; font-weight:bold;"><?php echo ($user_advance[$u['id']] > 0) ? '-¥' . number_format($user_advance[$u['id']]) : '0'; ?></td>
                            <td style="font-size:0.85em; color:#666;"><?php echo $user_hours[$u['id']]; ?>h</td>
                            <td>
                                <form method="POST" onsubmit="return confirm('削除しますか？');"><input type="hidden" name="delete_user_id" value="<?php echo $u['id']; ?>"><button type="submit" style="background:#dc3545; color:white; border:none; padding:4px 8px; border-radius:4px;">削除</button></form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>