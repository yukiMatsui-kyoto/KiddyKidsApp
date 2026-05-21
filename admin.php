<?php
date_default_timezone_set('Asia/Tokyo');
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (!isset($_SESSION['is_admin'])) { header('Location: admin_login.php'); exit; }


$weeks = ['日', '月', '火', '水', '木', '金', '土'];

$stmt = $pdo->query("SELECT * FROM practices ORDER BY practice_date DESC, start_time DESC");
$all_practices = $stmt->fetchAll();

$upcoming = []; $past = [];
$now = date('Y-m-d H:i:s');
foreach ($all_practices as $p) {
    if (strtotime($p['practice_date'] . ' ' . $p['end_time']) >= strtotime($now)) { $upcoming[] = $p; } 
    else { $past[] = $p; }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="global-navbar"><div class="logo-area"><span class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active');">☰</span><strong>練習管理</strong></div></header>
        <main class="content-body">
            <div class="main-card">
                <h3 style="margin-top:0;">＋新しい練習を追加する</h3>
                <form action="add_practice.php" method="POST" enctype="multipart/form-data">
                    <p>日付</p><input type="date" name="practice_date" required style="padding:10px;">
                    <p>時間帯</p>
                    <div style="margin-bottom:10px;">
                        <input type="radio" name="time_preset" value="daytime" required onchange="toggleCustomInput()"> 16:00 - 18:00
                        <input type="radio" name="time_preset" value="night" onchange="toggleCustomInput()"> 18:00 - 21:00
                        <input type="radio" name="time_preset" value="custom" onchange="toggleCustomInput()"> その他
                    </div>
                    <div id="custom_time_area" style="display: none; padding: 10px; background: #f8f9fa;">
                        開始：<input type="time" name="custom_start_time" id="custom_start_time"> 終了：<input type="time" name="custom_end_time" id="custom_end_time">
                    </div>
                    <p>コート</p>
                    <div style="margin-bottom:10px;">
                        <input type="radio" name="location_preset" value="宝" required onchange="toggleCustomInput()"> 宝
                        <input type="radio" name="location_preset" value="岡崎" onchange="toggleCustomInput()"> 岡崎
                        <input type="radio" name="location_preset" value="custom" onchange="toggleCustomInput()"> その他
                    </div>
                    <div id="custom_location_area" style="display: none; padding: 10px; background: #f8f9fa;">
                        <input type="text" name="custom_location" id="custom_location" placeholder="会場名を入力">
                    </div>
                    
                    <p>コート代（円）</p>
                    <input type="number" name="facility_fee" value="5000" required style="padding:10px;">
                    
                    <p>使用許可証（任意）</p>
                    <input type="file" name="permit_file" accept=".jpg,.jpeg,.png,.pdf" style="padding:10px;"><br><br>
                    <button type="submit" class="btn-submit">予定を追加する</button>
                </form>
            </div>
            
            <div class="main-card">
                <h3>今後の練習一覧</h3>
                <table class="practice-table">
                    <tr><th>日付</th><th>会場</th><th>操作</th></tr>
                    <?php foreach ($upcoming as $p): 
                        $w_idx = date('w', strtotime($p['practice_date']));
                    ?>
                    <tr style="<?php if($p['is_cancelled']) echo 'background:#ffeeba;'; ?>">
                        <td><?php echo date('n/j', strtotime($p['practice_date'])) . '(' . $weeks[$w_idx] . ')'; ?></td>
                        <td><?php echo htmlspecialchars($p['location']); ?></td>
                        <td><a href="admin_roster.php?id=<?php echo $p['id']; ?>" class="btn-waive" style="text-decoration:none;">詳細・編集</a></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="main-card" style="background: #fdfdfd;">
                <h3>過去の練習の表示・編集</h3>
                <table class="practice-table">
                    <tr><th>日付</th><th>会場</th><th>操作</th></tr>
                    <?php foreach ($past as $p): 
                        $w_idx = date('w', strtotime($p['practice_date']));
                    ?>
                    <tr style="<?php if($p['is_cancelled']) echo 'background:#ffeeba;'; ?>">
                        <td style="color: #777;"><?php echo date('n/j', strtotime($p['practice_date'])) . '(' . $weeks[$w_idx] . ')'; ?></td>
                        <td style="color: #777;"><?php echo htmlspecialchars($p['location']); ?></td>
                        <td><a href="admin_roster.php?id=<?php echo $p['id']; ?>" class="btn-waive" style="text-decoration:none; background:#6c757d;">詳細・編集</a></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </main>
    </div>
    <script>
    function toggleCustomInput() {
        const t = document.querySelector('input[name="time_preset"]:checked');
        document.getElementById('custom_time_area').style.display = (t && t.value === 'custom') ? 'block' : 'none';
        const l = document.querySelector('input[name="location_preset"]:checked');
        document.getElementById('custom_location_area').style.display = (l && l.value === 'custom') ? 'block' : 'none';
    }
    </script>
</body>
</html>