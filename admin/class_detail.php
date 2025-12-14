<?php
require_once '../config.php';
require_once 'admin_sidebar.php';
checkRole('admin');

$pdo = getDB();
$classId = $_GET['id'] ?? 0;

// 获取班级信息
$class = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$class->execute([$classId]);
$class = $class->fetch();

if (!$class) {
    header('Location: manage_classes.php');
    exit;
}

$message = '';
$messageType = '';
$user = getCurrentUser();

// 处理分配老师
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_teacher'])) {
    $teacherId = intval($_POST['teacher_id']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id) VALUES (?, ?)");
        $stmt->execute([$teacherId, $classId]);
        $message = '分配成功！';
        $messageType = 'success';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            $message = '该老师已经管理此班级';
        } else {
            $message = '分配失败：' . $e->getMessage();
        }
        $messageType = 'error';
    }
}

// 处理移除老师
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_teacher'])) {
    $teacherId = intval($_POST['teacher_id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = ? AND class_id = ?");
        $stmt->execute([$teacherId, $classId]);
        $message = '移除成功！';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = '移除失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 获取该班级的老师
$classTeachers = $pdo->prepare("SELECT u.* FROM users u 
                                INNER JOIN teacher_classes tc ON u.id = tc.teacher_id 
                                WHERE tc.class_id = ? 
                                ORDER BY u.name");
$classTeachers->execute([$classId]);
$classTeachers = $classTeachers->fetchAll();

// 获取该班级的学生
$classStudents = $pdo->prepare("SELECT * FROM users WHERE class_id = ? AND role = 'student' ORDER BY name");
$classStudents->execute([$classId]);
$classStudents = $classStudents->fetchAll();

// 获取所有老师（用于分配）
$allTeachers = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY name")->fetchAll();
$availableTeachers = array_filter($allTeachers, function($teacher) use ($classTeachers) {
    foreach ($classTeachers as $ct) {
        if ($ct['id'] == $teacher['id']) {
            return false;
        }
    }
    return true;
});
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>班级详情 - <?php echo htmlspecialchars($class['name']); ?></title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderAdminSidebar('manage_classes', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>班级详情 - <?php echo htmlspecialchars($class['name']); ?></h1>
                    <p>查看班级描述、分配老师并浏览当前学生列表，确保班级有序管理。</p>
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
                    <h2>班级信息</h2>
                </div>
                <p><strong>班级名称：</strong><?php echo htmlspecialchars($class['name']); ?></p>
                <p><strong>班级描述：</strong><?php echo htmlspecialchars($class['description'] ?? ''); ?></p>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>分配老师</h2>
                </div>
                <?php if (empty($availableTeachers)): ?>
                    <p>所有老师都已分配到该班级。</p>
                <?php else: ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>选择老师：</label>
                            <select name="teacher_id" required>
                                <option value="">请选择老师</option>
                                <?php foreach ($availableTeachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?> (<?php echo htmlspecialchars($teacher['username']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="assign_teacher" class="btn btn-primary">分配老师</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>班级老师（<?php echo count($classTeachers); ?> 人）</h2>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>姓名</th>
                                <th>用户名</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($classTeachers)): ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;color:#94a3b8;">暂无老师</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($classTeachers as $teacher): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                        <td class="table-actions">
                                            <form method="POST" onsubmit="return confirm('确定要移除「<?php echo htmlspecialchars($teacher['name']); ?>」吗？');">
                                                <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                <button type="submit" name="remove_teacher" class="btn btn-danger btn-small">移除</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>班级学生（<?php echo count($classStudents); ?> 人）</h2>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>姓名</th>
                                <th>用户名</th>
                                <th>创建时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($classStudents)): ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;color:#94a3b8;">暂无学生</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($classStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($student['created_at'])); ?></td>
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

