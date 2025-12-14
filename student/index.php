<?php
require_once '../config.php';
checkRole('student');

$user = getCurrentUser();
$pdo = getDB();

// 获取学生所在的班级
$student = $pdo->prepare("SELECT class_id FROM users WHERE id = ?");
$student->execute([$_SESSION['user_id']]);
$student = $student->fetch();
$classId = $student['class_id'] ?? null;

// 获取可参加的考试数量（排除已提交的，除非允许再次考试）
if ($classId) {
    // 获取该班级的所有老师ID
    $teacherIds = $pdo->prepare("SELECT teacher_id FROM teacher_classes WHERE class_id = ?");
    $teacherIds->execute([$classId]);
    $teacherIds = $teacherIds->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($teacherIds)) {
        $availableExams = 0;
    } else {
        $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
        $availableExams = $pdo->prepare("SELECT COUNT(DISTINCT e.id) as cnt 
                                          FROM exams e 
                                          LEFT JOIN scores s ON e.id = s.exam_id AND s.student_id = ?
                                          WHERE e.status = 'published' 
                                          AND e.teacher_id IN ($placeholders)
                                          AND e.start_time <= NOW() 
                                          AND e.end_time >= NOW()
                                          AND (s.id IS NULL OR e.allow_retake = 1)");
        $params = array_merge([$_SESSION['user_id']], $teacherIds);
        $availableExams->execute($params);
        $availableExams = $availableExams->fetch()['cnt'];
    }
} else {
    $availableExams = 0;
}

// 获取已完成的考试数量
$completedExams = $pdo->prepare("SELECT COUNT(DISTINCT exam_id) as cnt FROM answers WHERE student_id = ?");
$completedExams->execute([$_SESSION['user_id']]);
$completedExams = $completedExams->fetch()['cnt'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生端 - 校园答题系统</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php require_once 'student_sidebar.php'; renderStudentSidebar('dashboard', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>您好，<?php echo htmlspecialchars($user['name']); ?> 👋</h1>
                    <p>欢迎来到学生端，这里可以查看可参加的考试和历史成绩。</p>
                </div>
                <div>
                    <div class="hero-actions">
                        <a href="exam_list.php" class="btn btn-primary btn-small">考试/作业列表</a>
                        <a href="view_score.php" class="btn btn-muted btn-small">查询成绩</a>
                    </div>
                </div>
            </section>

            <section class="stats-grid">
                <div class="stat-card">
                    <span>可参加考试</span>
                    <strong><?php echo $availableExams; ?></strong>
                </div>
                <div class="stat-card">
                    <span>已完成</span>
                    <strong><?php echo $completedExams; ?></strong>
                </div>
            </section>

            <section class="card-grid">
                <div class="card-highlight">
                    <h3>考试说明</h3>
                    <p>查看考试时间与要求，按时参加考试，祝你发挥出色。</p>
                </div>
                <div class="card-highlight">
                    <h3>成绩查询</h3>
                    <p>成绩发布后可在“查询成绩”查看详细得分。</p>
                </div>
            </section>
        </main>
    </div>
</body>
</html>

