<?php
// 修复密码的脚本
// 在浏览器中访问此文件可以修复所有用户的密码
// 访问地址：http://localhost/项目目录/fix_password.php

require_once 'config.php';

// 检查是否已登录，如果已登录则不允许访问
if (isset($_SESSION['user_id'])) {
    die('请先退出登录后再运行此脚本');
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        $pdo = getDB();
        
        // 生成新的密码哈希（密码：123456）
        $password = '123456';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        // 更新所有用户的密码
        $stmt = $pdo->prepare("UPDATE users SET password = ?");
        $stmt->execute([$hash]);
        
        $count = $stmt->rowCount();
        $message = "成功更新 {$count} 个用户的密码！现在可以使用 teacher1/123456 或 student1/123456 登录。";
        $success = true;
        
    } catch (Exception $e) {
        $message = "错误: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修复密码</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>修复用户密码</h1>
            <p style="margin-bottom: 20px;">此工具将把所有用户的密码重置为：<strong>123456</strong></p>
            
            <?php if ($message): ?>
                <div class="<?php echo $success ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
                <form method="POST">
                    <p style="color: #dc3545; margin-bottom: 20px;">
                        ⚠️ 警告：此操作将重置所有用户的密码为 123456
                    </p>
                    <button type="submit" name="confirm" class="btn btn-primary" onclick="return confirm('确定要重置所有用户密码吗？')">确认重置密码</button>
                </form>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">返回登录</a>
            <?php endif; ?>
            
            <div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 5px; font-size: 12px;">
                <p><strong>说明：</strong></p>
                <p>如果登录时提示密码错误，通常是因为：</p>
                <ol style="margin-left: 20px; margin-top: 10px;">
                    <li>数据库还没有导入数据（请先导入 database.sql）</li>
                    <li>数据库中的密码哈希值不正确</li>
                </ol>
                <p style="margin-top: 10px;">运行此脚本可以修复密码问题。</p>
            </div>
        </div>
    </div>
</body>
</html>
