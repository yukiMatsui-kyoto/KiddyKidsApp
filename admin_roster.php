<?php
date_default_timezone_set('Asia/Tokyo');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';

if (!isset($_SESSION['is_admin'])) { header('Location: admin_login.php'); exit; }

$practice_id = $_GET['id'] ?? null;
if (!$practice_id) { header('Location: admin.php'); exit; }

// --- データベース自動拡張（ここで確実にカラムを追加します） ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS practice_roles (practice_id INT, user_id INT, role_type VARCHAR(50), PRIMARY KEY(practice_id, user_id, role_type)) DEFAULT CHARSET=utf8mb4");
    $pdo->exec("ALTER TABLE practice_roles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("ALTER TABLE practice_attendance ADD COLUMN override_weight FLOAT DEFAULT NULL");
    $pdo->exec("ALTER TABLE practice_attendance ADD COLUMN override_hours FLOAT DEFAULT NULL");
    // ★追加：コート番号と男子練/女子練のカラムを詳細画面でも確実に追加する
    $pdo->exec("ALTER TABLE practices ADD COLUMN court_number VARCHAR(50) DEFAULT NULL");
    $pdo->exec("ALTER TABLE practices ADD COLUMN gender_target VARCHAR(20) DEFAULT NULL");
} catch (PDOException $e) {}


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
        $stmt = $pdo->prepare("UPDATE practices SET practice_date = ?, start_time = ?, end_time = ?, location = ?, court_number = ?, facility_fee = ?, gender_target = ? WHERE id = ?");
        $stmt->execute([
            $_POST['practice_date'], 
            $_POST['start_time'], 
            $_POST['end_time'], 
            $_POST['location'], 
            $_POST['court_number'], 
            $_POST['facility_fee'], 
            $_POST['gender_target'], 
            $practice_id
        ]);
    }

    if (isset($_POST['add_member'])) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO practice_attendance (practice_id, user_id, status) VALUES (?, ?, ?)");
        $stmt->execute([$practice_id, $_POST['new_user_id'], $_POST['new_status']]);
    }

    if (isset($_POST['delete_attendance'])) {
        $target_uid = $_POST['delete_attendance'];
        $pdo->prepare("DELETE FROM practice_attendance WHERE practice_id = ? AND user_id = ?")->execute([$practice_id, $target_uid]);
        $pdo->prepare("DELETE FROM practice_roles WHERE practice_id = ? AND user_id = ?")->execute([$practice_id, $target_uid]);
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
                    コート番号: <input type="text" name="court_number" value="<?php echo htmlspecialchars($p['court_number'] ?? ''); ?>" placeholder="例: 1, 2"><br>
                    
                    対象: 
                    <label><input type="radio" name="gender_target" value="" <?php if(empty($p['gender_target'])) echo 'checked'; ?>> 指定なし</label>
                    <label><input type="radio" name="gender_target" value="男子練" <?php if(($p['gender_target'] ?? '') === '男子練') echo 'checked'; ?>> 男子練</label>
                    <label><input type="radio" name="gender_target" value="女子練" <?php if(($p['gender_target'] ?? '') === '女子練') echo 'checked'; ?>> 女子練</label><br>

                    コート代: <input type="number" name="facility_fee" value="<?php echo $p['facility_fee']; ?>"> 円<br>
                    <button type="submit" class="btn-submit" style="margin-top:10px;">基本情報を保存</button>
                </form>
                
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ccc;">
                    <p style="font-weight: bold; margin-bottom: 8px;">アップロード済みの許可証</p>
                    <?php if (!empty($p['permit_path'])): ?>
                        <?php 
                        $ext = strtolower(pathinfo($p['permit_path'], PATHINFO_EXTENSION));
                        $file_url = 'uploads/' . htmlspecialchars($p['permit_path']);
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                            <img src="<?php echo $file_url; ?>" alt="コートカード" style="max-width: 100%; max-height: 400px; border: 1px solid #ddd; border-radius: 4px;">
                            <br>
                            <a href="<?php echo $file_url; ?>" target="_blank" style="display: inline-block; margin-top: 8px; font-size: 0.9em; color: #0056b3; text-decoration: underline;">拡大して見る(別タブ)</a>
                        <?php else: ?>
                            <a href="<?php echo $file_url; ?>" target="_blank" class="btn-submit" style="background:#17a2b8; text-decoration:none; display:inline-block;">許可証（PDF等）を開く</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #999; font-size: 0.9em;">※ファイルはアップロードされていません。</p>
                    <?php endif; ?>
                </div>

                <form method="POST" action="admin_roster.php?id=<?php echo htmlspecialchars($practice_id); ?>" style="margin-top:20px;" onsubmit="return confirm('この練習を中止しますか？');">
                    <button type="submit" name="cancel_practice" class="btn-cancel">練習を中止（キャンセル）にする</button>
                </form>
            </div>

            <div class="main-card">
                <h3>参加者・役割・手動調整</h3>
                <p style="font-size:0.85em; color:#666;">※0代は、どれを選んでも自動で「お手伝い(0円)」になります。</p>
                <form method="POST" action="admin_roster.php?id=<?php echo htmlspecialchars($practice_id); ?>">
                    <table class="practice-table" style="display:block; overflow-x:auto; white-space:nowrap;">
                        <thead>
                            <tr><th>名前</th><th>出欠</th><th>手動調整 (割合/時間)</th><th>役割</th><th>操作</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendees as $m): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($m['name_kana']); ?></strong><br>
                                    <span style="font-size:0.8em; color:#888;">
                                        <?php echo ($m['generation'] == 0) ? 'お手伝い' : $m['generation'] . '代'; ?>
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
                                    <button type="submit" name="delete_attendance" value="<?php echo $m['id']; ?>" class="btn-cancel" onclick="return confirm('この参加データを消去しますか？')">消去</button>
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