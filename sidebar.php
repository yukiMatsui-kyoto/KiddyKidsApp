<?php
// 名前が取得できていない場合の保険
$sidebar_user_name = $_SESSION['name_kana'] ?? 'ゲスト';
?>

<nav class="global-navbar">
    <div class="nav-left">
        <div class="hamburger" onclick="toggleSidebar()">&#9776;</div>
        
        <div class="logo-area">
            <img src="logo.png" alt="ロゴ" style="height: 80px; border-radius: 5px; object-fit: cover;">
            FreshTSystem
        </div>
    </div>

    <div class="nav-right">
        <a href="mypage.php" title="マイページ">&#128100;</a>
    </div>
</nav>

<div class="sidebar" id="mySidebar">
    <div class="sidebar-user">
        <p style="margin: 0; font-weight: bold;">👤 <?php echo htmlspecialchars($sidebar_user_name); ?> さん</p>
    </div>
    
    <ul class="sidebar-menu">
        <li><a href="top.php">🏠 ホーム</a></li>
        <li><a href="attendance_list.php">📅 練習参加登録</a></li>
        <?php if (isset($_SESSION['is_admin'])): ?>
            <li style="background: #fff4e5;"><a href="admin.php">⚙️ 管理画面</a></li>
            <li style="background: #fff4e5;"><a href="admin_roster.php">集金・出欠名簿</a></li>
        <?php else: ?>
            <li><a href="admin_login.php">管理画面</a></li>
        <?php endif; ?>
        
        <li><a href="logout.php" style="color: #dc3545;">ログアウト</a></li>
    </ul>
</div>

<script>
function toggleSidebar() {
    document.getElementById('mySidebar').classList.toggle('active');
}
</script>