<?php
require_once '../config.php';
require_once 'admin_sidebar.php';
checkRole('admin');

$pdo = getDB();
$userId = $_GET['id'] ?? 0;

$returnUrl = 'user_list.php';
if (!empty($_GET['class_id'])) {
    $returnUrl .= '?class_id=' . urlencode($_GET['class_id']);
    if (!empty($_GET['keyword'])) {
        $returnUrl .= '&keyword=' . urlencode($_GET['keyword']);
    }
} elseif (!empty($_GET['keyword'])) {
    $returnUrl .= '?keyword=' . urlencode($_GET['keyword']);
}

$userToEdit = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userToEdit->execute([$userId]);
$userToEdit = $userToEdit->fetch();

if (!$userToEdit) {
    header('Location: ' . $returnUrl);
    exit;
}

$message = '';
$messageType = '';
$currentAdmin = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? $userToEdit['role'];
    $classId = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;
    
    if (empty($name) || empty($username)) {
        $message = '请填写所有必填项';
        $messageType = 'error';
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$username, $userId]);
            if ($check->fetch()) {
                $message = '用户名已被使用';
                $messageType = 'error';
            } else {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, name = ?, role = ?, class_id = ? WHERE id = ?");
                    $stmt->execute([$username, $hash, $name, $role, $classId, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, role = ?, class_id = ? WHERE id = ?");
                    $stmt->execute([$username, $name, $role, $classId, $userId]);
                }
                
                $redirectUrl = 'user_list.php';
                if (!empty($_POST['search_class_id'])) {
                    $redirectUrl .= '?class_id=' . urlencode($_POST['search_class_id']);
                    if (!empty($_POST['search_keyword'])) {
                        $redirectUrl .= '&keyword=' . urlencode($_POST['search_keyword']);
                    }
                } elseif (!empty($_POST['search_keyword'])) {
                    $redirectUrl .= '?keyword=' . urlencode($_POST['search_keyword']);
                }
                header("Location: " . $redirectUrl);
                exit;
            }
        } catch (Exception $e) {
            $message = '更新失败：' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$classes = $pdo->query("SELECT * FROM classes ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑用户</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderAdminSidebar('user_list', $currentAdmin); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>编辑用户</h1>
                    <p>更新账号基本信息及角色，必要时为学生调整班级归属。</p>
                </div>
                <div>
                    <a href="<?php echo $returnUrl; ?>" class="btn btn-muted btn-small">返回用户列表</a>
                </div>
            </section>

            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="POST">
                    <?php if (!empty($_GET['class_id'])): ?>
                        <input type="hidden" name="search_class_id" value="<?php echo htmlspecialchars($_GET['class_id']); ?>">
                    <?php endif; ?>
                    <?php if (!empty($_GET['keyword'])): ?>
                        <input type="hidden" name="search_keyword" value="<?php echo htmlspecialchars($_GET['keyword']); ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>用户名：</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($userToEdit['username']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>姓名：</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($userToEdit['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>密码（留空则不修改）：</label>
                        <input type="password" name="password" placeholder="留空则不修改密码">
                    </div>

                    <div class="form-group">
                        <label>角色：</label>
                        <select name="role" required>
                            <option value="admin" <?php echo $userToEdit['role'] === 'admin' ? 'selected' : ''; ?>>超级管理员</option>
                            <option value="teacher" <?php echo $userToEdit['role'] === 'teacher' ? 'selected' : ''; ?>>老师</option>
                            <option value="student" <?php echo $userToEdit['role'] === 'student' ? 'selected' : ''; ?>>学生</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>班级（仅学生生效）：</label>
                        <select name="class_id">
                            <option value="">未分配</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $userToEdit['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" name="update_user" class="btn btn-primary">保存修改</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

