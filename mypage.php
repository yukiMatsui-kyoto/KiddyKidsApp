<?php
// mypage.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name_kana'];

// 自分の参加履歴（またはペナルティ欠席）を取得
$stmt = $pdo->prepare("
    SELECT p.id as practice_id, p.practice_date, p.location, p.facility_fee, a.status, a.is_penalty
    FROM practice_attendance a
    JOIN practices p ON a.practice_id = p.id
    WHERE a.user_id = :u_id AND p.is_cancelled = 0
    ORDER BY p.practice_date DESC
");
$stmt->execute([':u_id' => $user_id]);
$my_history = $stmt->fetchAll();

// ---  割り勘計算の専用関数 ---
function calculateMyFee($pdo, $practice_id, $my_status, $my_penalty, $facility_fee) {
    // 欠席（ペナルティなし）なら0円
    if ($my_status === '欠席' && $my_penalty == 0) return 0;

    // その日の全参加者のステータスを取得
    $stmt = $pdo->prepare("SELECT status, is_penalty FROM practice_attendance WHERE practice_id = ?");
    $stmt->execute([$practice_id]);
    $attendees = $stmt->fetchAll();

    $total_weight = 0;
    foreach ($attendees as $att) {
        // フル参加とペナルティ欠席は負担係数 1.0
        if ($att['status'] === 'フル' || $att['is_penalty'] == 1) {
            $total_weight += 1.0;
        } 
        // 途中参加は負担係数 0.5
        elseif ($att['status'] === '途中') {
            $total_weight += 0.5; 
        }
    }

    if ($total_weight == 0) return 0; // エラー回避（誰もいない場合）

    // 1係数あたりの基本額（フル参加者の支払額）
    $base_fee = $facility_fee / $total_weight;

    // 自分の係数
    $my_weight = ($my_status === 'フル' || $my_penalty == 1) ? 1.0 : 0.5;
    
    // 計算結果を四捨五入して返す
    return round($base_fee * $my_weight);
}

$total_unpaid = 0; // 未払い合計額
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>マイページ - 練習管理</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="top-container">
        <header class="top-header">
            <h2>マイページ</h2>
            <a href="top.php" class="btn-logout" style="background:#6c757d;">トップへ戻る</a>
        </header>

        <div class="main-card">
    <h3 style="margin-bottom: 20px;">👤 <?php echo htmlspecialchars($user_name); ?> さんのマイページ</h3>
    
    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ddd;">
        <h4 style="margin-top: 0;">表示名設定</h4>
        <p style="font-size: 0.9em; color: #666;">練習一覧に表示される名前を自由に変更できます。（未設定の場合はひらがな本名が表示されます）</p>
        
        <?php if (isset($_GET['updated'])): ?>
            <p style="color: #28a745; font-weight: bold;">表示名を更新しました！</p>
        <?php endif; ?>

        <form action="update_profile.php" method="POST" style="display: flex; gap: 10px; align-items: center;">
            <input type="text" name="display_name" value="<?php echo htmlspecialchars($current_display_name ?? ''); ?>" placeholder="例：なかがわゆうた→ぴー" style="padding: 8px; width: 60%; border: 1px solid #ccc; border-radius: 4px;">
            <button type="submit" style="background: #17a2b8; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">保存する</button>
        </form>
    </div>

                    <td style="padding: 10px;">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT u.name_kana, u.display_name, a.status 
                            FROM practice_attendance a 
                            JOIN users u ON a.user_id = u.id 
                            WHERE a.practice_id = ? AND a.status IN ('フル', '途中')
                        ");
                        $stmt->execute([$p['id']]);
                        $members = $stmt->fetchAll();
                        
                        if (empty($members)) {
                            echo '<span style="color:#999; font-size:0.8em;">まだ参加者はいません</span>';
                        } else {
                            foreach ($members as $m) {
                                $class = ($m['status'] === 'フル') ? 'full-tag' : 'half-tag';
                                $show_name = !empty($m['display_name']) ? $m['display_name'] : $m['name_kana'];
                                
                                echo '<span class="participant-tag ' . $class . '">' . htmlspecialchars($show_name) . '</span>';
                            }
                        }
                        ?>
                    </td>
            <h3 style="margin-bottom: 20px;">👤 <?php echo htmlspecialchars($user_name); ?> さんの支払い状況</h3>
            
            <table class="practice-table" style="width: 100%; border-collapse: collapse; text-align: center;">
                <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                    <th style="padding: 10px;">日付</th>
                    <th>参加状況</th>
                    <th>場所代(全体)</th>
                    <th>あなたの負担額</th>
                </tr>
                
                <?php foreach ($my_history as $record): ?>
                    <?php 
                    // 先ほど作った計算機で、この日の負担額を算出
                    $my_fee = calculateMyFee($pdo, $record['practice_id'], $record['status'], $record['is_penalty'], $record['facility_fee']);
                    $total_unpaid += $my_fee; // 合計に足していく
                    ?>
                    
                    <?php if ($my_fee > 0): // 1円でも支払う必要がある日だけ表示 ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 15px 0;"><?php echo date('n/j', strtotime($record['practice_date'])); ?></td>
                        <td>
                            <?php 
                            if ($record['is_penalty']) {
                                echo '<span style="color:#dc3545; font-weight:bold;">ペナルティ欠席</span>';
                            } else {
                                echo htmlspecialchars($record['status']); 
                            }
                            ?>
                        </td>
                        <td style="color: #666;">¥<?php echo number_format($record['facility_fee']); ?></td>
                        <td style="font-weight: bold; font-size: 1.1em; color: #d35400;">
                            ¥<?php echo number_format($my_fee); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>

            <div style="margin-top: 30px; padding: 20px; background: #e8f4f8; border-radius: 8px; text-align: right;">
                <h2 style="margin: 0; color: #0056b3;">現在の合計: ¥<?php echo number_format($total_unpaid); ?></h2>
                <p style="margin: 5px 0 0 0; font-size: 0.9em; color: #555;">※会計より期日を連絡後、支払いが完了したら会計担当に報告してください</p>
            </div>
        </div>
    </div>
</body>
</html>