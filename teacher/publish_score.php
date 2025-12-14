<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$message = '';
$messageType = '';
$user = getCurrentUser();

// 获取所有考试
$exams = $pdo->query("SELECT * FROM exams WHERE teacher_id = " . $_SESSION['user_id'] . " ORDER BY created_at DESC")->fetchAll();

// 发布成绩
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish'])) {
    try {
        $examId = $_POST['exam_id'];
        $update = $pdo->prepare("UPDATE scores SET status = 'published', published_at = NOW() WHERE exam_id = ? AND status = 'graded'");
        $update->execute([$examId]);
        $message = '成绩发布成功！';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = '发布失败：' . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发布成绩</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('publish_score', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>发布成绩</h1>
                    <p>汇总所有考试/作业的批改进度，批量发布已完成评分的成绩。</p>
                </div>
                <div>
                    <p>提示：只有状态为“graded”的成绩才会被发布；发布后学生即可在学员端查看。</p>
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
                                <th>考试/作业</th>
                                <th>类型</th>
                                <th>已批改人数</th>
                                <th>已发布人数</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $exam): ?>
                                <?php 
                                $gradedCount = $pdo->prepare("SELECT COUNT(*) as cnt FROM scores WHERE exam_id = ? AND status IN ('graded', 'published')");
                                $gradedCount->execute([$exam['id']]);
                                $gradedCount = $gradedCount->fetch()['cnt'];
                                
                                $publishedCount = $pdo->prepare("SELECT COUNT(*) as cnt FROM scores WHERE exam_id = ? AND status = 'published'");
                                $publishedCount->execute([$exam['id']]);
                                $publishedCount = $publishedCount->fetch()['cnt'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo $exam['type'] === 'exam' ? '考试' : '作业'; ?></td>
                                    <td><?php echo $gradedCount; ?></td>
                                    <td><?php echo $publishedCount; ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <?php if ($gradedCount > 0 && $gradedCount > $publishedCount): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                    <button type="submit" name="publish" class="btn btn-success btn-small">发布成绩</button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">暂无待发布成绩</span>
                                            <?php endif; ?>
                                            <a href="score_list.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-small">查看成绩</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

