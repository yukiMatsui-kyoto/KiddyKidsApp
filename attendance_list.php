<?php
// attendance_list.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM practices WHERE practice_date >= CURDATE() ORDER BY practice_date ASC");
$stmt->execute();
$practices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"><title>練習参加登録 - FreshTSystem</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .btn-small { background: #4a86e8; color: white; border: none; padding: 6px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-half { background: #17a2b8; }
        .btn-info { background: #f0f2f5; border: 1px solid #ddd; color: #555; padding: 5px 12px; border-radius: 20px; cursor: pointer; font-size: 0.85em; }
        
        /* リストの表示スタイル */
        .hidden-participant-list {
            display: none; margin-top: 10px; text-align: left; 
            background: #fdfdfd; padding: 12px; border-radius: 8px; border: 1px solid #e0e0e0;
            line-height: 1.6;
        }
        .status-group { margin-bottom: 5px; font-size: 0.9em; }
        .status-label { font-weight: bold; color: #4a86e8; margin-right: 5px; }
        .status-label.half { color: #17a2b8; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="top-bar"><strong>📅 練習参加登録</strong></header>
        <main class="content-body">
            <div class="main-card">
                <table class="practice-table">
                    <tr><th>日程</th><th>コート</th><th>参加予定者</th><th>自分の出欠</th></tr>
                    <?php foreach ($practices as $p): ?>
                    <tr style="<?php if($p['is_cancelled']) echo 'background:#fff9e6; color:#856404;'; ?>">
                        <td><strong><?php echo date('n/j', strtotime($p['practice_date'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['location']); ?></td>
                        <td>
                            <?php if (!$p['is_cancelled']): ?>
                                <?php
                                $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM practice_attendance WHERE practice_id = ? AND status IN ('参加', '途中')");
                                $count_stmt->execute([$p['id']]);
                                $p_count = $count_stmt->fetchColumn();
                                ?>
                                <button type="button" class="btn-info" onclick="toggleParticipants(<?php echo $p['id']; ?>)">
                                    👤 <?php echo $p_count; ?>名 詳細タップ
                                </button>
                                <div id="list-<?php echo $p['id']; ?>" class="hidden-participant-list">
                                    <?php
                                    $member_stmt = $pdo->prepare("SELECT COALESCE(u.display_name, u.name_kana) as show_name, a.status FROM practice_attendance a JOIN users u ON a.user_id = u.id WHERE a.practice_id = ? AND a.status IN ('参加', '途中')");
                                    $member_stmt->execute([$p['id']]);
                                    $members = $member_stmt->fetchAll();
                                    
                                    $full_names = []; $half_names = [];
                                    foreach ($members as $m) {
                                        if ($m['status'] === '参加') $full_names[] = htmlspecialchars($m['show_name']);
                                        else $half_names[] = htmlspecialchars($m['show_name']);
                                    }
                                    
                                    if (empty($members)) {
                                        echo '<small style="color:#999;">まだ登録がありません</small>';
                                    } else {
                                        if (!empty($full_names)) echo '<div class="status-group"><span class="status-label">【参加】</span>' . implode(', ', $full_names) . '</div>';
                                        if (!empty($half_names)) echo '<div class="status-group"><span class="status-label half">【途中】</span>' . implode(', ', $half_names) . '</div>';
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$p['is_cancelled']): ?>
                                <?php
                                $status_stmt = $pdo->prepare("SELECT status FROM practice_attendance WHERE practice_id = ? AND user_id = ?");
                                $status_stmt->execute([$p['id'], $user_id]);
                                $my_status = $status_stmt->fetchColumn();

                                if ($my_status && $my_status !== '欠席'): ?>
                                    <span style="color:#28a745; font-weight:bold;">✅ <?php echo $my_status; ?></span>
                                    <form action="submit_attendance.php" method="POST" style="margin-top:5px;">
                                        <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                        <input type="hidden" name="status" value="欠席">
                                        <button type="submit" class="btn-cancel" style="font-size:0.8em;">取消</button>
                                    </form>
                                <?php else: ?>
                                    <form action="submit_attendance.php" method="POST" style="display:flex; justify-content:center; gap:5px;">
                                        <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="status" value="参加" class="btn-small">参加</button>
                                        <button type="submit" name="status" value="途中" class="btn-small btn-half">途中</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </main>
    </div>
    <script>
    function toggleParticipants(id) {
        const list = document.getElementById('list-' + id);
        list.style.display = (list.style.display === 'none' || list.style.display === '') ? 'block' : 'none';
    }
    </script>
</body>
</html>