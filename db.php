<?php
// db.php
$dsn = 'mysql:dbname=attendance_db;host=localhost;charset=utf8mb4';
$user = 'root'; // XAMPPの初期ユーザー名はroot
$password = ''; // XAMPPの初期パスワードは空

try {
    $pdo = new PDO($dsn, $user, $password);
    // エラーが出たら画面に表示する設定
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "データベース接続エラー: " . $e->getMessage();
    exit;
}
?>