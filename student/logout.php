<?php
session_start();
session_destroy();
// 退出学生后回到学生登录页
header('Location: login.php');
exit;
?>
