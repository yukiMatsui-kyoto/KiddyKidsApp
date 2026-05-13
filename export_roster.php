<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['is_admin'])) { exit('Access Denied'); }

$users = $pdo->query("SELECT id, name_kana, generation FROM users ORDER BY generation DESC, name_kana ASC")->fetchAll();
$practices = $pdo->query("SELECT * FROM practices WHERE practice_date <= CURDATE() AND is_cancelled = 0")->fetchAll();

function getPracticeStats($practice, $attendees) {
    $start = strtotime($practice['start_time']); $end = strtotime($practice['end_time']);
    $practice_hours = ($end - $start) / 3600; if ($practice_hours <= 0) $practice_hours = 2;

    $total_weight = 0; $total_hours = 0; $user_stats = [];
    foreach ($attendees as $a) {
        $st = $a['status']; $pen = $a['is_penalty'] ?? 0; $w = 0; $h = 0;
        
        // 0代は強制的に負担なし
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
        
        // 手動オーバーライドがあれば上書き
        if (isset($a['override_weight']) && $a['override_weight'] !== null) $w = (float)$a['override_weight'];
        if (isset($a['override_hours']) && $a['override_hours'] !== null) $h = (float)$a['override_hours'];

        $user_stats[$a['user_id']] = ['weight' => $w, 'hours' => $h];
        $total_weight += $w; $total_hours += $h;
    }
    return ['total_weight' => $total_weight, 'total_hours' => $total_hours, 'user_stats' => $user_stats];
}

$user_court_fee = []; $user_misc_fee = []; $user_hours = [];
foreach ($users as $u) { $user_court_fee[$u['id']] = 0; $user_misc_fee[$u['id']] = 0; $user_hours[$u['id']] = 0; }
$all_users_total_hours = 0;

foreach ($practices as $p) {
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
    $stmt = $pdo->prepare("SELECT p.user_id FROM global_expense_payers p JOIN users u ON p.user_id = u.id WHERE p.expense_id = ? AND u.generation != 0");
    $stmt->execute([$exp['id']]);
    $valid_payer_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($valid_payer_ids) > 0) {
        $m_unit = $exp['amount'] / count($valid_payer_ids);
        foreach ($valid_payer_ids as $uid) { if (isset($user_misc_fee[$uid])) $user_misc_fee[$uid] += $m_unit; }
    }
}

$filename = "total_roster_" . date('Ymd') . ".csv";
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'w');
fputcsv($output, ["合計集金名簿 (" . date('Y/m/d') . " 出力)"]);
fputcsv($output, []);
fputcsv($output, ["代", "氏名", "合計金額", "コート代", "ボール代", "その他雑費", "練習時間(h)"]);

foreach ($users as $u) {
    $uid = $u['id'];
    $b_fee = ($all_users_total_hours > 0) ? ($total_ball_fee * ($user_hours[$uid] / $all_users_total_hours)) : 0;
    $total = round($user_court_fee[$uid]) + round($b_fee) + round($user_misc_fee[$uid]);
    
    // ★変更：0代以外なら、0円でも名簿に出力する
    if ($u['generation'] != 0) {
        fputcsv($output, [$u['generation'], $u['name_kana'], $total, round($user_court_fee[$uid]), round($b_fee), round($user_misc_fee[$uid]), $user_hours[$uid]]);
    }
}
fclose($output);
exit;