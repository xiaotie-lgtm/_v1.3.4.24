<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$teacherId = $_SESSION['user_id'];
$user = getCurrentUser();

// 获取老师的所有考试
$exams = $pdo->prepare("SELECT * FROM exams WHERE teacher_id = ? ORDER BY created_at DESC");
$exams->execute([$teacherId]);
$exams = $exams->fetchAll();

// 获取老师管理的班级
$classes = $pdo->prepare("SELECT c.* FROM classes c INNER JOIN teacher_classes tc ON c.id = tc.class_id WHERE tc.teacher_id = ?");
$classes->execute([$teacherId]);
$classes = $classes->fetchAll();

// 获取每个班级的学生数量
$classStudents = [];
foreach ($classes as $class) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE class_id = ? AND role = 'student'");
    $stmt->execute([$class['id']]);
    $classStudents[$class['id']] = $stmt->fetch()['count'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>考试可见性检查</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('exam_list', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>考试可见性检查</h1>
                    <p>检查您的考试是否能被学生看到，以及可能存在的问题。</p>
                </div>
            </section>

            <div class="card">
                <h2>我的班级信息</h2>
                <?php if (empty($classes)): ?>
                    <div class="error-message">
                        <strong>⚠️ 问题：您没有分配到任何班级</strong>
                        <p>学生只能看到自己班级的老师发布的考试。请先到"班级管理"页面将自己分配到相应的班级。</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>班级名称</th>
                                    <th>学生数量</th>
                                    <th>状态</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($class['name']); ?></td>
                                        <td><?php echo $classStudents[$class['id']] ?? 0; ?> 人</td>
                                        <td>
                                            <?php if (($classStudents[$class['id']] ?? 0) > 0): ?>
                                                <span class="tag tag-published">正常</span>
                                            <?php else: ?>
                                                <span class="tag tag-draft">无学生</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2>考试可见性分析</h2>
                <?php if (empty($exams)): ?>
                    <p>您还没有创建任何考试。</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>考试标题</th>
                                    <th>状态</th>
                                    <th>开始时间</th>
                                    <th>结束时间</th>
                                    <th>可见性分析</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exams as $exam): ?>
                                    <?php
                                    $issues = [];
                                    $warnings = [];
                                    
                                    // 检查状态
                                    if ($exam['status'] !== 'published') {
                                        $issues[] = "考试状态为" . ($exam['status'] === 'draft' ? '草稿' : '已结束') . "，学生无法看到";
                                    }
                                    
                                    // 检查时间
                                    $now = time();
                                    $startTime = strtotime($exam['start_time']);
                                    $endTime = strtotime($exam['end_time']);
                                    
                                    if ($now > $endTime) {
                                        $warnings[] = "考试已过期";
                                    } elseif ($now < $startTime) {
                                        $warnings[] = "考试尚未开始";
                                    }
                                    
                                    // 检查班级关联
                                    if (empty($classes)) {
                                        $issues[] = "您没有分配到任何班级，学生无法看到此考试";
                                    }
                                    
                                    // 检查是否有题目
                                    $questionCount = $pdo->prepare("SELECT COUNT(*) as count FROM questions WHERE exam_id = ?");
                                    $questionCount->execute([$exam['id']]);
                                    $questionCount = $questionCount->fetch()['count'];
                                    
                                    if ($questionCount == 0) {
                                        $warnings[] = "考试中没有题目";
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td>
                                            <?php if ($exam['status'] === 'published'): ?>
                                                <span class="tag tag-published">已发布</span>
                                            <?php elseif ($exam['status'] === 'draft'): ?>
                                                <span class="tag tag-draft">草稿</span>
                                            <?php else: ?>
                                                <span class="tag tag-finished">已结束</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', $startTime); ?></td>
                                        <td><?php echo date('Y-m-d H:i', $endTime); ?></td>
                                        <td>
                                            <?php if (empty($issues) && empty($warnings)): ?>
                                                <span style="color: #16a34a;">✓ 正常，学生可以看到</span>
                                            <?php else: ?>
                                                <?php if (!empty($issues)): ?>
                                                    <div style="color: #dc2626; margin-bottom: 5px;">
                                                        <strong>问题：</strong>
                                                        <ul style="margin: 5px 0; padding-left: 20px;">
                                                            <?php foreach ($issues as $issue): ?>
                                                                <li><?php echo htmlspecialchars($issue); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($warnings)): ?>
                                                    <div style="color: #f59e0b;">
                                                        <strong>提示：</strong>
                                                        <ul style="margin: 5px 0; padding-left: 20px;">
                                                            <?php foreach ($warnings as $warning): ?>
                                                                <li><?php echo htmlspecialchars($warning); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card" style="margin-top: 20px; background: #f0f9ff; border-left: 4px solid #0ea5e9;">
                <h3>💡 重要说明</h3>
                <ul style="line-height: 1.8;">
                    <li><strong>题库 ≠ 考试：</strong>学生无法直接看到题库。您需要从题库创建考试后，学生才能看到。</li>
                    <li><strong>班级关联：</strong>学生只能看到自己班级的老师发布的考试。请确保您已分配到相应的班级。</li>
                    <li><strong>考试状态：</strong>只有状态为"已发布"的考试，学生才能看到。</li>
                    <li><strong>时间限制：</strong>即使考试已发布，如果当前时间不在开始时间和结束时间之间，学生可能看不到或无法参加。</li>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>

