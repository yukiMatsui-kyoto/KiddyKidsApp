<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['is_admin'])) { exit('Access Denied'); }

$users = $pdo->query("SELECT id, name_kana, generation FROM users ORDER BY generation DESC, name_kana ASC")->fetchAll();
$practices = $pdo->query("SELECT * FROM practices WHERE practice_date <= CURDATE() AND is_cancelled = 0")->fetchAll();

function getPracticeStats($practice, $attendees) {
    $start = strtotime($practice['start_time']); $end = strtotime($practice['end_time']);
    $practice_hours = ($end - $start) / 3600; if ($practice_hours <= 0) $practice_hours = 2;

    $total_weight = 0; $total_hours = 0; $total_count = 0; $user_stats = [];
    foreach ($attendees as $a) {
        $st = $a['status']; $pen = $a['is_penalty'] ?? 0; $w = 0; $h = 0; $c = 0;
        
        $gen = isset($a['generation']) ? $a['generation'] : -1;
        if ($gen == 0) { $w = 0; $h = 0; $c = 0; } 
        else {
            if ($st === '参加' || $st === 'ドタ参' || $st === 'ドタ途中参' || $pen == 1) { 
                $w = 1.0; $h = $practice_hours; $c = 1; 
            } elseif ($st === '途中' || $st === '途中参') {
                if (abs($practice_hours - 2.0) < 0.1) { $w = 0.5; $h = 1.0; } 
                elseif (abs($practice_hours - 3.0) < 0.1) { $w = 2/3; $h = 2.0; } 
                else { $w = 0.5; $h = $practice_hours * 0.5; }
                $c = 1;
            } elseif ($st === 'お手伝い') { 
                $w = 0; $h = 0; $c = 0; 
            }
        }
        
        if (isset($a['override_weight']) && $a['override_weight'] !== null) $w = (float)$a['override_weight'];
        if (isset($a['override_hours']) && $a['override_hours'] !== null) $h = (float)$a['override_hours'];

        $user_stats[$a['user_id']] = ['weight' => $w, 'hours' => $h, 'count' => $c];
        $total_weight += $w; $total_hours += $h; $total_count += $c;
    }
    return ['total_weight' => $total_weight, 'total_hours' => $total_hours, 'total_count' => $total_count, 'user_stats' => $user_stats];
}

$user_court_fee = []; $user_misc_fee = []; $user_counts = []; $user_advance = [];
foreach ($users as $u) { $user_court_fee[$u['id']] = 0; $user_misc_fee[$u['id']] = 0; $user_counts[$u['id']] = 0; $user_advance[$u['id']] = 0; }
$all_users_total_count = 0;

foreach ($practices as $p) {
    if (!empty($p['booked_by']) && isset($user_advance[$p['booked_by']])) { $user_advance[$p['booked_by']] += $p['facility_fee']; }
    $atts = $pdo->prepare("SELECT u.generation, a.user_id, a.status, a.is_penalty, a.override_weight, a.override_hours FROM practice_attendance a JOIN users u ON a.user_id = u.id WHERE a.practice_id = ?"); 
    $atts->execute([$p['id']]); $attendees = $atts->fetchAll();
    $stats = getPracticeStats($p, $attendees);
    
    $c_unit = ($stats['total_weight'] > 0) ? ($p['facility_fee'] / $stats['total_weight']) : 0;
    foreach ($stats['user_stats'] as $uid => $data) {
        if (isset($user_court_fee[$uid])) { $user_court_fee[$uid] += $c_unit * $data['weight']; $user_counts[$uid] += $data['count']; }
    }
    $all_users_total_count += $stats['total_count'];
}

$total_ball_fee = $pdo->query("SELECT SUM(amount) FROM global_expenses WHERE expense_type = 'ball'")->fetchColumn() ?: 0;
$misc_expenses = $pdo->query("SELECT * FROM global_expenses WHERE expense_type = 'misc'")->fetchAll();

foreach ($misc_expenses as $exp) {
    if (!empty($exp['paid_by']) && isset($user_advance[$exp['paid_by']])) { $user_advance[$exp['paid_by']] += $exp['amount']; }
    $stmt = $pdo->prepare("SELECT p.user_id FROM global_expense_payers p JOIN users u ON p.user_id = u.id WHERE p.expense_id = ?");
    $stmt->execute([$exp['id']]);
    $valid_payer_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($valid_payer_ids) > 0) {
        $m_unit = $exp['amount'] / count($valid_payer_ids);
        foreach ($valid_payer_ids as $uid) { if (isset($user_misc_fee[$uid])) $user_misc_fee[$uid] += $m_unit; }
    }
}

$ball_expenses = $pdo->query("SELECT * FROM global_expenses WHERE expense_type = 'ball'")->fetchAll();
foreach ($ball_expenses as $exp) {
    if (!empty($exp['paid_by']) && isset($user_advance[$exp['paid_by']])) { $user_advance[$exp['paid_by']] += $exp['amount']; }
}

$filename = "total_roster_" . date('Ymd') . ".csv";
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'w');
fputcsv($output, ["合計集金名簿 (" . date('Y/m/d') . " 出力)"]);
fputcsv($output, []);
fputcsv($output, ["代", "氏名", "最終金額", "コート代", "ボール代", "その他雑費", "立替分(マイナス)", "参加回数(回)"]);

foreach ($users as $u) {
    $uid = $u['id'];
    $b_fee = ($all_users_total_count > 0) ? ($total_ball_fee * ($user_counts[$uid] / $all_users_total_count)) : 0;
    $total = round($user_court_fee[$uid]) + round($b_fee) + round($user_misc_fee[$uid]) - round($user_advance[$uid]);
    
    if ($u['generation'] != 0 || $total != 0) {
        fputcsv($output, [$u['generation'], $u['name_kana'], $total, round($user_court_fee[$uid]), round($b_fee), round($user_misc_fee[$uid]), round($user_advance[$uid]), $user_counts[$uid]]);
    }
}
fclose($output);
exit;