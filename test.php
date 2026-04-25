<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>① PHPは正常に動いています！</h2>";

// ここでdb.phpを読み込む
require_once 'db.php';

echo "<h2>② データベース接続も完璧です！</h2>";
?>