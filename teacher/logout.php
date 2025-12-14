<?php
session_start();
session_destroy();
// 退出老师后回到老师登录页
header('Location: login.php');
exit;
?>
