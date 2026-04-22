<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}
$date = $_POST['practice_date'];
$fee = $_POST['facility_fee'];

$time_preset = $_POST['time_preset'];
$location_preset = $_POST['location_preset'];

// 時間の判定
if ($time_preset === 'daytime') {
    $start_time = '16:00:00';
    $end_time = '18:00:00';
} elseif ($time_preset === 'night') {
    $start_time = '18:00:00';
    $end_time = '21:00:00';
} else {
    // 「その他」が選ばれた場合は、自由入力欄の値を使う
    $start_time = $_POST['custom_start_time'];
    $end_time = $_POST['custom_end_time'];
}

// コートの判定
if ($location_preset === 'custom') {
    // 「その他」が選ばれた場合は、自由入力欄の値を使う
    $location = $_POST['custom_location'];
} else {
    // それ以外（宝、岡崎）はボタンの値をそのまま使う
    $location = $location_preset;
}

// --- ファイルアップロード処理 ---
$permit_filename = null;
if (isset($_FILES['permit_file']) && $_FILES['permit_file']['error'] === UPLOAD_ERR_OK) {
    // 保存先のフォルダ（kdappの中に「uploads」フォルダを自動作成）
    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // ファイル名が被らないように、日付をファイル名にくっつける
    $original_name = basename($_FILES['permit_file']['name']);
    $permit_filename = date('Ymd_His') . '_' . $original_name;
    $target_file = $upload_dir . $permit_filename;
    
    // 一時フォルダから本番フォルダへ移動
    move_uploaded_file($_FILES['permit_file']['tmp_name'], $target_file);
}

// --- データベースへ保存 ---
try {
    $stmt = $pdo->prepare("INSERT INTO practices (practice_date, start_time, end_time, location, facility_fee, permit_path, is_cancelled) VALUES (:date, :start, :end, :loc, :fee, :permit, 0)");
    $stmt->execute([
        ':date' => $date,
        ':start' => $start_time,
        ':end' => $end_time,
        ':loc' => $location,
        ':fee' => $fee,
        ':permit' => $permit_filename
    ]);

    header('Location: admin.php');
    exit;

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage();
    exit;
}
?>