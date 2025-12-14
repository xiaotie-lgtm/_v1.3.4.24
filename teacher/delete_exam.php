<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$message = '';
$messageType = '';
$user = getCurrentUser();

// 处理删除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $examId = intval($_POST['delete_id']);
    
    try {
        // 验证考试是否属于当前老师
        $exam = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
        $exam->execute([$examId, $_SESSION['user_id']]);
        $exam = $exam->fetch();
        
        if (!$exam) {
            $message = '考试不存在或无权删除';
            $messageType = 'error';
        } else {
            // 由于外键设置了 CASCADE，删除考试会自动删除相关的题目、答案、成绩
            $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$examId, $_SESSION['user_id']]);
            
            $message = '删除成功！';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = '删除失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 获取所有考试/作业
$exams = $pdo->prepare("SELECT e.*, 
                      COUNT(DISTINCT q.id) as question_count,
                      COUNT(DISTINCT s.id) as student_count
                      FROM exams e 
                      LEFT JOIN questions q ON e.id = q.exam_id 
                      LEFT JOIN scores s ON e.id = s.exam_id
                      WHERE e.teacher_id = ?
                      GROUP BY e.id 
                      ORDER BY e.created_at DESC");
$exams->execute([$_SESSION['user_id']]);
$exams = $exams->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>删除考试/作业</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('delete', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>删除考试/作业</h1>
                    <p>彻底移除不再需要的考试记录，但请先确认学生数据是否已备份。</p>
                    <div class="hero-actions">
                        <a href="exam_list.php" class="btn btn-muted btn-small">返回考试列表</a>
                    </div>
                </div>
                <div>
                    <p>提示：删除操作会连带清空题目、学生作答与成绩数据，无法恢复。</p>
                </div>
            </section>
            
            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>⚠️ 注意事项</h2>
                </div>
                <p style="color: #dc2626; margin-bottom: 20px;">
                    删除考试/作业将同时删除所有相关的题目、学生答案和成绩记录，此操作不可恢复，请谨慎处理。
                </p>
            </div>
            
            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>标题</th>
                                <th>类型</th>
                                <th>题目数</th>
                                <th>参与学生数</th>
                                <th>开始时间</th>
                                <th>结束时间</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($exams)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #94a3b8;">暂无考试/作业</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($exams as $exam): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td><?php echo $exam['type'] === 'exam' ? '考试' : '作业'; ?></td>
                                        <td><?php echo $exam['question_count']; ?></td>
                                        <td><?php echo $exam['student_count']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($exam['start_time'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($exam['end_time'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $exam['status']; ?>">
                                                <?php 
                                                $statusMap = ['draft' => '草稿', 'published' => '已发布', 'finished' => '已结束'];
                                                echo $statusMap[$exam['status']];
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('确定要删除「<?php echo htmlspecialchars($exam['title']); ?>」吗？\n\n此操作将删除：\n- <?php echo $exam['question_count']; ?> 道题目\n- <?php echo $exam['student_count']; ?> 个学生的答案和成绩\n\n不可恢复！');">
                                                <input type="hidden" name="delete_id" value="<?php echo $exam['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-small">删除</button>
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

