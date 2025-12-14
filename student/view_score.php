<?php
require_once '../config.php';
checkRole('student');

$pdo = getDB();
$scores = $pdo->prepare("SELECT s.*, e.title, e.type 
                         FROM scores s 
                         INNER JOIN exams e ON s.exam_id = e.id 
                         WHERE s.student_id = ? AND s.status = 'published'
                         ORDER BY s.published_at DESC");
$scores->execute([$_SESSION['user_id']]);
$scores = $scores->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查询成绩</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php require_once 'student_sidebar.php'; renderStudentSidebar('scores', getCurrentUser()); ?>
        <main class="app-content">
            <div class="card">
                <div class="section-title">
                    <h2>查询成绩</h2>
                </div>
                <?php if (empty($scores)): ?>
                    <p class="empty-state">暂无已发布的成绩</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>考试/作业</th>
                                    <th>类型</th>
                                    <th>自动批改得分</th>
                                    <th>手动评分得分</th>
                                    <th>总分</th>
                                    <th>发布时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scores as $score): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($score['title']); ?></td>
                                        <td><?php echo $score['type'] === 'exam' ? '考试' : '作业'; ?></td>
                                        <td><?php echo $score['auto_score']; ?></td>
                                        <td><?php echo $score['manual_score']; ?></td>
                                        <td><strong class="highlight-score"><?php echo $score['total_score']; ?></strong></td>
                                        <td><?php echo $score['published_at'] ? date('Y-m-d H:i', strtotime($score['published_at'])) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

