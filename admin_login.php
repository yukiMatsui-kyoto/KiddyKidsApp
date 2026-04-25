<?php
// admin_login.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // パスワードチェック
    if ($_POST['password'] === 'admin') {
        $_SESSION['is_admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = "パスワードが違います。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>管理者ログイン</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="display:flex; justify-content:center; align-items:center; height:100vh; background:#f4f6f8;">
    <div class="main-card" style="width: 300px; text-align: center;">
        <h3>認証</h3>
        <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <form action="admin_login.php" method="POST">
            <input type="password" name="password" placeholder="パスワード" required style="width: 100%; padding: 10px; margin-bottom: 15px; box-sizing: border-box;">
            <button type="submit" class="btn-submit" style="width: 100%;">ログイン</button>
        </form>
    </div>
</body>
</html>