<?php
// update_profile.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mypage.php');
    exit;
}

$user_id = $_SESSION['user_id'];
// 入力された表示名（空欄の場合は NULL にする）
$display_name = trim($_POST['display_name']);
if ($display_name === '') {
    $display_name = null;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET display_name = :d_name WHERE id = :u_id");
    $stmt->execute([':d_name' => $display_name, ':u_id' => $user_id]);

    // セッションの記憶も新しい表示名に更新しておく
    $_SESSION['display_name'] = $display_name;

    // 変更が完了したらマイページに戻る
    header('Location: mypage.php?updated=1');
    exit;

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage();
    exit;
}
?>