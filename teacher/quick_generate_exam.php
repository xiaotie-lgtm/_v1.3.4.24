<?php
require_once '../config.php';
checkRole('teacher');

$pdo = getDB();
$teacherId = $_SESSION['user_id'];
$user = getCurrentUser();

// 读取当前老师的题库及题目数量
$banksStmt = $pdo->prepare("
    SELECT qb.id, qb.name, COUNT(qbq.id) AS question_count
    FROM question_banks qb
    LEFT JOIN question_bank_questions qbq ON qb.id = qbq.bank_id
    WHERE qb.teacher_id = ?
    GROUP BY qb.id
    HAVING question_count > 0
    ORDER BY qb.updated_at DESC
");
$banksStmt->execute([$teacherId]);
$banks = $banksStmt->fetchAll();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bankId = intval($_POST['bank_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $totalQuestions = intval($_POST['total_questions'] ?? 0);

    if (!$bankId || !$title || $totalQuestions <= 0) {
        $message = '请选择题库并填写试卷标题与题目数量。';
        $messageType = 'error';
    } else {
        try {
            // 检查题库是否属于当前老师
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM question_banks WHERE id = ? AND teacher_id = ?");
            $checkStmt->execute([$bankId, $teacherId]);
            if ($checkStmt->fetchColumn() == 0) {
                throw new Exception('无权使用该题库。');
            }

            // 随机抽题
            $questionStmt = $pdo->prepare("
                SELECT * FROM question_bank_questions
                WHERE bank_id = ?
                ORDER BY RAND()
                LIMIT ?
            ");
            $questionStmt->bindValue(1, $bankId, PDO::PARAM_INT);
            $questionStmt->bindValue(2, $totalQuestions, PDO::PARAM_INT);
            $questionStmt->execute();
            $questions = $questionStmt->fetchAll();

            if (count($questions) === 0) {
                throw new Exception('该题库中没有题目。');
            }

            // 如果题库题目数量不足，则全部抽出
            if (count($questions) < $totalQuestions) {
                $totalQuestions = count($questions);
            }

            $pdo->beginTransaction();

            $now = date('Y-m-d H:i:s');
            $end = date('Y-m-d H:i:s', strtotime('+1 day'));

            // 创建考试（默认类型为考试，立即开始，有效期 1 天，草稿状态方便后续再编辑）
            $examStmt = $pdo->prepare("
                INSERT INTO exams (title, description, type, teacher_id, start_time, end_time, duration, allow_retake, status)
                VALUES (?, ?, 'exam', ?, ?, ?, NULL, 0, 'draft')
            ");
            $description = '由题库快速组卷自动生成';
            $examStmt->execute([
                $title,
                $description,
                $teacherId,
                $now,
                $end
            ]);

            $examId = $pdo->lastInsertId();

            // 写入考试题目
            $insertQuestion = $pdo->prepare("
                INSERT INTO questions (exam_id, question_text, question_type, section_label, options, correct_answer, score, order_num)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $order = 1;
            $selected = array_slice($questions, 0, $totalQuestions);
            foreach ($selected as $q) {
                $sectionLabel = !empty($q['section_label']) ? $q['section_label'] : null;
                $insertQuestion->execute([
                    $examId,
                    $q['question_text'],
                    $q['question_type'],
                    $sectionLabel,
                    $q['options'],
                    $q['correct_answer'],
                    $q['score'],
                    $order++
                ]);
            }

            $pdo->commit();

            // 生成成功后跳转到考试详情页或编辑页
            header('Location: exam_detail.php?id=' . $examId);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = '快速组卷失败：' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

require_once 'teacher_sidebar.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>快速组卷</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('quick_generate', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>快速组卷</h1>
                    <p>从现有题库中随机抽题，几秒钟生成一套新的试卷，后续仍可在考试编辑页继续调整。</p>
                </div>
            </section>

            <div class="card">
                <?php if ($message): ?>
                    <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($banks)): ?>
                    <p>当前还没有包含题目的题库，请先在“题库管理”中创建并添加题目。</p>
                <?php else: ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>选择题库：</label>
                            <select name="bank_id" required>
                                <option value="">请选择题库</option>
                                <?php foreach ($banks as $bank): ?>
                                    <option value="<?php echo $bank['id']; ?>">
                                        <?php echo htmlspecialchars($bank['name']); ?>（共<?php echo intval($bank['question_count']); ?>题）
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>试卷标题：</label>
                            <input type="text" name="title" placeholder="如：快速组卷-单元测试" required>
                        </div>

                        <div class="form-group">
                            <label>抽取题目数量：</label>
                            <input type="number" name="total_questions" min="1" placeholder="例如：20" required>
                            <p class="hint">若输入数量大于题库总题数，则会自动抽取题库中全部题目。</p>
                        </div>

                        <div style="margin-top:24px;text-align:right;">
                            <button type="submit" class="btn btn-primary">一键生成试卷</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>


