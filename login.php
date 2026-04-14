<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-box">
        <h1>フレ団出欠管理</h1>
        <p>ユーザー名とパスワードを入力してください</p>
        
        <form action="login_action.php" method="POST">
            <input type="text" name="username" placeholder="ユーザー名" required><br>
            <input type="password" name="password" placeholder="パスワード" required><br>
            <button type="submit">ログイン</button>
        </form>
    </div>
</body>
</html>