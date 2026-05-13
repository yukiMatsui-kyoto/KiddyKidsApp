<?php
date_default_timezone_set('Asia/Tokyo');
session_start();
require_once 'db.php';

// 管理者認証チェック
if (!isset($_SESSION['is_admin'])) { header('Location: admin_login.php'); exit; }

$practice_id = $_GET['id'] ?? null;
if (!$practice_id) { header('Location: admin.php'); exit; }

// --- データベース自動拡張 ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS practice_roles (practice_id INT, user_id INT, role_type VARCHAR(50), PRIMARY KEY(practice_id, user_id, role_type)) DEFAULT CHARSET=utf8mb4");
    $pdo->exec("ALTER TABLE practice_roles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("ALTER TABLE practice_attendance ADD COLUMN override_weight FLOAT DEFAULT NULL");
    $pdo->exec("ALTER TABLE practice_attendance ADD COLUMN override_hours FLOAT DEFAULT NULL");
} catch (PDOException $e) {}

// --- 更新処理の受付 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_details'])) {
        $pdo->prepare("DELETE FROM practice_roles WHERE practice_id = ?")->execute([$practice_id]);

        if (isset($_POST['ow'])) {
            foreach ($_POST['ow'] as $uid => $val) {
                $ow = ($val === '') ? null : (float)$val;
                $oh = ($_POST['oh'][$uid] === '') ? null : (float)$_POST['oh'][$uid];
                
                $pdo->prepare("UPDATE practice_attendance SET override_weight = ?, override_hours = ? WHERE practice_id = ? AND user_id = ?")
                    ->execute([$ow, $oh, $practice_id, $uid]);
                
                if (isset($_POST['roles'][$uid])) {
                    foreach ($_POST['roles'][$uid] as $role) {
                        $pdo->prepare("INSERT INTO practice_roles (practice_id, user_id, role_type) VALUES (?, ?, ?)")
                            ->execute([$practice_id, $uid, $role]);
                    }
                }
            }
        }
    }

    if (isset($_POST['update_practice'])) {
        $stmt = $pdo->prepare("UPDATE practices SET practice_date = ?, start_time = ?, end_time = ?, location = ?, facility_fee = ? WHERE id = ?");
        $stmt->execute([
            $_POST['practice_date'], $_POST['start_time'], $_POST['end_time'], 
            $_POST['location'], $_POST['facility_fee'], $practice_id
        ]);
    }

    if (isset($_POST['add_member'])) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO practice_attendance (practice_id, user_id, status) VALUES (?, ?, ?)");
        $stmt->execute([$practice_id, $_POST['new_user_id'], $_POST['new_status']]);
    }

    if (isset($_POST['delete_attendance'])) {
        $stmt = $pdo->prepare("DELETE FROM practice_attendance WHERE practice_id = ? AND user_id = ?");
        $stmt->execute([$practice_id, $_POST['del_uid']]);
    }

    if (isset($_POST['cancel_practice'])) {
        $pdo->prepare("UPDATE practices SET is_cancelled = 1 WHERE id = ?")->execute([$practice_id]);
    }

    header("Location: admin_roster.php?id=$practice_id");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM practices WHERE id = ?");
$stmt->execute([$practice_id]);
$p = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT u.id, u.name_kana, u.generation, a.status, a.is_penalty, a.override_weight, a.override_hours 
    FROM practice_attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.practice_id = ? 
    ORDER BY u.generation DESC, u.name_kana ASC
");
$stmt->execute([$practice_id]);
$attendees = $stmt->fetchAll();

$role_stmt = $pdo->prepare("SELECT user_id, role_type FROM practice_roles WHERE practice_id = ?");
$role_stmt->execute([$practice_id]);
$role_map = [];
foreach ($role_stmt->fetchAll() as $r) { $role_map[$r['user_id']][] = $r['role_type']; }

