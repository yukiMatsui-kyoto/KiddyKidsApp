<div class="admin-card">
    <h3>新しい練習を追加する</h3>
    <form action="add_practice.php" method="POST">
        
        <p>日付</p>
        <input type="date" name="practice_date" required>

        <p>時間帯（どちらかを選択）</p>
        <div class="toggle-buttons">
            <label>
                <input type="radio" name="time_preset" value="daytime" required>
                <span>13:00 - 17:00</span>
            </label>
            <label>
                <input type="radio" name="time_preset" value="night">
                <span>18:00 - 21:00</span>
            </label>
        </div>

        <p>会場（どちらかを選択）</p>
        <div class="toggle-buttons">
            <label>
                <input type="radio" name="location" value="宝" required>
                <span>宝</span>
            </label>
            <label>
                <input type="radio" name="location" value="岡崎">
                <span>岡崎</span>
            </label>
        </div>

        <p>場所代（円）</p>
        <input type="number" name="facility_fee" value="5000" required>

        <br><br>
        <button type="submit" class="btn-submit">予定を追加する</button>
    </form>
</div>