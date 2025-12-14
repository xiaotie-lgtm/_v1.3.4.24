<?php
require_once '../config.php';
require_once 'admin_sidebar.php';
checkRole('admin');

$pdo = getDB();
$message = '';
$messageType = '';
$user = getCurrentUser();

// 处理创建班级
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_class'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name)) {
        $message = '请输入班级名称';
        $messageType = 'error';
    } else {
        try {
            // 检查并修复表结构
            $columns = $pdo->query("SHOW COLUMNS FROM classes")->fetchAll(PDO::FETCH_COLUMN);
            
            // 如果存在 teacher_id 字段，先删除外键和字段
            if (in_array('teacher_id', $columns)) {
                try {
                    // 尝试删除外键（可能不存在，忽略错误）
                    $pdo->exec("ALTER TABLE classes DROP FOREIGN KEY classes_ibfk_1");
                } catch (Exception $e) {
                    // 忽略外键不存在的错误
                }
                // 删除 teacher_id 字段
                $pdo->exec("ALTER TABLE classes DROP COLUMN teacher_id");
            }
            
            // 检查 description 字段是否存在
            if (!in_array('description', $columns)) {
                $pdo->exec("ALTER TABLE classes ADD COLUMN description text COMMENT '班级描述'");
            }
            
            // 创建班级
            $stmt = $pdo->prepare("INSERT INTO classes (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            
            $message = '班级创建成功！';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = '创建失败：' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// 处理删除班级
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
    $classId = intval($_POST['class_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$classId]);
        $message = '班级删除成功！';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = '删除失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 处理分配老师到班级
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_teacher'])) {
    $teacherId = intval($_POST['teacher_id']);
    $classId = intval($_POST['class_id']);
    
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

// 处理移除老师班级关联
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_teacher'])) {
    $teacherId = intval($_POST['teacher_id']);
    $classId = intval($_POST['class_id']);
    
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

// 获取所有班级
$classes = $pdo->query("SELECT c.*, 
                       COUNT(DISTINCT tc.teacher_id) as teacher_count,
                       COUNT(DISTINCT u.id) as student_count
                       FROM classes c
                       LEFT JOIN teacher_classes tc ON c.id = tc.class_id
                       LEFT JOIN users u ON u.class_id = c.id AND u.role = 'student'
                       GROUP BY c.id
                       ORDER BY c.created_at DESC")->fetchAll();

// 获取所有老师
$teachers = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY name")->fetchAll();
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
        <?php renderAdminSidebar('manage_classes', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>班级管理</h1>
                    <p>创建班级、查看老师与学生数量，并进入详情完成老师分配。</p>
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
                <div class="card-header">
                    <h2>创建新班级</h2>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label>班级名称：</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>班级描述：</label>
                        <textarea name="description"></textarea>
                    </div>
                    <button type="submit" name="create_class" class="btn btn-primary">创建班级</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>班级列表</h2>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>班级名称</th>
                                <th>描述</th>
                                <th>老师数</th>
                                <th>学生数</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($classes)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #94a3b8;">暂无班级</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($class['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($class['description'] ?? ''); ?></td>
                                        <td><?php echo $class['teacher_count']; ?></td>
                                        <td><?php echo $class['student_count']; ?></td>
                                        <td class="table-actions">
                                            <a href="class_detail.php?id=<?php echo $class['id']; ?>" class="btn btn-muted btn-small">详情</a>
                                            <form method="POST" onsubmit="return confirm('确定要删除班级「<?php echo htmlspecialchars($class['name']); ?>」吗？');">
                                                <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                <button type="submit" name="delete_class" class="btn btn-danger btn-small">删除</button>
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