$all_users = $pdo->query("SELECT id, name_kana, generation FROM users ORDER BY generation DESC, name_kana ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>詳細・編集 - FreshTSystem</title>
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
                <h3>練習基本情報の編集</h3>
                <form method="POST" action="admin_roster.php?id=<?php echo htmlspecialchars($practice_id); ?>">
                    <input type="hidden" name="update_practice" value="1">
                    日付: <input type="date" name="practice_date" value="<?php echo $p['practice_date']; ?>"><br>
                    時間: <input type="time" name="start_time" value="<?php echo $p['start_time']; ?>"> 〜 
                          <input type="time" name="end_time" value="<?php echo $p['end_time']; ?>"><br>
                    会場: <input type="text" name="location" value="<?php echo htmlspecialchars($p['location']); ?>"><br>
                    コート代: <input type="number" name="facility_fee" value="<?php echo $p['facility_fee']; ?>"> 円<br>
                    <button type="submit" class="btn-submit" style="margin-top:10px;">基本情報を保存</button>
                </form>
                <form method="POST" action="admin_roster.php?id=<?php echo htmlspecialchars($practice_id); ?>" style="margin-top:10px;" onsubmit="return confirm('この練習を中止しますか？');">
                    <button type="submit" name="cancel_practice" class="btn-cancel">練習を中止（キャンセル）にする</button>
                </form>
            </div>

            <div class="main-card">
                <h3>参加者・役割・手動調整</h3>
                <p style="font-size:0.85em; color:#666;">※0代のユーザーは、どの出欠を選んでも自動で「お手伝い(0円)」になります。</p>
                <form method="POST" action="admin_roster.php?id=<?php echo htmlspecialchars($practice_id); ?>">
                    <table class="practice-table" style="display:block; overflow-x:auto; white-space:nowrap;">
                        <thead>
                            <tr>
                                <th>名前</th>
                                <th>出欠</th>
                                <th>手動調整 (割合/時間)</th>
                                <th>役割</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendees as $m): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($m['name_kana']); ?></strong><br>
                                    <span style="font-size:0.8em; color:#888;">
                                        <?php echo ($m['generation'] == 0) ? 'OB/お手伝い' : $m['generation'] . '代'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo ($m['generation'] == 0) ? '<span style="color:#28a745; font-weight:bold;">お手伝い</span>' : $m['status']; ?>
                                    <?php if($m['is_penalty']) echo "<br><span style='color:red; font-size:0.8em;'>キャンセル料対象</span>"; ?>
                                </td>
                                <td>
                                    <?php if($m['generation'] != 0): ?>
                                        割合: <input type="number" step="0.01" name="ow[<?php echo $m['id']; ?>]" value="<?php echo $m['override_weight']; ?>" style="width:60px; padding:2px;" placeholder="自動"><br>
                                        時間: <input type="number" step="0.1" name="oh[<?php echo $m['id']; ?>]" value="<?php echo $m['override_hours']; ?>" style="width:60px; padding:2px; margin-top:3px;" placeholder="自動">
                                    <?php else: ?>
                                        <span style="color:#ccc; font-size:0.8em;">調整不要(0固定)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <label><input type="checkbox" name="roles[<?php echo $m['id']; ?>][]" value="運搬" <?php if(isset($role_map[$m['id']]) && in_array('運搬', $role_map[$m['id']])) echo 'checked'; ?>> 運搬</label><br>
                                    <label><input type="checkbox" name="roles[<?php echo $m['id']; ?>][]" value="仕切り" <?php if(isset($role_map[$m['id']]) && in_array('仕切り', $role_map[$m['id']])) echo 'checked'; ?>> 仕切り</label>
                                </td>
                                <td>
                                    <button type="submit" name="delete_attendance" value="1" class="btn-cancel" onclick="return confirm('この参加データを消去しますか？')">消去</button>
                                    <input type="hidden" name="del_uid" value="<?php echo $m['id']; ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top:20px;">
                        <button type="submit" name="update_details" class="btn-submit" style="background:#17a2b8;">参加者情報を一括保存</button>
                    </div>
                </form>
            </div>

            <div class="main-card" style="background:#eefcf0;">
                <h3>メンバーを直接追加</h3>
                <form method="POST" action="admin_roster.php?id=<?php echo htmlspecialchars($practice_id); ?>">
                    <select name="new_user_id" required>
                        <?php foreach($all_users as $u) echo "<option value='{$u['id']}'>({$u['generation']}代) {$u['name_kana']}</option>"; ?>
                    </select>
                    <select name="new_status">
                        <option value="参加">参加</option>
                        <option value="途中参">途中参</option>
                        <option value="ドタ参">ドタ参</option>
                        <option value="ドタ途中参">ドタ途中参</option>
                        <option value="お手伝い">お手伝い</option>
                    </select>
                    <button type="submit" name="add_member" class="btn-submit" style="background:#28a745;">追加</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>