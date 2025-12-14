<?php
// 修复admin账号密码为 'admin' 的脚本
// 在浏览器中访问此文件可以修复admin账号的密码

require_once 'config.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        $pdo = getDB();
        
        // 生成密码哈希（密码：admin）
        $password = 'admin';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        // 更新admin账号的密码
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $stmt->execute([$hash]);
        
        // 如果admin账号不存在，则创建
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES ('admin', ?, '超级管理员', 'admin')");
            $stmt->execute([$hash]);
        }
        
        $message = "成功设置admin账号密码为 'admin'！现在可以使用 admin/admin 登录。";
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
    <title>修复Admin密码</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>修复Admin密码</h1>
            <p style="margin-bottom: 20px;">此工具将admin账号的密码设置为：<strong>admin</strong></p>
            
            <?php if ($message): ?>
                <div class="<?php echo $success ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
                <form method="POST">
                    <button type="submit" name="confirm" class="btn btn-primary" onclick="return confirm('确定要设置admin密码为 admin 吗？')">确认设置</button>
                </form>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">返回登录</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

