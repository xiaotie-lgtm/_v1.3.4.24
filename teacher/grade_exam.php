<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$user = getCurrentUser();
$teacherId = $_SESSION['user_id'];

$examId = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$selectedStudentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
$filter = $_GET['filter'] ?? 'all'; // all, graded, ungraded
$message = '';
$messageType = '';

// 老师所有考试列表
$examsStmt = $pdo->prepare("SELECT id, title, status, start_time, end_time FROM exams WHERE teacher_id = ? ORDER BY created_at DESC");
$examsStmt->execute([$teacherId]);
$teacherExams = $examsStmt->fetchAll();

$exam = null;
$questions = [];
$students = [];
$currentStudent = null;

if ($examId) {
    $examStmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
    $examStmt->execute([$examId, $teacherId]);
    $exam = $examStmt->fetch();

    if (!$exam) {
        $message = '未找到对应考试或无权访问。';
        $messageType = 'error';
        $examId = 0;
        $exam = null;
    } else {
        // 载入试题
        $questionStmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY order_num");
        $questionStmt->execute([$examId]);
        $questions = $questionStmt->fetchAll();

        // 自动批改请求
        if (isset($_GET['auto_grade']) && $_GET['auto_grade'] == '1') {
            try {
                $pdo->beginTransaction();

                $studentStmt = $pdo->prepare("SELECT DISTINCT u.id FROM users u INNER JOIN answers a ON u.id = a.student_id WHERE a.exam_id = ?");
                $studentStmt->execute([$examId]);
                $allStudents = $studentStmt->fetchAll();

                foreach ($allStudents as $stu) {
                    $autoScore = 0;
                    foreach ($questions as $question) {
                        if (!in_array($question['question_type'], ['single_choice', 'multiple_choice', 'judge'])) {
                            continue;
                        }
                        $answerStmt = $pdo->prepare("SELECT * FROM answers WHERE exam_id = ? AND question_id = ? AND student_id = ?");
                        $answerStmt->execute([$examId, $question['id'], $stu['id']]);
                        $answer = $answerStmt->fetch();
                        if (!$answer) {
                            continue;
                        }
                        if ($answer['is_auto_graded']) {
                            $autoScore += $answer['score'];
                            continue;
                        }

                        $studentAnswer = trim($answer['answer_text']);
                        $correctAnswer = trim($question['correct_answer']);
                        $score = 0;

                        if ($question['question_type'] === 'judge' || $question['question_type'] === 'single_choice') {
                            if (strcasecmp($studentAnswer, $correctAnswer) === 0) {
                                $score = $question['score'];
                            }
                        } else {
                            $studentAnswers = array_map('trim', explode(',', $studentAnswer));
                            $correctAnswers = array_map('trim', explode(',', $correctAnswer));
                            $studentAnswers = array_map('strtoupper', $studentAnswers);
                            $correctAnswers = array_map('strtoupper', $correctAnswers);
                            sort($studentAnswers);
                            sort($correctAnswers);
                            if ($studentAnswers === $correctAnswers) {
                                $score = $question['score'];
                            }
                        }

                        $upd = $pdo->prepare("UPDATE answers SET score = ?, is_auto_graded = 1, graded_at = NOW() WHERE id = ?");
                        $upd->execute([$score, $answer['id']]);
                        $autoScore += $score;
                    }

                    $scoreRecord = $pdo->prepare("SELECT * FROM scores WHERE exam_id = ? AND student_id = ?");
                    $scoreRecord->execute([$examId, $stu['id']]);
                    if ($scoreRecord = $scoreRecord->fetch()) {
                        $updScore = $pdo->prepare("UPDATE scores SET auto_score = ? WHERE id = ?");
                        $updScore->execute([$autoScore, $scoreRecord['id']]);
                    } else {
                        $insScore = $pdo->prepare("INSERT INTO scores (exam_id, student_id, auto_score, status) VALUES (?, ?, ?, 'graded')");
                        $insScore->execute([$examId, $stu['id'], $autoScore]);
                    }
                }

                $pdo->commit();
                header("Location: grade_exam.php?exam_id={$examId}&filter={$filter}" . ($selectedStudentId ? "&student_id={$selectedStudentId}" : ''));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = '自动批改失败：' . $e->getMessage();
                $messageType = 'error';
            }
        }

        // 手动评分
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade'])) {
            try {
                $pdo->beginTransaction();
                $gradedStudentId = intval($_POST['student_id']);

                foreach ($_POST['scores'] as $answerId => $score) {
                    $score = floatval($score);
                    $updAns = $pdo->prepare("UPDATE answers SET score = ?, graded_at = NOW() WHERE id = ?");
                    $updAns->execute([$score, $answerId]);
                }

                $autoScoreStmt = $pdo->prepare("SELECT COALESCE(SUM(score),0) AS total FROM answers WHERE exam_id = ? AND student_id = ? AND is_auto_graded = 1");
                $autoScoreStmt->execute([$examId, $gradedStudentId]);
                $autoScore = $autoScoreStmt->fetch()['total'];

                $manualScoreStmt = $pdo->prepare("SELECT COALESCE(SUM(score),0) AS total FROM answers WHERE exam_id = ? AND student_id = ? AND is_auto_graded = 0");
                $manualScoreStmt->execute([$examId, $gradedStudentId]);
                $manualScore = $manualScoreStmt->fetch()['total'];

                $totalScore = $autoScore + $manualScore;

                $scoreRecord = $pdo->prepare("SELECT * FROM scores WHERE exam_id = ? AND student_id = ?");
                $scoreRecord->execute([$examId, $gradedStudentId]);
                if ($scoreRecord = $scoreRecord->fetch()) {
                    $updScore = $pdo->prepare("UPDATE scores SET auto_score = ?, manual_score = ?, total_score = ?, status = 'graded', graded_at = NOW() WHERE id = ?");
                    $updScore->execute([$autoScore, $manualScore, $totalScore, $scoreRecord['id']]);
                } else {
                    $insScore = $pdo->prepare("INSERT INTO scores (exam_id, student_id, auto_score, manual_score, total_score, status, graded_at) VALUES (?, ?, ?, ?, ?, 'graded', NOW())");
                    $insScore->execute([$examId, $gradedStudentId, $autoScore, $manualScore, $totalScore]);
                }

                $pdo->commit();
                header("Location: grade_exam.php?exam_id={$examId}&filter={$filter}&student_id={$gradedStudentId}");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = '评分失败：' . $e->getMessage();
                $messageType = 'error';
            }
        }

        // 获取学生列表
        $studentsQuery = "
            SELECT DISTINCT u.id, u.name, u.username,
                CASE WHEN s.status IN ('graded','published') THEN 1 ELSE 0 END AS is_graded,
                s.total_score, s.status
            FROM users u
            INNER JOIN answers a ON u.id = a.student_id
            LEFT JOIN scores s ON s.exam_id = a.exam_id AND s.student_id = u.id
            WHERE a.exam_id = ?
        ";
        $params = [$examId];
        if ($filter === 'graded') {
            $studentsQuery .= " AND (s.status = 'graded' OR s.status = 'published')";
        } elseif ($filter === 'ungraded') {
            $studentsQuery .= " AND (s.status IS NULL OR s.status = 'submitted')";
        }
        $studentsQuery .= " ORDER BY is_graded ASC, u.name";

        $studentsStmt = $pdo->prepare($studentsQuery);
        $studentsStmt->execute($params);
        $students = $studentsStmt->fetchAll();

        if ($selectedStudentId) {
            foreach ($students as $stu) {
                if ($stu['id'] == $selectedStudentId) {
                    $currentStudent = $stu;
                    break;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批改作业</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .student-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .student-item {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            color: #1f2937;
            background: #fff;
        }
        .student-item:hover {
            border-color: #6366f1;
            background: #eef2ff;
        }
        .student-item.active {
            border-color: #4f46e5;
            background: #4f46e5;
            color: white;
        }
        .student-item.graded {
            border-color: #16a34a;
            background: #dcfce7;
        }
        .student-item.ungraded {
            border-color: #f97316;
            background: #fff7ed;
        }
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .filter-tab {
            padding: 8px 16px;
            border: 2px solid #6366f1;
            background: white;
            color: #4c1d95;
            border-radius: 999px;
            cursor: pointer;
            text-decoration: none;
        }
        .filter-tab.active {
            background: #6366f1;
            color: white;
        }
        .question-section-label {
            display: inline-flex;
            padding: 4px 12px;
            border-radius: 999px;
            background: #e0e7ff;
            color: #4338ca;
            font-weight: 600;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('grade', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1><?php echo $exam ? '批改作业 - ' . htmlspecialchars($exam['title']) : '批改作业'; ?></h1>
                    <p>自动批改客观题并对主观题进行细致评分，随时掌握学生作答与得分情况。</p>
                    <div class="hero-actions">
                        <a href="exam_list.php" class="btn btn-muted btn-small">返回考试列表</a>
                        <?php if ($exam): ?>
                            <a href="?exam_id=<?php echo $examId; ?>&auto_grade=1&filter=<?php echo $filter; ?>" class="btn btn-success btn-small">执行自动批改</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <p>提示：先选择考试再定位具体学生；自动批改仅作用于单选/多选/判断题。</p>
                </div>
            </section>

            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!$exam): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>请选择需要批改的考试</h2>
                    </div>
                    <?php if (empty($teacherExams)): ?>
                        <p style="color:#94a3b8;">您尚未发布考试，先前往“发布考试/作业”。</p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>标题</th>
                                        <th>状态</th>
                                        <th>开始时间</th>
                                        <th>结束时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teacherExams as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['title']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $item['status']; ?>">
                                                    <?php echo $item['status'] === 'published' ? '已发布' : ($item['status'] === 'finished' ? '已结束' : '草稿'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['start_time']); ?></td>
                                            <td><?php echo htmlspecialchars($item['end_time']); ?></td>
                                            <td><a href="?exam_id=<?php echo $item['id']; ?>" class="btn btn-primary btn-small">进入批改</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="filter-tabs">
                        <a href="?exam_id=<?php echo $examId; ?>&filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                            全部 (<?php echo count($students); ?>)
                        </a>
                        <a href="?exam_id=<?php echo $examId; ?>&filter=ungraded" class="filter-tab <?php echo $filter === 'ungraded' ? 'active' : ''; ?>">
                            未批改 (<?php echo count(array_filter($students, fn($s) => !$s['is_graded'])); ?>)
                        </a>
                        <a href="?exam_id=<?php echo $examId; ?>&filter=graded" class="filter-tab <?php echo $filter === 'graded' ? 'active' : ''; ?>">
                            已批改 (<?php echo count(array_filter($students, fn($s) => $s['is_graded'])); ?>)
                        </a>
                    </div>

                    <div class="student-list">
                        <?php foreach ($students as $student): ?>
                            <a href="?exam_id=<?php echo $examId; ?>&filter=<?php echo $filter; ?>&student_id=<?php echo $student['id']; ?>"
                               class="student-item <?php echo $student['is_graded'] ? 'graded' : 'ungraded'; ?> <?php echo $selectedStudentId == $student['id'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($student['name']); ?>
                                <?php if ($student['is_graded']): ?><span style="margin-left:6px;">✓</span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($students)): ?>
                        <p style="color:#94a3b8;">暂无学生提交。</p>
                    <?php elseif ($selectedStudentId && $currentStudent): ?>
                        <form method="POST">
                            <input type="hidden" name="student_id" value="<?php echo $currentStudent['id']; ?>">

                            <div class="card" style="margin-bottom:20px;border-left:4px solid <?php echo $currentStudent['is_graded'] ? '#16a34a' : '#f97316'; ?>;">
                                <div class="card-header">
                                    <h3>
                                        <?php echo htmlspecialchars($currentStudent['name']); ?> (<?php echo htmlspecialchars($currentStudent['username']); ?>)
                                        <span class="status-badge <?php echo $currentStudent['is_graded'] ? 'status-graded' : 'status-submitted'; ?>" style="margin-left:10px;">
                                            <?php echo $currentStudent['is_graded'] ? '已批改' : '未批改'; ?>
                                        </span>
                                    </h3>
                                </div>

                                <?php
                                $scoreStmt = $pdo->prepare("SELECT * FROM scores WHERE exam_id = ? AND student_id = ?");
                                $scoreStmt->execute([$examId, $currentStudent['id']]);
                                $scoreRow = $scoreStmt->fetch();
                                if ($scoreRow):
                                ?>
                                    <div style="padding:15px;background:#f8fafc;border-radius:12px;margin-bottom:15px;">
                                        <p><strong>自动批改得分：</strong><?php echo $scoreRow['auto_score']; ?> 分</p>
                                        <p><strong>手动评分得分：</strong><?php echo $scoreRow['manual_score']; ?> 分</p>
                                        <p><strong>总分：</strong><span style="font-size:18px;color:#4f46e5;font-weight:bold;"><?php echo $scoreRow['total_score']; ?></span> 分</p>
                                    </div>
                                <?php endif; ?>

                                <?php 
                                $lastSectionLabel = '';
                                foreach ($questions as $index => $question): 
                                    // 如果当前题目的大标题与前一个不同，显示大标题
                                    if (!empty($question['section_label']) && $question['section_label'] !== $lastSectionLabel):
                                        $lastSectionLabel = $question['section_label'];
                                ?>
                                    <div style="margin: 20px 0 10px 0; padding: 12px 20px; background: linear-gradient(135deg, #e0e7ff, #c7d2fe); border-left: 4px solid #4338ca; border-radius: 8px;">
                                        <h3 style="margin: 0; color: #4338ca; font-size: 18px; font-weight: 600;"><?php echo htmlspecialchars($question['section_label']); ?></h3>
                                    </div>
                                <?php 
                                    elseif (empty($question['section_label'])):
                                        $lastSectionLabel = '';
                                    endif;
                                    
                                    $answerStmt = $pdo->prepare("SELECT * FROM answers WHERE exam_id = ? AND question_id = ? AND student_id = ?");
                                    $answerStmt->execute([$examId, $question['id'], $currentStudent['id']]);
                                    $answer = $answerStmt->fetch();
                                ?>
                                    <div style="margin:15px 0;padding:15px;background:#f8fafc;border-radius:12px;">
                                        <p><strong>第<?php echo $index + 1; ?>题（<?php
                                            $typeMap = [
                                                'single_choice' => '单选题',
                                                'multiple_choice' => '多选题',
                                                'judge' => '判断题',
                                                'fill_blank' => '填空题',
                                                'essay' => '主观题',
                                                'big_question' => '一大题',
                                                'answer_question' => '二答题'
                                            ];
                                            echo $typeMap[$question['question_type']] ?? '主观题';
                                        ?>，<?php echo $question['score']; ?>分）：</strong></p>
                                        <p><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>

                                        <?php if ($answer): ?>
                                            <p><strong>学生答案：</strong><?php echo nl2br(htmlspecialchars($answer['answer_text'])); ?></p>
                                            <p><strong>正确答案：</strong><?php echo htmlspecialchars($question['correct_answer']); ?></p>
                                            <?php if ($answer['is_auto_graded']): ?>
                                                <p><strong>得分：</strong><span style="color:#16a34a;"><?php echo $answer['score']; ?> 分</span>（已自动批改）</p>
                                            <?php else: ?>
                                                <div class="grading-area">
                                                    <label>评分（0-<?php echo $question['score']; ?>分）：</label>
                                                    <input type="number" name="scores[<?php echo $answer['id']; ?>]" min="0" max="<?php echo $question['score']; ?>" step="0.01" value="<?php echo $answer['score'] ?? 0; ?>" required>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p style="color:#94a3b8;">未作答</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <div style="margin-top:20px;">
                                    <button type="submit" name="grade" class="btn btn-primary">保存评分</button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="card">
                            <p style="text-align:center;color:#94a3b8;padding:40px;">请从上方选择学生进行批改。</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

