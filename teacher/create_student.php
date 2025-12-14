<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$message = '';
$messageType = '';
$user = getCurrentUser();

// 处理删除学生
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $studentId = intval($_POST['student_id']);
    
    try {
        // 验证学生是否属于老师管理的班级
        $student = $pdo->prepare("SELECT u.* FROM users u 
                                 INNER JOIN teacher_classes tc ON u.class_id = tc.class_id 
                                 WHERE u.id = ? AND tc.teacher_id = ? AND u.role = 'student'");
        $student->execute([$studentId, $_SESSION['user_id']]);
        $student = $student->fetch();
        
        if (!$student) {
            $message = '无权删除该学生';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$studentId]);
            $message = '删除成功！';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = '删除失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name = trim($_POST['name'] ?? '');
    
    if (empty($username) || empty($password) || empty($name)) {
        $message = '请填写所有必填项';
        $messageType = 'error';
    } else {
        try {
            // 检查用户名是否已存在
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $message = '用户名已存在';
                $messageType = 'error';
            } else {
                // 获取老师管理的第一个班级
                $class = $pdo->prepare("SELECT class_id FROM teacher_classes WHERE teacher_id = ? LIMIT 1");
                $class->execute([$_SESSION['user_id']]);
                $class = $class->fetch();
                $classId = $class ? $class['class_id'] : null;
                
                // 创建学生账号，自动分配到老师的班级
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role, teacher_id, class_id, created_by) VALUES (?, ?, ?, 'student', ?, ?, ?)");
                $stmt->execute([$username, $hash, $name, $_SESSION['user_id'], $classId, $_SESSION['user_id']]);
                
                $message = '学生账号创建成功！' . ($classId ? '已自动分配到班级。' : '（注意：您还没有被分配到任何班级，请联系管理员）');
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = '创建失败：' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// 获取老师管理的班级
$teacherClasses = $pdo->prepare("SELECT c.* FROM classes c 
                                INNER JOIN teacher_classes tc ON c.id = tc.class_id 
                                WHERE tc.teacher_id = ?");
$teacherClasses->execute([$_SESSION['user_id']]);
$teacherClasses = $teacherClasses->fetchAll();

// 获取本班学生列表（老师管理的所有班级的学生）
$classIds = array_column($teacherClasses, 'id');
if (empty($classIds)) {
    $students = [];
} else {
    $placeholders = implode(',', array_fill(0, count($classIds), '?'));
    $students = $pdo->prepare("SELECT u.*, c.name as class_name FROM users u 
                              LEFT JOIN classes c ON u.class_id = c.id
                              WHERE u.class_id IN ($placeholders) AND u.role = 'student' 
                              ORDER BY u.created_at DESC");
    $students->execute($classIds);
    $students = $students->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建学生账号</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('students', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>创建学生账号</h1>
                    <p>快速为班级新增学生，并在下方表格中管理现有账号。</p>
                </div>
                <div>
                    <a href="manage_classes.php" class="btn btn-muted btn-small">返回班级管理</a>
                </div>
            </section>
            
            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>创建新学生账号</h2>
                </div>
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
            
            <?php if (empty($teacherClasses)): ?>
                <div class="card">
                    <div class="info-message">
                        <p>⚠️ 您还没有被分配到任何班级，请联系超级管理员为您分配班级。</p>
                        <p>创建的学生账号将无法自动分配到班级。</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h2>我管理的班级</h2>
                    </div>
                    <ul>
                        <?php foreach ($teacherClasses as $tc): ?>
                            <li><?php echo htmlspecialchars($tc['name']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>本班学生列表（共 <?php echo count($students); ?> 人）</h2>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>用户名</th>
                                <th>姓名</th>
                                <th>班级</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #94a3b8;">暂无学生</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['class_name'] ?? '未分配'); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($student['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('确定要删除学生「<?php echo htmlspecialchars($student['name']); ?>」吗？\n\n此操作不可恢复！');">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <button type="submit" name="delete_student" class="btn btn-danger btn-small">删除</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

