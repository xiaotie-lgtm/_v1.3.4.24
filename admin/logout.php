<?php
session_start();
session_destroy();
// 退出管理员后回到管理员登录页
header('Location: login.php');
exit;
?>
