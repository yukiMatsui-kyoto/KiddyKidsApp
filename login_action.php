<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// セッション開始
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
    $_SESSION['generation'] = $user['generation']; // 念のため代もセッションに保存
    
    // ログイン保持のチェックがあれば、30日間有効なクッキーをセット
    if (isset($_POST['remember']) && $_POST['remember'] === '1') {
        setcookie('kiddy_auto_login', $user['id'], time() + 60 * 60 * 24 * 30, '/');
    }
    
    header('Location: top.php');
    exit;
} else {
    // 存在しなかった場合（※元の表示をそのまま維持）
    echo "<h1>代 または 名前が間違っています。</h1>";
    echo "<p>半角数字または、ひらがなで入力しているか確認してください。</p>";
    echo '<a href="login.php">ログイン画面に戻る</a>';
}
?>