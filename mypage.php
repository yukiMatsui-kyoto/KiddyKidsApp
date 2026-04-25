<?php
// エラーの原因を画面に表示させる設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name_kana'];

try {
    // 現在の表示名を取得（usersテーブルに display_name 列が必要）
    $stmt = $pdo->prepare("SELECT display_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_display_name = $stmt->fetchColumn();

    // 練習参加履歴を取得（practicesテーブルに is_published, is_cancelled 列が必要）
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
    // SQLエラーが起きた場合に原因を表示する
    die("<div style='padding:20px; background:#f8d7da; color:#721c24; border-radius:5px;'><strong>データベースエラー：</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>");
}

// 割り勘計算用の関数
function calculateMyFee($pdo, $practice_id, $my_status, $my_penalty, $facility_fee) {
    if ($my_status === '欠席' && $my_penalty == 0) return 0;

    $stmt = $pdo->prepare("SELECT status, is_penalty FROM practice_attendance WHERE practice_id = ?");
    $stmt->execute([$practice_id]);
    $attendees = $stmt->fetchAll();

    $total_weight = 0;
    foreach ($attendees as $att) {
        // ステータスを「参加」として判定
        if ($att['status'] === '参加' || $att['is_penalty'] == 1) $total_weight += 1.0;
        elseif ($att['status'] === '途中') $total_weight += 0.5;
    }

    if ($total_weight == 0) return 0;
    $unit_price = $facility_fee / $total_weight;
    $my_weight = ($my_status === '参加' || $my_penalty == 1) ? 1.0 : 0.5;
    return round($unit_price * $my_weight);
}

$total_confirmed_fee = 0; 
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"><title>マイページ - FreshTSystem</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="top-bar">
            <strong>マイページ</strong>
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
                foreach ($my_history as $record) {
                    if ($record['is_published'] == 1) {
                        $total_confirmed_fee += calculateMyFee($pdo, $record['practice_id'], $record['status'], $record['is_penalty'], $record['facility_fee']);
                    }
                }
                ?>
                <h3 style="margin: 0; color: #666;">現在確定の支払い合計</h3>
                <div style="font-size: 3em; font-weight: bold; color: #d35400; margin: 10px 0;">
                    ¥<?php echo number_format($total_confirmed_fee); ?>
                </div>
                <p style="margin: 0; color: #999; font-size: 0.9em;">※管理者が内容を確定させた分のみ表示されています</p>
            </div>

            <div class="main-card">
                <h3 style="margin-top:0;">練習参加履歴</h3>
                <table class="practice-table">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th>日付</th>
                            <th>コート</th>
                            <th>参加状況</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_history as $record): ?>
                        <tr>
                            <td><?php echo date('n/j', strtotime($record['practice_date'])); ?></td>
                            <td><?php echo htmlspecialchars($record['location']); ?></td>
                            <td>
                                <?php 
                                if ($record['is_penalty']) echo '<span style="color:#dc3545; font-weight:bold;">ペナルティ</span>';
                                else echo htmlspecialchars($record['status']); 
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($my_history)): ?>
                            <tr><td colspan="3" style="color:#999;">履歴はありません</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>