<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// これからの練習予定をすべて取得（中止フラグも見る）
$stmt = $pdo->prepare("SELECT * FROM practices WHERE practice_date >= CURDATE() ORDER BY practice_date ASC");
$stmt->execute();
$practices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理画面 - 練習管理</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="top-container">
        <header class="top-header">
            <h2>管理画面</h2>
            <a href="top.php" class="btn-logout" style="background:#6c757d;">トップへ戻る</a>
        </header>

        <div class="admin-card">
            <h3> ＋新しい練習を追加する</h3>
            <form action="add_practice.php" method="POST" enctype="multipart/form-data">
                
                <p> 日付</p>
                <input type="date" name="practice_date" required style="padding:10px; font-size:1.1em;">

                <p>🕗 時間帯</p>
<div class="toggle-buttons">
    <label><input type="radio" name="time_preset" value="daytime" required onchange="toggleCustomInput()"><span>16:00 - 18:00</span></label>
    <label><input type="radio" name="time_preset" value="night" onchange="toggleCustomInput()"><span>18:00 - 21:00</span></label>
    <label><input type="radio" name="time_preset" value="custom" onchange="toggleCustomInput()"><span>その他</span></label>
</div>
<div id="custom_time_area" style="display: none; margin-bottom: 15px; background: #f8f9fa; padding: 10px; border-radius: 8px;">
    開始：<input type="time" name="custom_start_time" id="custom_start_time" style="padding: 5px;"> 〜 
    終了：<input type="time" name="custom_end_time" id="custom_end_time" style="padding: 5px;">
</div>

<p>コート</p>
<div class="toggle-buttons">
    <label><input type="radio" name="location_preset" value="宝" required onchange="toggleCustomInput()"><span>宝</span></label>
    <label><input type="radio" name="location_preset" value="岡崎" onchange="toggleCustomInput()"><span>岡崎</span></label>
    <label><input type="radio" name="location_preset" value="custom" onchange="toggleCustomInput()"><span>その他</span></label>
</div>
<div id="custom_location_area" style="display: none; margin-bottom: 15px; background: #f8f9fa; padding: 10px; border-radius: 8px;">
    <input type="text" name="custom_location" id="custom_location" placeholder="会場名を入力（例：向島）" style="padding: 8px; width: 90%;">
</div>

                <p>場所代（円）</p>
                <input type="number" name="facility_fee" value="5000" required style="padding:10px;">

                <p>📄 会場使用許可証（画像・PDF）※任意</p>
                <input type="file" name="permit_file" accept=".jpg,.jpeg,.png,.pdf" style="padding:10px;">

                <br><br>
                <button type="submit" class="btn-submit" style="background:#007bff;">予定を追加する</button>
            </form>
        </div>

        <div class="admin-card" style="margin-top:30px;">
            <h3>今後の練習予定一覧</h3>
            <table class="practice-table">
                <tr>
                    <th>日付</th>
                    <th>時間 / 会場</th>
                    <th>許可証</th>
                    <th>操作</th>
                </tr>
                <?php foreach ($practices as $p): ?>
                <tr style="<?php if($p['is_cancelled']) echo 'background:#ffeeba; color:#856404;'; ?>">
                    <td><?php echo date('n/j', strtotime($p['practice_date'])); ?></td>
                    <td>
                        <?php echo date('H:i', strtotime($p['start_time'])); ?> - <br>
                        <?php echo htmlspecialchars($p['location']); ?>
                    </td>
                    <td>
                        <?php if ($p['permit_path']): ?>
                            <a href="uploads/<?php echo htmlspecialchars($p['permit_path']); ?>" target="_blank">📄 見る</a>
                        <?php else: ?>
                            なし
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$p['is_cancelled']): ?>
                            <form action="cancel_practice.php" method="POST" onsubmit="return confirm('本当にこの日の練習を中止（雨天中止等）にしますか？\n※参加者の出欠も無効になります。');">
                                <input type="hidden" name="practice_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" style="background:#dc3545; color:white; padding:5px 10px; border:none; border-radius:3px; cursor:pointer;">中止にする</button>
                            </form>
                        <?php else: ?>
                            <strong>中止済</strong>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

<script>
function toggleCustomInput() {
    // --- 時間帯の制御 ---
    const timePreset = document.querySelector('input[name="time_preset"]:checked');
    const customTimeArea = document.getElementById('custom_time_area');
    if (timePreset && timePreset.value === 'custom') {
        customTimeArea.style.display = 'block';
        document.getElementById('custom_start_time').required = true;
        document.getElementById('custom_end_time').required = true;
    } else {
        customTimeArea.style.display = 'none';
        document.getElementById('custom_start_time').required = false;
        document.getElementById('custom_end_time').required = false;
    }

    // --- コートの制御 ---
    const locPreset = document.querySelector('input[name="location_preset"]:checked');
    const customLocArea = document.getElementById('custom_location_area');
    if (locPreset && locPreset.value === 'custom') {
        customLocArea.style.display = 'block';
        document.getElementById('custom_location').required = true;
    } else {
        customLocArea.style.display = 'none';
        document.getElementById('custom_location').required = false;
    }
}
</script>   
    
</body>
</html>