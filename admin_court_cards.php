<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['is_admin'])) { header('Location: admin_login.php'); exit; }

// 新規ユーザーのスピード追加
if (isset($_POST['add_user'])) {
    $gen = $_POST['generation'];
    $name = trim($_POST['name_kana']);
    $pdo->prepare("INSERT INTO users (generation, name_kana) VALUES (?, ?)")->execute([$gen, $name]);
    header("Location: admin_court_cards.php?added=1"); exit;
}

// コート立替者の設定
if (isset($_POST['assign_court'])) {
    $pid = $_POST['practice_id'];
    $uid = empty($_POST['user_id']) ? null : $_POST['user_id'];
    $pdo->prepare("UPDATE practices SET booked_by = ? WHERE id = ?")->execute([$uid, $pid]);
    header("Location: admin_court_cards.php?updated=1"); exit;
}

$users = $pdo->query("SELECT id, name_kana, generation FROM users ORDER BY generation DESC, name_kana ASC")->fetchAll();
$practices = $pdo->query("SELECT * FROM practices ORDER BY practice_date DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>コートカード（立替管理）</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="global-navbar">
            <div class="logo-area">
                <span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span>
                <img src="logo.png" alt="logo" onerror="this.style.display='none'">    
                <strong>コートカード（立替管理）</strong>
            </div>
        </header>
        <main class="content-body">
            
            <div class="main-card" style="margin-bottom: 20px; background: #fdfbf7;">
                <h3>データベースにいない人（上回、経等）を追加</h3>
                <form method="POST" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <input type="number" name="generation" placeholder="代 (例: 0)" required style="width:100px;">
                    <input type="text" name="name_kana" placeholder="名前 (ひらがな)" required>
                    <button type="submit" name="add_user" class="btn-submit">追加する</button>
                </form>
            </div>

            <div class="main-card">
                <h3>コートカード チャージ設定</h3>
                <table class="practice-table" style="display:block; overflow-x:auto; white-space:nowrap;">
                    <thead><tr><th>日付</th><th>場所</th><th>コート代</th><th>許可証(画像)</th><th>立替者を選択</th></tr></thead>
                    <tbody>
                        <?php foreach ($practices as $p): ?>
                        <tr>
                            <td><?php echo date('n/j', strtotime($p['practice_date'])); ?></td>
                            <td><?php echo htmlspecialchars($p['location']); ?></td>
                            <td>¥<?php echo number_format($p['facility_fee']); ?></td>
                            <td>
                                <?php if (!empty($p['permit_path'])): ?>
                                    <a href="uploads/<?php echo htmlspecialchars($p['permit_path']); ?>" target="_blank" style="color: #0056b3; font-weight: bold; text-decoration: underline;">確認する</a>
                                <?php else: ?>
                                    <span style="color: #ccc; font-size: 0.85em;">なし</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:flex; gap:5px;">
                                    <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                    <select name="user_id">
                                        <option value="">-- kiddyから --</option>
                                        <?php foreach ($users as $u): ?>
                                            <option value="<?php echo $u['id']; ?>" <?php echo ($p['booked_by'] == $u['id']) ? 'selected' : ''; ?>>
                                                <?php echo $u['generation']; ?>代: <?php echo htmlspecialchars($u['name_kana']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="assign_court" style="padding:4px 8px;">保存</button>
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