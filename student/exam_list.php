<?php
require_once '../config.php';
checkRole('student');

$pdo = getDB();

// 获取学生所在的班级
$student = $pdo->prepare("SELECT class_id FROM users WHERE id = ?");
$student->execute([$_SESSION['user_id']]);
$student = $student->fetch();
$classId = $student['class_id'] ?? null;

// 如果学生有班级，只显示本班老师发布的考试/作业
if ($classId) {
    // 获取该班级的所有老师ID
    $teacherIds = $pdo->prepare("SELECT teacher_id FROM teacher_classes WHERE class_id = ?");
    $teacherIds->execute([$classId]);
    $teacherIds = $teacherIds->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($teacherIds)) {
        $exams = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
        $exams = $pdo->prepare("SELECT e.*, 
                              CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as has_submitted,
                              s.status as score_status,
                              e.allow_retake
                              FROM exams e 
                              LEFT JOIN scores s ON e.id = s.exam_id AND s.student_id = ?
                              WHERE e.status = 'published' 
                              AND e.teacher_id IN ($placeholders)
                              ORDER BY e.start_time DESC");
        $params = array_merge([$_SESSION['user_id']], $teacherIds);
        $exams->execute($params);
        $exams = $exams->fetchAll();
    }
} else {
    // 如果学生没有班级，不显示任何考试
    $exams = [];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>考试/作业列表</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php require_once 'student_sidebar.php'; renderStudentSidebar('exam_list', getCurrentUser()); ?>
        <main class="app-content">
            <div class="card">
                <div class="section-title">
                    <h2>考试/作业列表</h2>
                </div>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>标题</th>
                                <th>类型</th>
                                <th>开始时间</th>
                                <th>结束时间</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($exams)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">暂无考试/作业</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($exams as $exam): ?>
                                    <?php 
                                    $now = time();
                                    $startTime = strtotime($exam['start_time']);
                                    $endTime = strtotime($exam['end_time']);
                                    $canTake = ($now >= $startTime && $now <= $endTime);
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td><?php echo $exam['type'] === 'exam' ? '考试' : '作业'; ?></td>
                                        <td><?php echo date('Y-m-d H:i', $startTime); ?></td>
                                        <td><?php echo date('Y-m-d H:i', $endTime); ?></td>
                                        <td>
                                            <?php if ($exam['has_submitted']): ?>
                                                <span class="tag tag-submitted">已提交</span>
                                            <?php elseif ($canTake): ?>
                                                <span class="tag tag-published">进行中</span>
                                            <?php elseif ($now < $startTime): ?>
                                                <span class="tag tag-draft">未开始</span>
                                            <?php else: ?>
                                                <span class="tag tag-finished">已结束</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="table-actions">
                                            <?php if ($canTake && (!$exam['has_submitted'] || $exam['allow_retake'])): ?>
                                                <a href="take_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-small">
                                                    <?php echo $exam['has_submitted'] ? '再次答题' : '开始答题'; ?>
                                                </a>
                                            <?php elseif ($exam['has_submitted']): ?>
                                                <span class="muted-text">已提交<?php echo $exam['allow_retake'] ? '（可再次答题）' : ''; ?></span>
                                            <?php else: ?>
                                                <span class="muted-text">不可参加</span>
                                            <?php endif; ?>
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

