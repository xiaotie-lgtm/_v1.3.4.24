<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$user = getCurrentUser();
$examId = $_GET['exam_id'] ?? 0;

$exam = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
$exam->execute([$examId, $_SESSION['user_id']]);
$exam = $exam->fetch();

if (!$exam) {
    header('Location: publish_score.php');
    exit;
}

$scores = $pdo->prepare("SELECT s.*, u.name, u.username 
                         FROM scores s 
                         INNER JOIN users u ON s.student_id = u.id 
                         WHERE s.exam_id = ? 
                         ORDER BY s.total_score DESC");
$scores->execute([$examId]);
$scores = $scores->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>成绩列表</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('publish_score', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>成绩列表 - <?php echo htmlspecialchars($exam['title']); ?></h1>
                    <p>查看该考试/作业的所有学生成绩，按总分从高到低排序。</p>
                    <div class="hero-actions">
                        <a href="publish_score.php" class="btn btn-secondary btn-small">返回</a>
                    </div>
                </div>
            </section>
            
            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>学号</th>
                                <th>姓名</th>
                                <th>自动批改得分</th>
                                <th>手动评分得分</th>
                                <th>总分</th>
                                <th>状态</th>
                                <th>提交时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($scores)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #94a3b8;">暂无成绩</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($scores as $index => $score): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($score['username']); ?></td>
                                        <td><?php echo htmlspecialchars($score['name']); ?></td>
                                        <td><?php echo $score['auto_score']; ?></td>
                                        <td><?php echo $score['manual_score']; ?></td>
                                        <td><strong><?php echo $score['total_score']; ?></strong></td>
                                        <td>
                                            <span class="tag status-<?php echo $score['status']; ?>">
                                                <?php 
                                                $statusMap = [
                                                    'submitted' => '已提交',
                                                    'graded' => '已批改',
                                                    'published' => '已发布'
                                                ];
                                                echo $statusMap[$score['status']];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo $score['submitted_at'] ? date('Y-m-d H:i', strtotime($score['submitted_at'])) : '-'; ?></td>
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

