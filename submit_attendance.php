<?php
date_default_timezone_set('Asia/Tokyo');
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $practice_id = $_POST['practice_id'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("SELECT practice_date FROM practices WHERE id = ?");
    $stmt->execute([$practice_id]);
    $p_date = $stmt->fetchColumn();
    $days_left = (strtotime($p_date) - strtotime(date('Y-m-d'))) / 86400;

    $stmt = $pdo->prepare("SELECT status FROM practice_attendance WHERE practice_id = ? AND user_id = ?");
    $stmt->execute([$practice_id, $user_id]);
    $prev_status = $stmt->fetchColumn();

    $is_penalty = 0;
    if ($status === '欠席' && $days_left <= 7 && in_array($prev_status, ['参加', '途中', 'ドタ参', 'ドタ途中参'])) {
        $is_penalty = 1;
    }

    if ($prev_status !== false) {
        $pdo->prepare("UPDATE practice_attendance SET status = ?, is_penalty = ? WHERE practice_id = ? AND user_id = ?")->execute([$status, $is_penalty, $practice_id, $user_id]);
    } else {
        $pdo->prepare("INSERT INTO practice_attendance (practice_id, user_id, status, is_penalty) VALUES (?, ?, ?, ?)")->execute([$practice_id, $user_id, $status, $is_penalty]);
    }
    header('Location: attendance_list.php');
}