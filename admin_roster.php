<?php
// admin_roster.php
session_start();
require_once 'db.php';
if (!isset($_SESSION['is_admin'])) { header('Location: admin_login.php'); exit; }

// --- 一括公開処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_all'])) {
    $stmt = $pdo->prepare("UPDATE practices SET is_published = 1 WHERE practice_date <= CURDATE()");
    $stmt->execute();
    header("Location: admin_roster.php?published=1"); exit;
}

// 全ユーザーと今日までの全練習を取得
$users = $pdo->query("SELECT id, name_kana, generation FROM users ORDER BY generation DESC, name_kana ASC")->fetchAll();
$practices = $pdo->query("SELECT * FROM practices WHERE practice_date <= CURDATE() AND is_cancelled = 0")->fetchAll();

$user_totals = [];
foreach ($users as $u) { $user_totals[$u['id']] = 0; }

foreach ($practices as $p) {
    $stmt = $pdo->prepare("SELECT user_id, status, is_penalty FROM practice_attendance WHERE practice_id = ?");
    $stmt->execute([$p['id']]);
    $atts = $stmt->fetchAll();
    
    $total_weight = 0;
    $temp_weights = [];
    foreach ($atts as $a) {
        $w = 0;
        if ($a['status'] === '参加' || $a['is_penalty'] == 1) $w = 1.0;
        elseif ($a['status'] === '途中') $w = 0.5;
        $temp_weights[$a['user_id']] = $w;
        $total_weight += $w;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>全体会計名簿 - FreshTSystem</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="top-bar">
            <strong>合計会計名簿</strong>
        </header>

        <main class="content-body">
            <div class="main-card">
                <?php if(isset($_GET['published'])) echo '<p style="color:#28a745; font-weight:bold;">全員のマイページに金額を一括公開しました！</p>'; ?>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h3 style="margin:0;">今日までの全練習の合計</h3>
                        <p style="color: #666; font-size:0.9em; margin-top:5px;">※管理者は未公開分も含めた全額が表示されています</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="export_roster.php" class="btn-submit" style="background: #28a745; text-decoration: none; padding: 10px 20px;">CSV出力</a>
                        <form method="POST">
                            <button type="submit" name="publish_all" class="btn-publish" style="padding: 10px 20px;" onclick="return confirm('合計金額をマイページに一括公開しますか？')">
                                金額を全員に一括公開(＊フレ団終了後!!)
                            </button>
                        </form>
                    </div>
                </div>

                <table class="practice-table">
                    <thead>
                        <tr style="background: #4a86e8; color: white;">
                            <th>代</th>
                            <th>名前</th>
                            <th>合計金額</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['generation']); ?></td>
                            <td><strong><?php echo htmlspecialchars($u['name_kana']); ?></strong></td>
                            <td style="font-size: 1.2em; font-weight: bold; <?php if($user_totals[$u['id']] == 0) echo 'color:#ccc;'; else echo 'color:#d35400;'; ?>">
                                ¥<?php echo number_format($user_totals[$u['id']]); ?>
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