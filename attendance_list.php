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
    <title>練習参加登録・一覧</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* 追加デザイン */
        .btn-small {
            background: #007bff; color: white; border: none; 
            padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 0.9em;
        }
        .btn-half { background: #17a2b8; }
        .btn-cancel { background: #dc3545; color: white; border: none; border-radius: 4px; padding: 5px 10px; cursor: pointer; }
        
        .btn-info {
            background: #e9ecef; border: 1px solid #ced4da; color: #495057;
            padding: 5px 10px; border-radius: 5px; cursor: pointer; font-size: 0.9em;
            transition: 0.2s;
        }
        .btn-info:hover { background: #dde2e6; }
        
        .hidden-participant-list {
            display: none; margin-top: 10px; text-align: left; 
            background: #f8f9fa; padding: 10px; border-radius: 5px; border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="top-container">
        <header class="top-header">
            <h2>練習参加登録</h2>
            <a href="top.php" class="btn-logout" style="background:#6c757d;">トップへ戻る</a>
        </header>

        <div class="main-card">
            <table class="practice-table" style="width: 100%; border-collapse: collapse;">
                <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd; text-align: center;">
                    <th style="padding: 10px; width: 15%;">日程</th>
                    <th style="width: 25%;">コート</th>
                    <th style="width: 35%;">参加予定者</th>
                    <th style="width: 25%;">自分の出欠</th>
                </tr>
                
                <?php foreach ($practices as $p): ?>
                <tr style="border-bottom: 1px solid #eee; <?php if($p['is_cancelled']) echo 'background:#ffeeba; color:#856404;'; ?>">
                    
                    <td style="padding: 15px 5px; text-align: center;">
                        <strong><?php echo date('n/j', strtotime($p['practice_date'])); ?></strong><br>
                        <small style="color: #666;"><?php echo date('H:i', strtotime($p['start_time'])); ?>〜</small>
                    </td>
                    
                    <td style="text-align: center;">
                        <?php echo htmlspecialchars($p['location']); ?>
                    </td>

                    <td style="padding: 10px; text-align: center;">
                        <?php if ($p['is_cancelled']): ?>
                            <strong style="color: #856404;">中止</strong>
                        <?php else: ?>
                            <?php
                            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM practice_attendance WHERE practice_id = ? AND status IN ('フル', '途中')");
                            $count_stmt->execute([$p['id']]);
                            $p_count = $count_stmt->fetchColumn();
                            ?>
                            
                            <button type="button" class="btn-info" onclick="toggleParticipants(<?php echo $p['id']; ?>)">
                                参加予定者(<?php echo $p_count; ?>)
                            </button>

                            <div id="list-<?php echo $p['id']; ?>" class="hidden-participant-list">
                                <?php
                                $member_stmt = $pdo->prepare("
                                    SELECT COALESCE(u.display_name, u.name_kana) as show_name, a.status 
                                    FROM practice_attendance a 
                                    JOIN users u ON a.user_id = u.id 
                                    WHERE a.practice_id = ? AND a.status IN ('フル', '途中')
                                    ORDER BY a.status = 'フル' DESC
                                ");
                                $member_stmt->execute([$p['id']]);
                                $members = $member_stmt->fetchAll();
                                
                                if (empty($members)) {
                                    echo '<small style="color:#888;">まだ誰も登録していません</small>';
                                } else {
                                    foreach ($members as $m) {
                                        // フル参加は青丸、途中参加は水色三角で視覚的に区別
                                        $icon = ($m['status'] === 'フル') ? '<span style="color:#007bff;">●</span>' : '<span style="color:#17a2b8;">▲</span>';
                                        echo '<div style="font-size:0.9em; margin-bottom:3px;">' . $icon . ' ' . htmlspecialchars($m['show_name']) . '</div>';
                                    }
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td style="text-align: center;">
                        <?php if (!$p['is_cancelled']): ?>
                            <?php
                            $status_stmt = $pdo->prepare("SELECT status FROM practice_attendance WHERE practice_id = ? AND user_id = ?");
                            $status_stmt->execute([$p['id'], $user_id]);
                            $my_status = $status_stmt->fetchColumn();

                            if ($my_status && $my_status !== '欠席'): ?>
                                <span style="color:#28a745; font-weight:bold; font-size:0.9em;">✅ <?php echo $my_status; ?></span><br>
                                <form action="submit_attendance.php" method="POST" style="margin-top: 5px;" onsubmit="return confirm('本当にキャンセルしますか？\n※練習日まで8日を切っている場合、キャンセルしても場所代が請求されます。');">
                                    <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="status" value="欠席">
                                    <button type="submit" class="btn-cancel">取消</button>
                                </form>
                            <?php else: ?>
                                <form action="submit_attendance.php" method="POST" style="display:flex; justify-content:center; gap:5px;">
                                    <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" name="status" value="フル" class="btn-small">フル</button>
                                    <button type="submit" name="status" value="途中" class="btn-small btn-half">途中</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <script>
    function toggleParticipants(id) {
        const list = document.getElementById('list-' + id);
        if (list.style.display === 'none' || list.style.display === '') {
            list.style.display = 'block';
        } else {
            list.style.display = 'none';
        }
    }
    </script>
</body>
</html>