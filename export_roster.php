<?php
// export_roster.php
session_start();
require_once 'db.php';
if (!isset($_SESSION['is_admin'])) { exit('Access Denied'); }

// 全ユーザーと今日までの全練習を取得
$users = $pdo->query("SELECT id, name_kana, generation FROM users ORDER BY generation DESC, name_kana ASC")->fetchAll();
$practices = $pdo->query("SELECT * FROM practices WHERE practice_date <= CURDATE() AND is_cancelled = 0")->fetchAll();

$user_totals = [];
foreach ($users as $u) { $user_totals[$u['id']] = 0; }

foreach ($practices as $p) {
    $stmt = $pdo->prepare("SELECT user_id, status, is_penalty FROM practice_attendance WHERE practice_id = ?");
    $stmt->execute([$p['id']]);
    $atts = $stmt->fetchAll();
    
    $total_weight = 0;
    $temp_weights = [];
    foreach ($atts as $a) {
        $w = 0;
        if ($a['status'] === '参加' || $a['is_penalty'] == 1) $w = 1.0;
        elseif ($a['status'] === '途中') $w = 0.5;
        $temp_weights[$a['user_id']] = $w;
        $total_weight += $w;
    }
    $unit_price = ($total_weight > 0) ? ($p['facility_fee'] / $total_weight) : 0;
    foreach ($temp_weights as $uid => $w) {
        $user_totals[$uid] += round($unit_price * $w);
    }
}

// --- 📥 CSV生成開始 ---
$filename = "total_roster_" . date('Ymd') . ".csv";

// ヘッダーを送信
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// ExcelのためのBOM（文字化け防止）
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// タイトル行
fputcsv($output, ["合計集金名簿 (" . date('Y/m/d') . " 出力)"]);
fputcsv($output, []); // 空行

// 見出し
fputcsv($output, ["代", "氏名", "合計負担額"]);

// データ行
foreach ($users as $u) {
    fputcsv($output, [
        $u['generation'],
        $u['name_kana'],
        $user_totals[$u['id']]
    ]);
}

fclose($output);
exit;