<?php
date_default_timezone_set('Asia/Tokyo');
session_start();
require_once 'db.php';
if (!isset($_SESSION['is_admin'])) { header('Location: admin_login.php'); exit; }

// 独立した会計用テーブル
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS global_expenses (id INT AUTO_INCREMENT PRIMARY KEY, expense_type VARCHAR(20), amount INT, description VARCHAR(255), expense_date DATE) DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS global_expense_payers (expense_id INT, user_id INT, PRIMARY KEY(expense_id, user_id)) DEFAULT CHARSET=utf8mb4");
    $pdo->exec("ALTER TABLE global_expenses CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
} catch (PDOException $e) {}

$edit_id = $_GET['id'] ?? null;

// --- データの保存・更新・削除 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_expense'])) {
        $pdo->prepare("INSERT INTO global_expenses (expense_type, amount, description, expense_date) VALUES (?, ?, ?, ?)")
            ->execute([$_POST['expense_type'], $_POST['amount'], $_POST['description'], $_POST['expense_date']]);
        header("Location: admin_expenses.php"); exit;
    }
    if (isset($_POST['delete_expense'])) {
        $pdo->prepare("DELETE FROM global_expenses WHERE id = ?")->execute([$_POST['delete_id']]);
        $pdo->prepare("DELETE FROM global_expense_payers WHERE expense_id = ?")->execute([$_POST['delete_id']]);
        header("Location: admin_expenses.php"); exit;
    }
    if (isset($_POST['update_payers'])) {
        // ★ バグ修正：確実にIDを受け取って保存する
        $target_id = $_POST['expense_id'];
        $pdo->prepare("DELETE FROM global_expense_payers WHERE expense_id = ?")->execute([$target_id]);
        if (isset($_POST['payers'])) {
            foreach ($_POST['payers'] as $uid) {
                $pdo->prepare("INSERT INTO global_expense_payers VALUES (?, ?)")->execute([$target_id, $uid]);
            }
        }
        header("Location: admin_expenses.php?id=$target_id"); exit;
    }
}

$expenses = $pdo->query("SELECT * FROM global_expenses ORDER BY expense_date DESC")->fetchAll();
$all_users = $pdo->query("SELECT id, name_kana, generation FROM users ORDER BY generation DESC, name_kana ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ボール代・雑費管理</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="global-navbar"><div class="logo-area"><span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span><strong>ボール代・雑費管理</strong></div></header>
        <main class="content-body">
            
            <?php if ($edit_id): 
                $exp = $pdo->prepare("SELECT * FROM global_expenses WHERE id = ?"); $exp->execute([$edit_id]); $expense = $exp->fetch();
                $payers = $pdo->prepare("SELECT user_id FROM global_expense_payers WHERE expense_id = ?"); $payers->execute([$edit_id]); $payer_ids = $payers->fetchAll(PDO::FETCH_COLUMN);
            ?>
            <div class="main-card">
                <h3>「<?php echo htmlspecialchars($expense['description']); ?>」の負担者設定</h3>
                <p>金額: ¥<?php echo number_format($expense['amount']); ?></p>
                <form method="POST" action="admin_expenses.php">
                    <input type="hidden" name="expense_id" value="<?php echo htmlspecialchars($edit_id); ?>">
                    <table class="practice-table">
                        <tr><th>名前</th><th>負担する</th></tr>
                        <?php foreach ($all_users as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['name_kana']); ?> <?php if($u['generation']==0) echo '<span style="color:#28a745; font-size:0.8em;">(OB)</span>'; ?></td>
                            <td><input type="checkbox" name="payers[]" value="<?php echo $u['id']; ?>" <?php if(in_array($u['id'], $payer_ids)) echo 'checked'; ?>></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <br><button type="submit" name="update_payers" class="btn-submit">負担者を保存</button>
                    <a href="admin_expenses.php" style="margin-left:15px; text-decoration:none; color:var(--primary-color); font-weight:bold;">← 戻る</a>
                </form>
            </div>

            <?php else: ?>
            <div class="main-card">
                <h3 style="margin-top:0;">＋新しい支出を追加する</h3>
                <form method="POST" action="admin_expenses.php">
                    <input type="hidden" name="add_expense" value="1">
                    <p>種類</p>
                    <select name="expense_type" style="padding:10px; margin-bottom:10px;">
                        <option value="ball">ボール代（※全員の総練習時間で自動案分）</option>
                        <option value="misc">雑費・車代など（※後から負担者を個別に設定）</option>
                    </select>
                    <p>日付</p>
                    <input type="date" name="expense_date" required style="padding:10px; margin-bottom:10px;">
                    <p>金額（円）</p>
                    <input type="number" name="amount" required style="padding:10px; margin-bottom:10px;">
                    <p>メモ（何のお金か）</p>
                    <input type="text" name="description" required placeholder="例：車代、ボール購入" style="padding:10px; width:80%; margin-bottom:10px;"><br><br>
                    <button type="submit" class="btn-submit">追加する</button>
                </form>
            </div>

            <div class="main-card">
                <h3>支出履歴</h3>
                <table class="practice-table" style="display:block; overflow-x:auto; white-space:nowrap;">
                    <tr><th>日付</th><th>内容</th><th>種類</th><th>金額</th><th>操作</th></tr>
                    <?php foreach ($expenses as $e): ?>
                    <tr>
                        <td><?php echo date('n/j', strtotime($e['expense_date'])); ?></td>
                        <td><?php echo htmlspecialchars($e['description']); ?></td>
                        <td><?php echo ($e['expense_type'] === 'ball') ? '<span style="color:#28a745; font-weight:bold;">ボール代</span>' : '<span style="color:#17a2b8; font-weight:bold;">雑費</span>'; ?></td>
                        <td>¥<?php echo number_format($e['amount']); ?></td>
                        <td>
                            <?php if ($e['expense_type'] === 'misc'): ?>
                                <a href="admin_expenses.php?id=<?php echo $e['id']; ?>" class="btn-waive" style="text-decoration:none; margin-right:5px;">負担者設定</a>
                            <?php endif; ?>
                            <form method="POST" action="admin_expenses.php" style="display:inline;" onsubmit="return confirm('削除しますか？');">
                                <input type="hidden" name="delete_id" value="<?php echo $e['id']; ?>">
                                <button type="submit" name="delete_expense" class="btn-cancel">削除</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>