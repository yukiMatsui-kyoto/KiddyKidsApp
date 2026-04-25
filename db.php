<?php
// db.php
//$dsn = 'mysql:dbname=attendance_db;host=localhost;charset=utf8mb4';
//$user = 'root'; // XAMPPの初期ユーザー名はroot
//$password = ''; // XAMPPの初期パスワードは空

//try {
//    $pdo = new PDO($dsn, $user, $password);
    // エラーが出たら画面に表示する設定
//    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//} catch (PDOException $e) {
//    echo "データベース接続エラー: " . $e->getMessage();
//    exit;
//}


// db.php (InfinityFree環境用)

// ホスト名 と データベース名
$dsn = 'mysql:dbname=if0_41750767_attendance_db;host=sql100.infinityfree.com;charset=utf8mb4';

// ユーザー名 を書き換える
$user = 'if0_41750767'; 

//  パスワード を書き換える
$password = 'cz18hbgevVBuB'; 

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "データベース接続エラー: " . $e->getMessage();
    exit;
}
?>