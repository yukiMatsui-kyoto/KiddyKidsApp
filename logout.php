<?php
session_start();

// セッション（記憶）を空っぽにする
$_SESSION = []; 

// セッションを完全に破壊する
session_destroy(); 

// ★追加：自動ログイン用のクッキーも削除（有効期限を過去にする）
if (isset($_COOKIE['kiddy_auto_login'])) {
    setcookie('kiddy_auto_login', '', time() - 3600, '/');
}

// ログイン画面に戻す
header('Location: login.php'); 
exit;
?>