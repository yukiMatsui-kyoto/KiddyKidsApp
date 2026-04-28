<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name_kana'];

try {
    $stmt = $pdo->prepare("SELECT display_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_display_name = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT p.id as practice_id, p.practice_date, p.location, p.facility_fee, p.is_published, a.status, a.is_penalty
        FROM practice_attendance a
        JOIN practices p ON a.practice_id = p.id
        WHERE a.user_id = :u_id AND p.practice_date <= CURDATE() AND p.is_cancelled = 0
        ORDER BY p.practice_date DESC
    ");
    $stmt->execute([':u_id' => $user_id]);
    $my_history = $stmt->fetchAll();
} catch (PDOException $e) {
    die("<div style='padding:20px; background:#f8d7da; color:#721c24;'><strong>データベースエラー：</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>");
}

function calculateMyFee($pdo, $practice_id, $my_status, $my_penalty, $facility_fee) {
    if ($my_status === '欠席' && $my_penalty == 0) return 0;

    $stmt = $pdo->prepare("SELECT status, is_penalty FROM practice_attendance WHERE practice_id = ?");
    $stmt->execute([$practice_id]);
    $attendees = $stmt->fetchAll();

    $total_weight = 0;
    foreach ($attendees as $att) {
        if ($att['status'] === '参加' || $att['status'] === 'ドタ参' || $att['is_penalty'] == 1) {
            $total_weight += 1.0;
        } elseif ($att['status'] === '途中' || $att['status'] === 'ドタ途中参') {
            $total_weight += 0.5;
        }
    }

    if ($total_weight == 0) return 0;
    $unit_price = $facility_fee / $total_weight;
    
    $my_weight = 0;
    if ($my_status === '参加' || $my_status === 'ドタ参' || $my_penalty == 1) $my_weight = 1.0;
    elseif ($my_status === '途中' || $my_status === 'ドタ途中参') $my_weight = 0.5;
    
    return round($unit_price * $my_weight);
}

$total_confirmed_fee = 0; 
$has_published = false; //公開された練習があるかどうかを判定するフラグ
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイページ - FreshTSystem</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="global-navbar">
            <div class="logo-area">
                <span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span>
                <img src="logo.png" alt="logo" onerror="this.style.display='none'">    
                <strong>マイページ</strong>
            </div>
        </header>
        <main class="content-body">
            <div class="main-card">
                <h3 style="margin-top:0;"><?php echo htmlspecialchars($user_name); ?> さんの表示名</h3>
                <form action="update_profile.php" method="POST" style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" name="display_name" value="<?php echo htmlspecialchars($current_display_name ?? ''); ?>" placeholder="表示名（ニックネーム）" style="padding: 8px; flex: 1; border: 1px solid #ccc; border-radius: 4px;">
                    <button type="submit" class="btn-submit">保存</button>
                </form>
            </div>

            <div class="main-card" style="text-align: center; background: #fff4f4; border: 2px solid #ffcccc;">
                <?php
                // 管理者が公開（is_published=1）にした分だけを合計する
                foreach ($my_history as $record) {
                    if ($record['is_published'] == 1) {
                        $has_published = true; // 1つでも公開されていれば true になる
                        $total_confirmed_fee += calculateMyFee($pdo, $record['practice_id'], $record['status'], $record['is_penalty'], $record['facility_fee']);
                    }
                }
                ?>
                <h3 style="margin: 0; color: #666;">現在確定の支払い合計</h3>
                <div style="font-size: 3em; font-weight: bold; color: #d35400; margin: 10px 0;">
                    <?php if ($has_published): ?>
                        ¥<?php echo number_format($total_confirmed_fee); ?>
                    <?php else: ?>
                        <span style="font-size: 0.6em; color: #999;">未公開</span>
                    <?php endif; ?>
                </div>
                <p style="margin: 0; color: #999; font-size: 0.9em;">※管理者が内容を確定させた分のみ表示されています</p>
            </div>

            <div class="main-card">
                <h3 style="margin-top:0;">練習参加履歴</h3>
                <table class="practice-table" style="display:block; overflow-x:auto; white-space:nowrap;">
                    <thead><tr style="background:#f8f9fa;"><th>日付</th><th>コート</th><th>参加状況</th></tr></thead>
                    <tbody>
                        <?php foreach ($my_history as $record): ?>
                        <tr>
                            <td><?php echo date('n/j', strtotime($record['practice_date'])); ?></td>
                            <td><?php echo htmlspecialchars($record['location']); ?></td>
                            <td>
                                <?php if ($record['is_penalty']) echo '<span style="color:#dc3545; font-weight:bold;">7日以内キャンセル料</span>'; else echo htmlspecialchars($record['status']); ?>
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