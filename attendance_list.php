<?php
// attendance_list.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// これからの練習予定を取得
$stmt = $pdo->prepare("SELECT * FROM practices WHERE practice_date >= CURDATE() ORDER BY practice_date ASC");
$stmt->execute();
$practices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>練習参加登録</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="top-container">
        <header class="top-header">
            <h2>練習参加登録</h2>
            <a href="top.php" class="btn-logout" style="background:#6c757d;">トップへ戻る</a>
        </header>

        <div class="main-card">
            <table class="practice-table" style="width: 100%; border-collapse: collapse; text-align: center;">
                <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                    <th style="padding: 10px;">日付</th>
                    <th>時間 / コート   
                    </th>
                    <th>出欠入力</th>
                </tr>
                
                <?php foreach ($practices as $p): ?>
                <tr style="border-bottom: 1px solid #eee; <?php if($p['is_cancelled']) echo 'background:#ffeeba; color:#856404;'; ?>">
                    <td style="padding: 15px 0;">
                        <?php echo date('n/j', strtotime($p['practice_date'])); ?>
                    </td>
                    <td>
                        <?php echo date('H:i', strtotime($p['start_time'])); ?><br>
                        <?php echo htmlspecialchars($p['location']); ?>
                    </td>
                    <td>
                        <?php if ($p['is_cancelled']): ?>
                            <strong>【雨天等で中止】</strong>
                        <?php else: ?>
                            <?php
                            // 自分の出欠状況をデータベースから確認
                            $stmt = $pdo->prepare("SELECT status FROM practice_attendance WHERE practice_id = ? AND user_id = ?");
                            $stmt->execute([$p['id'], $user_id]);
                            $my_status = $stmt->fetchColumn();
                            ?>

                            <?php if ($my_status && $my_status !== '欠席'): ?>
                                <span style="color:#28a745; font-weight:bold;"><?php echo $my_status; ?>参加</span><br>
                                <form action="submit_attendance.php" method="POST" style="margin-top: 5px;" onsubmit="return confirm('本当にキャンセルしますか？\n※練習日まで8日を切っている場合、キャンセルしても場所代が請求されます。');">
                                    <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="status" value="欠席">
                                    <button type="submit" style="background:#dc3545; color:white; border:none; padding:5px 10px; border-radius:3px; cursor:pointer;">キャンセル</button>
                                </form>
                            <?php else: ?>
                                <form action="submit_attendance.php" method="POST" style="display:flex; justify-content:center; gap:10px;">
                                    <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" name="status" value="フル" style="background:#007bff; color:white; border:none; padding:8px 15px; border-radius:5px; cursor:pointer;">フル</button>
                                    <button type="submit" name="status" value="途中" style="background:#17a2b8; color:white; border:none; padding:8px 15px; border-radius:5px; cursor:pointer;">途中</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>