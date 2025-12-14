<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$message = '';
$messageType = '';
$user = getCurrentUser();

// 处理再次发布
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['republish_id'])) {
    $examId = intval($_POST['republish_id']);
    
    try {
        // 验证考试是否属于当前老师
        $exam = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
        $exam->execute([$examId, $_SESSION['user_id']]);
        $exam = $exam->fetch();
        
        if (!$exam) {
            $message = '考试不存在或无权操作';
            $messageType = 'error';
        } else {
            // 更新状态为已发布，并允许再次考试
            $stmt = $pdo->prepare("UPDATE exams SET status = 'published', allow_retake = 1 WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$examId, $_SESSION['user_id']]);
            
            $message = '再次发布成功！学生可以再次参加考试。';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = '发布失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

$exams = $pdo->query("SELECT e.*, COUNT(q.id) as question_count 
                      FROM exams e 
                      LEFT JOIN questions q ON e.id = q.exam_id 
                      WHERE e.teacher_id = " . $_SESSION['user_id'] . "
                      GROUP BY e.id 
                      ORDER BY e.created_at DESC")->fetchAll();
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
        <?php renderTeacherSidebar('exam_list', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>考试/作业列表</h1>
                    <p>查看所有已创建的考试与作业，快速跳转到详情、编辑、批改和再次发布。</p>
                    <div class="hero-actions">
                        <a href="create_exam.php" class="btn btn-primary btn-small">创建新考试</a>
                    </div>
                </div>
                <div>
                    <p>可通过“再次发布”让学生重新参与；支持按状态快速辨识不同阶段任务。</p>
                </div>
            </section>
            
            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>标题</th>
                                <th>类型</th>
                                <th>题目数</th>
                                <th>开始时间</th>
                                <th>结束时间</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($exams)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #94a3b8;">暂无考试/作业</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($exams as $exam): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td><?php echo $exam['type'] === 'exam' ? '考试' : '作业'; ?></td>
                                        <td><?php echo $exam['question_count']; ?></td>
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
                                            <div class="table-actions">
                                                <a href="exam_detail.php?id=<?php echo $exam['id']; ?>" class="btn btn-muted btn-small">详情</a>
                                                <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-secondary btn-small">修改</a>
                                                <a href="grade_exam.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-success btn-small">批改</a>
                                                <form method="POST" onsubmit="return confirm('确定要再次发布「<?php echo htmlspecialchars($exam['title']); ?>」吗？\n\n此操作会将考试状态设为已发布。');">
                                                    <input type="hidden" name="republish_id" value="<?php echo $exam['id']; ?>">
                                                    <button type="submit" class="btn btn-secondary btn-small" title="将考试状态设置为已发布">再次发布</button>
                                                </form>
                                                <form method="POST" action="delete_exam.php" onsubmit="return confirm('确定要删除「<?php echo htmlspecialchars($exam['title']); ?>」吗？\n\n此操作不可恢复。');">
                                                    <input type="hidden" name="delete_id" value="<?php echo $exam['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-small">删除</button>
                                                </form>
                                            </div>
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

