<?php
session_start();
$_SESSION = []; // セッション（記憶）を空っぽにする
session_destroy(); // セッションを完全に破壊する
header('Location: login.php'); // ログイン画面に戻す
exit;
?>