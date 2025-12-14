<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$user = getCurrentUser();
$teacherId = $_SESSION['user_id'];

$examCount = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE teacher_id = ?");
$examCount->execute([$teacherId]);
$totalExams = (int)$examCount->fetchColumn();

$activeExamStmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE teacher_id = ? AND status = 'published' AND end_time >= NOW()");
$activeExamStmt->execute([$teacherId]);
$activeExams = (int)$activeExamStmt->fetchColumn();

$draftExamStmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE teacher_id = ? AND status = 'draft'");
$draftExamStmt->execute([$teacherId]);
$draftExams = (int)$draftExamStmt->fetchColumn();

$bankStmt = $pdo->prepare("SELECT COUNT(*) FROM question_banks WHERE teacher_id = ?");
$bankStmt->execute([$teacherId]);
$bankCount = (int)$bankStmt->fetchColumn();

$shareStmt = $pdo->prepare("SELECT COUNT(*) FROM question_bank_shares WHERE owner_teacher_id = ?");
$shareStmt->execute([$teacherId]);
$shareCount = (int)$shareStmt->fetchColumn();

$recentExamsStmt = $pdo->prepare("SELECT id, title, status, end_time FROM exams WHERE teacher_id = ? ORDER BY created_at DESC LIMIT 4");
$recentExamsStmt->execute([$teacherId]);
$recentExams = $recentExamsStmt->fetchAll();

$recentBanksStmt = $pdo->prepare("SELECT id, name, status, updated_at FROM question_banks WHERE teacher_id = ? ORDER BY updated_at DESC LIMIT 4");
$recentBanksStmt->execute([$teacherId]);
$recentBanks = $recentBanksStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>老师工作台</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('dashboard', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>您好，<?php echo htmlspecialchars($user['name']); ?> 👋</h1>
                    <p>快速了解最新的教学动态，管理题库与考试，保持课堂节奏井然有序。</p>
                    <div class="hero-actions">
                        <a href="create_exam.php" class="btn btn-primary btn-small">立即发布考试</a>
                        <a href="quick_generate_exam.php" class="btn btn-secondary btn-small">快速组卷</a>
                        <a href="question_bank.php" class="btn btn-muted btn-small">管理题库</a>
                    </div>
                </div>
                <div>
                    <p>提示：提前准备好题库并发布，可以一键分享给其他老师，更高效地协同授课。也可以使用“快速组卷”从题库随机抽题生成整套试卷。</p>
                </div>
            </section>

            <section class="stats-grid">
                <div class="stat-card">
                    <span>累计考试/作业</span>
                    <strong><?php echo $totalExams; ?></strong>
                </div>
                <div class="stat-card">
                    <span>进行中的任务</span>
                    <strong><?php echo $activeExams; ?></strong>
                </div>
                <div class="stat-card">
                    <span>草稿待完善</span>
                    <strong><?php echo $draftExams; ?></strong>
                </div>
                <div class="stat-card">
                    <span>我的题库</span>
                    <strong><?php echo $bankCount; ?></strong>
                </div>
                <div class="stat-card">
                    <span>分享中的题库</span>
                    <strong><?php echo $shareCount; ?></strong>
                </div>
            </section>

            <section class="card-grid">
                <div class="card-highlight">
                    <h3>快速发布考试</h3>
                    <p>从题库导入题目或手工添加，支持多种题型与自动判分，为每一次测评做好充分准备。</p>
                    <div class="card-actions">
                        <a href="create_exam.php" class="btn btn-primary btn-small">开始创建</a>
                        <a href="import_exam.php" class="btn btn-secondary btn-small">导入试题</a>
                    </div>
                </div>
                <div class="card-highlight">
                    <h3>题库协同</h3>
                    <p>个人题库不再孤立，发布后即可分享给同事，并可随时复制他人题库作为草稿。</p>
                    <div class="card-actions">
                        <a href="question_bank.php" class="btn btn-primary btn-small">管理题库</a>
                    </div>
                </div>
                <div class="card-highlight">
                    <h3>评阅与成绩</h3>
                    <p>自动批改客观题，手动批改主观题，完成后即可一键发布成绩，学生实时可查。</p>
                    <div class="card-actions">
                        <a href="grade_exam.php" class="btn btn-secondary btn-small">前往批改</a>
                        <a href="publish_score.php" class="btn btn-primary btn-small">发布成绩</a>
                    </div>
                </div>
            </section>

            <div class="card">
                <div class="section-title">
                    <h2>最近发布的考试</h2>
                    <a href="exam_list.php" class="btn btn-muted btn-small">查看全部</a>
                </div>
                <?php if (empty($recentExams)): ?>
                    <p>暂无记录，点击“立即发布考试”开始创建。</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>标题</th>
                                    <th>状态</th>
                                    <th>截止时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentExams as $exam): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td>
                                            <span class="tag <?php echo $exam['status'] === 'published' ? 'tag-published' : 'tag-draft'; ?>">
                                                <?php echo $exam['status'] === 'published' ? '已发布' : '草稿'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($exam['end_time']); ?></td>
                                        <td class="table-actions">
                                            <a href="exam_detail.php?id=<?php echo $exam['id']; ?>" class="btn btn-muted btn-small">详情</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="section-title">
                    <h2>最新题库</h2>
                    <a href="question_bank.php" class="btn btn-muted btn-small">管理题库</a>
                </div>
                <?php if (empty($recentBanks)): ?>
                    <p>还没有题库，建议先创建个人题库，方便重复利用。</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>题库名称</th>
                                    <th>状态</th>
                                    <th>更新时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBanks as $bank): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bank['name']); ?></td>
                                        <td>
                                            <span class="tag <?php echo $bank['status'] === 'published' ? 'tag-published' : 'tag-draft'; ?>">
                                                <?php echo $bank['status'] === 'published' ? '已发布' : '草稿'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($bank['updated_at']); ?></td>
                                        <td class="table-actions">
                                            <a href="question_bank_edit.php?id=<?php echo $bank['id']; ?>" class="btn btn-muted btn-small">编辑</a>
                                        </td>
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

