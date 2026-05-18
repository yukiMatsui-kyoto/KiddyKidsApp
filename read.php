<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>概要・問い合わせ </title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="global-navbar">
            <div class="logo-area">
                <span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span>
                <img src="logo.png" alt="logo" onerror="this.style.display='none'">    
                <strong>概要・問い合わせ</strong>
            </div>
        </header>
        <main class="content-body">
            <div class="main-card">
                <h3 style="margin-top:0;">システム概要</h3>
                <p style="line-height: 1.6; color: #444;">
                    KiddyKiddsのフレ団用の練習参加登録や会計管理を行うためのシステムです。<br><br>
                    機能は以下の通りです。<br>
                    ・練習参加登録：練習ごとに参加・欠席などのステータスを登録できます。ドタ参、ドタ途中参、7日以内キャンセルは最初から参加したのと
                    同じ金額がかかるので注意して下さい！！途中参になりそうなら早めに途中参ボタンを押しときましょう！<br>
                    ・マイページ：過去の参加履歴や会計情報を確認できます。フレ団終了後まで金額は確認できません。
                    管理画面にログインできる上回生にきいてもらえると教えます。<br>
                    ・管理者機能：練習の追加・編集、ユーザー管理、会計集計などが行えます。
                </p>
            </div>

            <div class="main-card">
                <h3 style="margin-top:0;">問い合わせ</h3>
                <p style="line-height: 1.6; color: #444;">
                    システムに関する不具合はまついゆうき、練習に関する質問はつばさに
                </p>
            </div>

            <div class="main-card">
                <h3 style="margin-top:0;">開発リポジトリ</h3>
                <p style="line-height: 1.6; color: #444;">
                    <a href="https://github.com/yukiMatsui-kyoto/KiddyKidsApp" target="_blank" style="color: #4a86e8; text-decoration: none; font-weight: bold; border-bottom: 1px solid #4a86e8;">
                        GitHubでリポジトリを見る
                    </a>
                </p>
            </div>
        </main>
    </div>
</body>
</html>