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

// 获取老师管理的班级
$classes = $pdo->prepare("SELECT c.*, 
                          COUNT(DISTINCT u.id) as student_count
                          FROM classes c
                          INNER JOIN teacher_classes tc ON c.id = tc.class_id
                          LEFT JOIN users u ON u.class_id = c.id AND u.role = 'student'
                          WHERE tc.teacher_id = ?
                          GROUP BY c.id
                          ORDER BY c.name");
$classes->execute([$_SESSION['user_id']]);
$classes = $classes->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>班级管理</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('manage_classes', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>班级管理</h1>
                    <p>查看所负责班级的学生名单，支持快速删除异常账号并跳转创建新学生。</p>
                </div>
                <div>
                    <a href="create_student.php" class="btn btn-primary btn-small">创建学生账号</a>
                </div>
            </section>
            
            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($classes)): ?>
                <div class="card">
                    <div class="info-message">
                        <p>您还没有被分配到任何班级，请联系超级管理员为您分配班级。</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($classes as $class): ?>
                    <div class="card" style="margin-bottom: 20px;">
                        <div class="card-header">
                            <h2><?php echo htmlspecialchars($class['name']); ?>（<?php echo $class['student_count']; ?> 人）</h2>
                        </div>
                        
                        <?php if (!empty($class['description'])): ?>
                            <p><strong>班级描述：</strong><?php echo htmlspecialchars($class['description']); ?></p>
                        <?php endif; ?>
                        
                        <?php 
                        $students = $pdo->prepare("SELECT * FROM users WHERE class_id = ? AND role = 'student' ORDER BY name");
                        $students->execute([$class['id']]);
                        $students = $students->fetchAll();
                        ?>
                        
                        <div style="margin-top: 15px;">
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>用户名</th>
                                            <th>姓名</th>
                                            <th>创建时间</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($students)): ?>
                                            <tr>
                                                <td colspan="4" style="text-align: center; color: #94a3b8;">暂无学生</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($student['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
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
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>快速操作</h2>
                </div>
                <a href="create_student.php" class="btn btn-primary">创建新学生</a>
            </div>
        </main>
    </div>
</body>
</html>

