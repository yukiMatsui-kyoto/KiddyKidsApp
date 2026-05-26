<?php
session_start();
require_once 'db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin.php'); exit; }

$date = $_POST['practice_date'];
$fee = $_POST['facility_fee'];
$time_preset = $_POST['time_preset'];
$location_preset = $_POST['location_preset'];
$court_number = $_POST['court_number'] ?? null;
// ★追加：対象の取得
$gender_target = empty($_POST['gender_target']) ? null : $_POST['gender_target'];

if ($time_preset === 'daytime') { $start_time = '16:00:00'; $end_time = '18:00:00'; } 
elseif ($time_preset === 'night') { $start_time = '18:00:00'; $end_time = '21:00:00'; } 
else { $start_time = $_POST['custom_start_time']; $end_time = $_POST['custom_end_time']; }

$location = ($location_preset === 'custom') ? $_POST['custom_location'] : $location_preset;

$permit_filename = null;
if (isset($_FILES['permit_file']) && $_FILES['permit_file']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    $permit_filename = date('Ymd_His') . '_' . basename($_FILES['permit_file']['name']);
    move_uploaded_file($_FILES['permit_file']['tmp_name'], $upload_dir . $permit_filename);
}

try {
    // ★追加：gender_targetを保存
    $stmt = $pdo->prepare("INSERT INTO practices (practice_date, start_time, end_time, location, court_number, facility_fee, permit_path, is_cancelled, gender_target) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)");
    $stmt->execute([$date, $start_time, $end_time, $location, $court_number, $fee, $permit_filename, $gender_target]);
    header('Location: admin.php'); exit;
} catch (PDOException $e) { echo "エラー: " . $e->getMessage(); exit; }
?>