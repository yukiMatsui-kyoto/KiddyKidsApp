<?php
// セッション（ログイン状態の維持）を開始
session_start();

// db.phpを読み込む
require_once 'db.php';

$gen = $_POST['generation'] ?? '';
$name = $_POST['name_kana'] ?? '';

// データベースから該当する人を探す
$sql = "SELECT * FROM users WHERE generation = :gen AND name_kana = :name";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':gen', $gen, PDO::PARAM_INT);
$stmt->bindValue(':name', $name, PDO::PARAM_STR);
$stmt->execute();

$user = $stmt->fetch(); // 結果を1件取得する

if ($user) {
    // データベースに存在した場合
    // 次のページでも「誰がログインしているか」分かるように、IDと名前を記憶させておく
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name_kana'] = $user['name_kana'];
    
    header('Location: top.php');
    exit;
} else {
    // 存在しなかった場合
    echo "<h1>代 または 名前が間違っています。</h1>";
    echo "<p>半角数字または、ひらがなで入力しているか確認してください。</p>";
    echo '<a href="login.php">ログイン画面に戻る</a>';
}
?>