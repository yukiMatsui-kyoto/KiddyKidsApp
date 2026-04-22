<?php
// cancel_practice.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $practice_id = $_POST['practice_id'];
    
    // is_cancelled（中止フラグ）を 1 に更新する
    $stmt = $pdo->prepare("UPDATE practices SET is_cancelled = 1 WHERE id = :id");
    $stmt->execute([':id' => $practice_id]);
}

// 処理が終わったら管理画面に戻る
header('Location: admin.php');
exit;
?>