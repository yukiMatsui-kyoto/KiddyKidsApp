<?php
// admin_roster.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// どの練習日を見るか（URLの ?id=〇 で指定。無ければ直近の練習）
$practice_id = $_GET['id'] ?? null;

if (!$practice_id) {
    // IDが指定されていない場合は直近の練習IDを取得
    $stmt = $pdo->query("SELECT id FROM practices WHERE practice_date >= CURDATE() AND is_cancelled = 0 ORDER BY practice_date ASC LIMIT 1");
    $practice_id = $stmt->fetchColumn();
}

// 指定した練習の詳細を取得
$stmt = $pdo->prepare("SELECT * FROM practices WHERE id = ?");
$stmt->execute([$practice_id]);
$practice = $stmt->fetch();

// --- 割り勘の計算ロジック（マイページと同じ） ---
$attendees = [];
$total_weight = 0;
$facility_fee = $practice ? $practice['facility_fee'] : 0;

if ($practice) {
    // この日の参加者全員（ペナルティ欠席含む）を取得
    $stmt = $pdo->prepare("
        SELECT u.name_kana, a.status, a.is_penalty 
        FROM practice_attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.practice_id = ? AND (a.status IN ('フル', '途中') OR a.is_penalty = 1)
    ");
    $stmt->execute([$practice_id]);
    $attendees = $stmt->fetchAll();

    foreach ($attendees as $att) {
        if ($att['status'] === 'フル' || $att['is_penalty'] == 1) {
            $total_weight += 1.0;
        } elseif ($att['status'] === '途中') {
            $total_weight += 0.5;
        }
    }
}

// 1係数あたりの基本額（0割エラー防止）
$base_fee = ($total_weight > 0) ? ($facility_fee / $total_weight) : 0;
$total_collected = 0; // 集金予定額の合計
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>集金名簿 - 管理画面</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="top-container">
        <header class="top-header">
            <h2>【会計用】集金・出欠名簿</h2>
            <a href="admin.php" class="btn-logout" style="background:#6c757d;">管理画面へ戻る</a>
        </header>

        <?php if ($practice): ?>
        <div class="admin-card">
            <h3><?php echo date('n月j日', strtotime($practice['practice_date'])); ?> の練習</h3>
            <p style="color: #666;">
                場所代: ¥<?php echo number_format($facility_fee); ?> / 
                総係数: <?php echo $total_weight; ?>
            </p>

            <table class="practice-table" style="width: 100%; text-align: center; margin-top: 20px;">
                <tr style="background: #007bff; color: white;">
                    <th style="padding: 10px;">名前</th>
                    <th>参加状況</th>
                    <th>集金額</th>
                </tr>
                
                <?php foreach ($attendees as $att): ?>
                    <?php 
                    // 負担額の計算
                    $weight = ($att['status'] === 'フル' || $att['is_penalty'] == 1) ? 1.0 : 0.5;
                    $fee = round($base_fee * $weight);
                    $total_collected += $fee;
                    ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 15px 0; font-weight: bold;"><?php echo htmlspecialchars($att['name_kana']); ?></td>
                        <td>
                            <?php 
                            if ($att['is_penalty']) {
                                echo '<span style="color:#dc3545; font-weight:bold;">ペナルティ欠席</span>';
                            } else {
                                echo htmlspecialchars($att['status']); 
                            }
                            ?>
                        </td>
                        <td style="font-size: 1.2em; color: #d35400; font-weight: bold;">
                            ¥<?php echo number_format($fee); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <div style="margin-top: 20px; padding: 15px; background: #e8f4f8; text-align: right; border-radius: 8px;">
                <p style="margin: 0;">集金予定額: <strong>¥<?php echo number_format($total_collected); ?></strong></p>
                <p style="margin: 5px 0 0 0; font-size: 0.8em; color: #888;">
                    （※四捨五入の都合上、場所代と数円の誤差が出ることがあります）
                </p>
            </div>
        </div>
        <?php else: ?>
            <p>対象の練習が見つかりません。</p>
        <?php endif; ?>
    </div>
</body>
</html>