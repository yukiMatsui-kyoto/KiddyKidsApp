<?php
// admin_accounting.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';
if (!isset($_SESSION['is_admin'])) { header('Location: admin_login.php'); exit; }

// ユーザー削除処理
if (isset($_POST['delete_user_id'])) {
    $del_id = $_POST['delete_user_id'];
    $pdo->prepare("DELETE FROM practice_attendance WHERE user_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$del_id]);
    header("Location: admin_accounting.php"); exit;
}

// 一括公開処理
if (isset($_POST['publish_all'])) {
    $pdo->prepare("UPDATE practices SET is_published = 1 WHERE practice_date <= CURDATE()")->execute();
    header("Location: admin_accounting.php?published=1"); exit;
}

$users = $pdo->query("SELECT id, name_kana, generation FROM users ORDER BY generation DESC, name_kana ASC")->fetchAll();
$practices = $pdo->query("SELECT * FROM practices WHERE practice_date <= CURDATE() AND is_cancelled = 0")->fetchAll();

$user_totals = [];
foreach ($users as $u) { $user_totals[$u['id']] = 0; }

foreach ($practices as $p) {
    $stmt = $pdo->prepare("SELECT user_id, status, is_penalty FROM practice_attendance WHERE practice_id = ?");
    $stmt->execute([$p['id']]);
    $atts = $stmt->fetchAll();
    
    $total_weight = 0; $temp_weights = [];
    foreach ($atts as $a) {
        if ($a['status'] === '参加' || $a['status'] === 'ドタ参' || $a['is_penalty'] == 1) $total_weight += 1.0;
        elseif ($a['status'] === '途中' || $a['status'] === 'ドタ途中参') $total_weight += 0.5;
        $temp_weights[$a['user_id']] = ($a['status'] === '参加' || $a['status'] === 'ドタ参' || $a['is_penalty'] == 1) ? 1.0 : 0.5;
    }
    $unit_price = ($total_weight > 0) ? ($p['facility_fee'] / $total_weight) : 0;
    foreach ($temp_weights as $uid => $w) {
        $user_totals[$uid] += round($unit_price * $w);
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>全体会計 - FreshTSystem</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
    <header class="global-navbar"> 
        <div class="logo-area">
                <span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span>
                <img src="logo.png" alt="logo" onerror="this.style.display='none'">    
                <strong>管理画面</strong>
        </div>
    </header>
        <main class="content-body">
            <div class="main-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="margin:0;">今日までの全練習の合計</h3>
                    <div style="display: flex; gap: 10px;">
                        <a href="export_roster.php" class="btn-submit" style="background:#28a745; text-decoration:none;">CSV出力(Excel出力)</a>
                        <form method="POST"><button type="submit" name="publish_all" class="btn-publish" onclick="return confirm('一括公開しますか？')">全員に一括公開</button></form>
                    </div>
                </div>

                <table class="practice-table" style="display:block; overflow-x:auto; white-space:nowrap;">
                    <thead><tr style="background: #4a86e8; color: white;"><th>代</th><th>名前</th><th>合計金額</th><th>操作</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['generation']); ?></td>
                            <td><strong><?php echo htmlspecialchars($u['name_kana']); ?></strong></td>
                            <td style="font-weight: bold; <?php echo ($user_totals[$u['id']] == 0) ? 'color:#ccc;' : 'color:#d35400;'; ?>">
                                ¥<?php echo number_format($user_totals[$u['id']]); ?>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('このユーザーを完全に削除しますか？');">
                                    <input type="hidden" name="delete_user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" style="background:#dc3545; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;">削除</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>