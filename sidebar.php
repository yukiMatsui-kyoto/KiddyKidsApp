<nav class="sidebar">
    <div class="sidebar-user">
        <p>👤 <?php echo htmlspecialchars($_SESSION['name_kana']); ?> さん</p>
    </div>
    <ul>
        <li><a href="top.php">🏠 ホーム</a></li>
        <li><a href="attendance_list.php">📅 練習参加登録</a></li>
        <li><a href="mypage.php">💰 マイページ</a></li>
        <li><a href="admin.php">⚙️ 管理画面</a></li>
        <li><a href="logout.php">🚪 ログアウト</a></li>
    </ul>
</nav>