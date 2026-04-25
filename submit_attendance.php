<?php
// submit_attendance.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: attendance_list.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$practice_id = $_POST['practice_id'];
$status = $_POST['status']; // "参加", "途中", "欠席" 

try {
    // ① 練習日の日付を取得（8日前ペナルティの計算用）
    $stmt = $pdo->prepare("SELECT practice_date FROM practices WHERE id = :p_id");
    $stmt->execute([':p_id' => $practice_id]);
    $practice = $stmt->fetch();

    if ($practice) {
        $practice_date = $practice['practice_date'];
        $today = date('Y-m-d');
        
        // 日数の差分を計算
        $days_left = (strtotime($practice_date) - strtotime($today)) / (60 * 60 * 24);
        
        // 状態が「欠席（キャンセル）」で、かつ残り7日以下の場合はペナルティを課す
        $is_penalty = ($status === '欠席' && $days_left < 8) ? 1 : 0;

        // ② 古い回答があれば一度消去する
        $stmt = $pdo->prepare("DELETE FROM practice_attendance WHERE practice_id = :p_id AND user_id = :u_id");
        $stmt->execute([':p_id' => $practice_id, ':u_id' => $user_id]);

        // ③ 新しい回答を保存する
        $stmt = $pdo->prepare("INSERT INTO practice_attendance (practice_id, user_id, status, is_penalty) VALUES (:p_id, :u_id, :status, :is_penalty)");
        $stmt->execute([
            ':p_id' => $practice_id,
            ':u_id' => $user_id,
            ':status' => $status,
            ':is_penalty' => $is_penalty
        ]);
    }

    // 処理が終わったらリスト画面に戻る
    header('Location: attendance_list.php');
    exit;

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage();
    exit;
}
?>