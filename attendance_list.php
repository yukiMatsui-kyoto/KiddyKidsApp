<?php
date_default_timezone_set('Asia/Tokyo');
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("SELECT *, DATEDIFF(practice_date, CURDATE()) as days_left FROM practices WHERE CONCAT(practice_date, ' ', end_time) > ? AND is_cancelled = 0 ORDER BY practice_date ASC");
$stmt->execute([$now]);
$practices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>参加登録 - KiddyKiddsFreshers</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
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
                        <th style="white-space: nowrap;">日程・時間</th>
                        <th style="white-space: nowrap;">コート</th>
                        <th style="white-space: nowrap;">出欠</th>
                        <th style="white-space: nowrap;">参加者</th>
                    </tr>
                    <?php foreach ($practices as $p): ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <strong><?php echo date('n/j', strtotime($p['practice_date'])); ?></strong><br>
                            <span style="font-size: 0.85em; color: #666;"><?php echo date('H:i', strtotime($p['start_time'])); ?> - <?php echo date('H:i', strtotime($p['end_time'])); ?></span>
                        </td>
                        <td style="white-space: nowrap;"><?php echo htmlspecialchars($p['location']); ?></td>
                        <td>
                            <?php
                            $stmt = $pdo->prepare("SELECT status FROM practice_attendance WHERE practice_id = ? AND user_id = ?");
                            $stmt->execute([$p['id'], $user_id]);
                            $my_status = $stmt->fetchColumn();

                            if ($my_status && $my_status !== '欠席'): 
                                $cancel_msg = ($p['days_left'] <= 7) ? "7日以内の練習のため、キャンセル料がかかる場合があります。本当にキャンセルしますか？" : "本当にキャンセルしますか？";
                            ?>
                                <form action="submit_attendance.php" method="POST" onsubmit="return confirm('<?php echo $cancel_msg; ?>');" style="margin:0;">
                                    <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="status" value="欠席">
                                    <button type="submit" class="btn-cancel" style="white-space: nowrap;">キャンセル</button>
                                </form>
                            <?php else: ?>
                                <form action="submit_attendance.php" method="POST" style="display:flex; gap:5px; margin:0;">
                                    <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                    <?php if ($p['days_left'] <= 7): ?>
                                        <button type="submit" name="status" value="ドタ参" style="background:#f39c12; color:white; border:none; padding:6px; border-radius:4px; white-space:nowrap;">ドタ参</button>
                                        <button type="submit" name="status" value="ドタ途中参" style="background:#cf2f3a; color:white; border:none; padding:6px; border-radius:4px; white-space:nowrap;">ドタ途中</button>
                                    <?php else: ?>
                                        <button type="submit" name="status" value="参加" class="btn-submit" style="padding:6px 12px; white-space:nowrap;">参加</button>
                                        <button type="submit" name="status" value="途中参" style="background:#17a2b8; color:white; border:none; padding:6px 12px; border-radius:4px; white-space:nowrap;">途中参</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: left; min-width: 150px; vertical-align: top;">
                            <?php
                            $s = $pdo->prepare("SELECT COALESCE(NULLIF(u.display_name, ''), u.name_kana) as show_name, a.status, u.generation, u.id as uid FROM practice_attendance a JOIN users u ON a.user_id = u.id WHERE a.practice_id = ? AND a.status != '欠席'");
                            $s->execute([$p['id']]);
                            $attendees = $s->fetchAll();
                            
                            // お手伝い（0代）を除外して参加人数をカウント
                            $total_attendees = 0;
                            foreach ($attendees as $att) {
                                if ($att['generation'] != 0) {
                                    $total_attendees++;
                                }
                            }

                            // 役割（運搬・仕切り）を取得
                            $r_stmt = $pdo->prepare("SELECT user_id, role_type FROM practice_roles WHERE practice_id = ?");
                            $r_stmt->execute([$p['id']]);
                            $role_map = [];
                            foreach($r_stmt->fetchAll() as $row){
                                $role_map[$row['user_id']][] = $row['role_type'];
                            }
                            ?>
                            <button type="button" onclick="toggleParticipants('part-<?php echo $p['id']; ?>')" style="background:#f0f2f5; color:#333; border:1px solid #ccc; padding:6px 12px; border-radius:20px; font-size:0.85em; cursor:pointer; font-weight:bold; white-space:nowrap;">
                                参加者 (<?php echo $total_attendees; ?>名) ▾
                            </button>
                            <div id="part-<?php echo $p['id']; ?>" style="display:none; margin-top:10px; background:#fdfdfd; padding:10px; border-radius:8px; border:1px solid #eee; white-space:normal;">
                                <?php
                                $groups = ['参加' => [], '途中参' => [], 'ドタ参' => [], 'ドタ途中参' => [], 'お手伝い' => []];
                                foreach ($attendees as $att) {
                                    $status_key = ($att['status'] === '途中' || $att['status'] === '途中参') ? '途中参' : $att['status'];
                                    $target_group = ($att['generation'] == 0) ? 'お手伝い' : $status_key;
                                    
                                    $name_disp = htmlspecialchars($att['show_name']);
                                    if (isset($role_map[$att['uid']])) {
                                        $name_disp .= " <span style='font-size:0.85em; color:#666; font-weight:normal;'>(" . implode('・', $role_map[$att['uid']]) . ")</span>";
                                    }

                                    if (isset($groups[$target_group])) {
                                        $groups[$target_group][] = $name_disp;
                                    }
                                }
                                $colors = ['参加' => '#4a86e8', '途中参' => '#17a2b8', 'ドタ参' => '#f39c12', 'ドタ途中参' => '#cf2f3a', 'お手伝い' => '#28a745'];
                                foreach ($groups as $label => $names) {
                                    if (!empty($names)) {
                                        echo "<div style='margin-bottom: 8px;'><strong style='color: {$colors[$label]}; font-size: 0.8em; display:block; border-bottom:1px solid #eee; padding-bottom:2px; margin-bottom:3px;'>【{$label}】</strong><span style='font-size: 0.85em; color: #444; line-height:1.4;'> " . implode(', ', $names) . "</span></div>";
                                    }
                                }
                                if (empty($attendees)) echo "<span style='color:#999; font-size:0.8em;'>まだ登録者がいません</span>";
                                ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </main>
    </div>
    <script>
    function toggleParticipants(id) {
        var el = document.getElementById(id);
        el.style.display = (el.style.display === "none") ? "block" : "none";
    }
    </script>
</body>
</html>