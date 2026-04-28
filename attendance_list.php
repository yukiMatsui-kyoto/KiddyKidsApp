<?php
date_default_timezone_set('Asia/Tokyo');
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$now = date('Y-m-d H:i:s');

// 終了時刻前の練習のみ表示
$stmt = $pdo->prepare("SELECT *, DATEDIFF(practice_date, CURDATE()) as days_left FROM practices WHERE CONCAT(practice_date, ' ', end_time) > ? AND is_cancelled = 0 ORDER BY practice_date ASC");
$stmt->execute([$now]);
$practices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>参加登録</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="global-navbar">
            <div class="logo-area">
                <span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span>
                <strong>練習参加登録</strong>
            </div>
        </header>
        <main class="content-body">
            <div class="main-card">
                <table class="practice-table" style="display:block; overflow-x:auto;">
                    <tr>
                        <th>日程</th>
                        <th>会場</th>
                        <th>出欠</th>
                        <th>参加者</th>
                    </tr>
                    <?php foreach ($practices as $p): ?>
                    <tr>
                        <td><?php echo date('n/j', strtotime($p['practice_date'])); ?></td>
                        <td><?php echo htmlspecialchars($p['location']); ?></td>
                        <td>
                            <?php
                            $stmt = $pdo->prepare("SELECT status FROM practice_attendance WHERE practice_id = ? AND user_id = ?");
                            $stmt->execute([$p['id'], $user_id]);
                            $my_status = $stmt->fetchColumn();

                            if ($my_status && $my_status !== '欠席'): ?>
                                <form action="submit_attendance.php" method="POST" onsubmit="return confirm('7日以内なら金額が発生します。よろしいですか？');">
                                    <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="status" value="欠席">
                                    <button type="submit" class="btn-cancel">キャンセル</button>
                                </form>
                            <?php else: ?>
                                <form action="submit_attendance.php" method="POST" style="display:flex; gap:5px;">
                                    <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                    <?php if ($p['days_left'] <= 7): ?>
                                        <button type="submit" name="status" value="ドタ参" style="background:#f39c12; color:white; border:none; padding:6px; border-radius:4px;">ドタ参</button>
                                        <button type="submit" name="status" value="ドタ途中参" style="background:#cf2f3a; color:white; border:none; padding:6px; border-radius:4px;">ドタ途中</button>
                                    <?php else: ?>
                                        <button type="submit" name="status" value="参加" class="btn-submit">参加</button>
                                        <button type="submit" name="status" value="途中参" style="background:#17a2b8; color:white; border:none; padding:6px; border-radius:4px;">途中参</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: left; min-width: 200px;">
                            <?php
                            // 参加者をステータス別に取得して分ける
                            $s = $pdo->prepare("SELECT u.name_kana, a.status FROM practice_attendance a JOIN users u ON a.user_id = u.id WHERE a.practice_id = ? AND a.status != '欠席'");
                            $s->execute([$p['id']]);
                            $attendees = $s->fetchAll();

                            $groups = ['参加' => [], '途中参' => [], 'ドタ参' => [], 'ドタ途中参' => []];
                            foreach ($attendees as $att) {
                                $status_key = ($att['status'] === '途中') ? '途中参' : $att['status'];
                                if (isset($groups[$status_key])) {
                                    $groups[$status_key][] = htmlspecialchars($att['name_kana']);
                                }
                            }

                            $colors = ['参加' => '#4a86e8', '途中参' => '#17a2b8', 'ドタ参' => '#f39c12', 'ドタ途中参' => '#cf2f3a'];
                            
                            foreach ($groups as $label => $names) {
                                if (!empty($names)) {
                                    echo "<div style='margin-bottom: 5px;'>";
                                    echo "<strong style='color: {$colors[$label]}; font-size: 0.8em;'>【{$label}】</strong>";
                                    echo "<span style='font-size: 0.85em; color: #333;'> " . implode(', ', $names) . "</span>";
                                    echo "</div>";
                                }
                            }
                            if (empty($attendees)) echo "<span style='color:#ccc; font-size:0.8em;'>まだいません</span>";
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </main>
    </div>
</body>
</html>