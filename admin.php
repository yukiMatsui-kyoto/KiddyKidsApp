<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (!isset($_SESSION['is_admin'])) { header('Location: admin_login.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM practices WHERE practice_date >= CURDATE() ORDER BY practice_date ASC");
$stmt->execute();
$practices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"><title>管理画面 - FreshTSystem</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="top-bar">
            <strong>⚙️ 管理画面</strong>
        </header>

        <main class="content-body">
            <div class="main-card">
                <h3 style="margin-top:0;">＋新しい練習を追加する</h3>
                <form action="add_practice.php" method="POST" enctype="multipart/form-data">
                    <p>日付</p>
                    <input type="date" name="practice_date" required style="padding:10px;">
                    <p>時間帯</p>
                    <div style="margin-bottom:10px;"><input type="radio" name="time_preset" value="daytime" required onchange="toggleCustomInput()"> 16:00 - 18:00 <input type="radio" name="time_preset" value="night" onchange="toggleCustomInput()"> 18:00 - 21:00 <input type="radio" name="time_preset" value="custom" onchange="toggleCustomInput()"> その他</div>
                    <div id="custom_time_area" style="display: none; padding: 10px; background: #f8f9fa;">開始：<input type="time" name="custom_start_time" id="custom_start_time"> 終了：<input type="time" name="custom_end_time" id="custom_end_time"></div>
                    
                    <p>コート</p>
                    <div style="margin-bottom:10px;"><input type="radio" name="location_preset" value="宝" required onchange="toggleCustomInput()"> 宝 <input type="radio" name="location_preset" value="岡崎" onchange="toggleCustomInput()"> 岡崎 <input type="radio" name="location_preset" value="custom" onchange="toggleCustomInput()"> その他</div>
                    <div id="custom_location_area" style="display: none; padding: 10px; background: #f8f9fa;"><input type="text" name="custom_location" id="custom_location" placeholder="例：向島" style="padding: 5px;"></div>
                    
                    <p>場所代（円）</p>
                    <input type="number" name="facility_fee" value="5000" required style="padding:10px;">
                    <p>会場使用許可証（任意）</p>
                    <input type="file" name="permit_file" accept=".jpg,.jpeg,.png,.pdf" style="padding:10px;">
                    <br><br>
                    <button type="submit" class="btn-submit">予定を追加する</button>
                </form>
            </div>

            <div class="main-card">
                <h3 style="margin-top:0;">今後の練習予定一覧</h3>
                <table class="practice-table">
    <tr>
        <th>日付</th>
        <th>時間 / 会場</th>
        <th>名簿</th> <th>操作</th>
        </tr>
    <?php foreach ($practices as $p): ?>
    <tr style="<?php if($p['is_cancelled']) echo 'background:#ffeeba; color:#856404;'; ?>">
        <td><?php echo date('n/j', strtotime($p['practice_date'])); ?></td>
        <td>
            <?php echo date('H:i', strtotime($p['start_time'])); ?> - <br>
            <?php echo htmlspecialchars($p['location']); ?>
        </td>
        <td>
            <?php if (!$p['is_cancelled']): ?>
                <a href="admin_roster.php?id=<?php echo $p['id']; ?>" style="text-decoration:none; color:#4a86e8; font-weight:bold;">📊 名簿表示</a>
            <?php else: ?>
                -
            <?php endif; ?>
        </td>
        <td>
            </td>
    </tr>
    <?php endforeach; ?>
</table>
            </div>
        </main>
    </div>

    <script>
    function toggleCustomInput() {
        const timePreset = document.querySelector('input[name="time_preset"]:checked');
        const customTimeArea = document.getElementById('custom_time_area');
        if (timePreset && timePreset.value === 'custom') { customTimeArea.style.display = 'block'; document.getElementById('custom_start_time').required = true; document.getElementById('custom_end_time').required = true; } 
        else { customTimeArea.style.display = 'none'; document.getElementById('custom_start_time').required = false; document.getElementById('custom_end_time').required = false; }
        
        const locPreset = document.querySelector('input[name="location_preset"]:checked');
        const customLocArea = document.getElementById('custom_location_area');
        if (locPreset && locPreset.value === 'custom') { customLocArea.style.display = 'block'; document.getElementById('custom_location').required = true; } 
        else { customLocArea.style.display = 'none'; document.getElementById('custom_location').required = false; }
    }
    </script>   
</body>
</html>