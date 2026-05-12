<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KiddyKiddsFreshers - ログイン</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <div class="login_logo-area">
                <img src="login_logo.png" alt="logo" onerror="this.style.display='none'">
            </div>

            <h1>KiddyKiddsFreshers<br>ログイン</h1>
            <p>代と、フルネーム（ひらがな）を入力してください</p>
            
            <form action="login_action.php" method="POST">
                <div class="input-group">
                    <label>代</label>
                    <input type="number" name="generation" required placeholder="例：50">
                </div>
                
                <div class="input-group">
                    <label>名前（ひらがな）</label>
                    <input type="text" name="name_kana" placeholder="例：なかがわしんじ" required>
                </div>
                
                <button type="submit">ログイン</button>
            </form>
        </div>
    </div>
</body>
</html>