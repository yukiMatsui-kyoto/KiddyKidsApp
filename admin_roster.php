<?php
date_default_timezone_set('Asia/Tokyo');
session_start();
require_once 'db.php';
if (!isset($_SESSION['is_admin'])) { header('Location: admin_login.php'); exit; }

$practice_id = $_GET['id'] ?? null;
if (!$practice_id) { header('Location: admin.php'); exit; }

// 1. 練習情報の更新
if (isset($_POST['update_practice'])) {
    $stmt = $pdo->prepare("UPDATE practices SET facility_fee = ?, location = ?, practice_date = ? WHERE id = ?");
    $stmt->execute([$_POST['facility_fee'], $_POST['location'], $_POST['practice_date'], $practice_id]);
    header("Location: admin_roster.php?id=$practice_id"); exit;
}

// 2. 練習の中止（キャンセル）
if (isset($_POST['cancel_practice'])) {
    $stmt = $pdo->prepare("UPDATE practices SET is_cancelled = 1 WHERE id = ?");
    $stmt->execute([$practice_id]);
    header("Location: admin_roster.php?id=$practice_id"); exit;
}

// 3. 出欠の消去
if (isset($_POST['delete_attendance_user'])) {
    $stmt = $pdo->prepare("DELETE FROM practice_attendance WHERE practice_id = ? AND user_id = ?");
    $stmt->execute([$practice_id, $_POST['delete_attendance_user']]);
    header("Location: admin_roster.php?id=$practice_id"); exit;
}

// 4. メンバーの手動追加（練習後追加）
if (isset($_POST['add_member'])) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO practice_attendance (practice_id, user_id, status) VALUES (?, ?, ?)");
    $stmt->execute([$practice_id, $_POST['new_user_id'], $_POST['new_status']]);
    header("Location: admin_roster.php?id=$practice_id"); exit;
}

$p = $pdo->prepare("SELECT * FROM practices WHERE id = ?");
$p->execute([$practice_id]);
$practice = $p->fetch();

$attendees = $pdo->prepare("SELECT u.id, u.name_kana, a.status, a.is_penalty FROM practice_attendance a JOIN users u ON a.user_id = u.id WHERE a.practice_id = ? ORDER BY a.status ASC");
$attendees->execute([$practice_id]);
$members = $attendees->fetchAll();

$all_users = $pdo->query("SELECT id, name_kana FROM users ORDER BY name_kana ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>詳細・編集</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="global-navbar">
            <div class="logo-area">
                <span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span>
                <strong>詳細・編集</strong>
            </div>
        </header>
        <main class="content-body">
            <div class="main-card">
                <h3>練習情報の編集</h3>
                <form method="POST">
                    <input type="hidden" name="update_practice" value="1">
                    日付: <input type="date" name="practice_date" value="<?php echo $practice['practice_date']; ?>"><br><br>
                    会場: <input type="text" name="location" value="<?php echo htmlspecialchars($practice['location']); ?>"><br><br>
                    代金: <input type="number" name="facility_fee" value="<?php echo $practice['facility_fee']; ?>"> 円<br><br>
                    <button type="submit" class="btn-submit">更新保存</button>
                </form>
                <form method="POST" style="margin-top:10px;" onsubmit="return confirm('中止しますか？');">
                    <button type="submit" name="cancel_practice" class="btn-cancel">この練習を中止する</button>
                </form>
            </div>

            <div class="main-card" style="background:#eefcf0;">
                <h3>練習後参加者登録</h3>
                <form method="POST">
                    <select name="new_user_id" required>
                        <?php foreach($all_users as $u) echo "<option value='{$u['id']}'>{$u['name_kana']}</option>"; ?>
                    </select>
                    <select name="new_status">
                        <option value="参加">参加</option><option value="途中">途中</option>
                        <option value="ドタ参">ドタ参</option><option value="ドタ途中参">ドタ途中参</option>
                    </select>
                    <button type="submit" name="add_member" class="btn-submit" style="background:#28a745;">追加</button>
                </form>
            </div>

            <div class="main-card">
                <h3>現在の参加者</h3>
                <table class="practice-table">
                    <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['name_kana']); ?></td>
                        <td><?php echo $m['status']; ?><?php if($m['is_penalty']) echo "(P)"; ?></td>
                        <td>
                            <form method="POST"><input type="hidden" name="delete_attendance_user" value="<?php echo $m['id']; ?>"><button type="submit" class="btn-cancel">消去</button></form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </main>
    </div>
</body>
</html>