<?php
require_once '../config.php';
require_once 'admin_sidebar.php';
checkRole('admin');

$user = getCurrentUser();
$pdo = getDB();

// 统计信息
$teacherCount = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'teacher'")->fetch()['cnt'];
$studentCount = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'student'")->fetch()['cnt'];
$examCount = $pdo->query("SELECT COUNT(*) as cnt FROM exams")->fetch()['cnt'];
$classCount = $pdo->query("SELECT COUNT(*) as cnt FROM classes")->fetch()['cnt'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>超级管理员 - 校园答题系统</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderAdminSidebar('dashboard', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>系统运营概览</h1>
                    <p>快速掌握老师、学生、班级与考试规模，集中发布账号与维护班级，让教学运作保持有序。</p>
                    <div class="hero-actions">
                        <a href="create_teacher.php" class="btn btn-primary btn-small">创建老师账号</a>
                        <a href="user_list.php" class="btn btn-muted btn-small">管理所有用户</a>
                    </div>
                </div>
                <div>
                    <p>建议定期检查老师的班级覆盖率和考试数量，确保每个班级都有老师负责。</p>
                </div>
            </section>

            <section class="stats-grid">
                <div class="stat-card">
                    <span>老师数量</span>
                    <strong><?php echo $teacherCount; ?></strong>
                </div>
                <div class="stat-card">
                    <span>学生数量</span>
                    <strong><?php echo $studentCount; ?></strong>
                </div>
                <div class="stat-card">
                    <span>考试/作业总数</span>
                    <strong><?php echo $examCount; ?></strong>
                </div>
                <div class="stat-card">
                    <span>班级数量</span>
                    <strong><?php echo $classCount; ?></strong>
                </div>
            </section>

            <div class="card">
                <div class="card-header">
                    <h2>常用操作</h2>
                </div>
                <div class="card-grid">
                    <div class="card-highlight">
                        <h3>老师账号管理</h3>
                        <p>新增老师、浏览列表、查看考试/学生覆盖，确保教学力量充足。</p>
                        <div class="card-actions">
                            <a href="create_teacher.php" class="btn btn-primary btn-small">创建老师</a>
                            <a href="teacher_list.php" class="btn btn-secondary btn-small">老师列表</a>
                        </div>
                    </div>
                    <div class="card-highlight">
                        <h3>班级调度</h3>
                        <p>创建班级并快速为其分配老师，查看班级详情与当前学生数。</p>
                        <div class="card-actions">
                            <a href="manage_classes.php" class="btn btn-primary btn-small">班级管理</a>
                        </div>
                    </div>
                    <div class="card-highlight">
                        <h3>全量用户管理</h3>
                        <p>按角色或班级筛选账号，更新班级归属或停用异常账号。</p>
                        <div class="card-actions">
                            <a href="user_list.php" class="btn btn-muted btn-small">进入用户管理</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

