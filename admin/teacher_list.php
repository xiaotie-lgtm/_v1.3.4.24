<?php
require_once '../config.php';
require_once 'admin_sidebar.php';
checkRole('admin');

$pdo = getDB();
$user = getCurrentUser();
// 获取所有老师
$teachers = $pdo->query("SELECT u.*, 
                        (SELECT COUNT(*) FROM exams WHERE teacher_id = u.id) as exam_count,
                        (SELECT COUNT(*) FROM users WHERE teacher_id = u.id) as student_count
                        FROM users u 
                        WHERE u.role = 'teacher' 
                        ORDER BY u.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>老师列表</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderAdminSidebar('teacher_list', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>老师列表</h1>
                    <p>查看老师账号的考试发布与学生管理规模，及时了解教学负责情况。</p>
                </div>
                <div>
                    <a href="create_teacher.php" class="btn btn-primary btn-small">创建老师账号</a>
                </div>
            </section>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>用户名</th>
                                <th>姓名</th>
                                <th>创建的考试数</th>
                                <th>管理的学生数</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($teachers)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;color:#94a3b8;">暂无老师</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                                        <td><?php echo $teacher['exam_count']; ?></td>
                                        <td><?php echo $teacher['student_count']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($teacher['created_at'])); ?></td>
                                        <td>
                                            <a href="edit_user.php?id=<?php echo $teacher['id']; ?>" class="btn btn-muted btn-small">编辑</a>
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

