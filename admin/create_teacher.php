<?php
require_once '../config.php';
require_once 'admin_sidebar.php';
checkRole('admin');

$pdo = getDB();
$message = '';
$messageType = '';
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name = trim($_POST['name'] ?? '');
    
    if (empty($username) || empty($password) || empty($name)) {
        $message = '请填写所有必填项';
        $messageType = 'error';
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $message = '用户名已存在';
                $messageType = 'error';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role, created_by) VALUES (?, ?, ?, 'teacher', ?)");
                $stmt->execute([$username, $hash, $name, $_SESSION['user_id']]);
                
                $message = '老师账号创建成功！';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = '创建失败：' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建老师账号</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderAdminSidebar('create_teacher', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>创建老师账号</h1>
                    <p>为老师分配平台登录账号，方便其管理班级、发布考试与创建学生。</p>
                </div>
                <div>
                    <a href="teacher_list.php" class="btn btn-muted btn-small">查看老师列表</a>
                </div>
            </section>

            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="POST">
                    <div class="form-group">
                        <label>用户名：</label>
                        <input type="text" name="username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label>密码：</label>
                        <input type="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>姓名：</label>
                        <input type="text" name="name" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">创建账号</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

