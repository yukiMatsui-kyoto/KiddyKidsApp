<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>FreshTSystem - ログイン</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-box">
        <h1>FreshTSystem - ログイン</h1>
        <p>代と、フルネーム（ひらがな）を入力してください</p>
        
        <form action="login_action.php" method="POST">
            <label><input type="number" name="generation" required style="width: 60px;"> 代</label><br><br>
            <input type="text" name="name_kana" placeholder="例：やまだたろう" required><br><br>
            <button type="submit">ログイン</button>
        </form>
    </div>
</body>
</html>