<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$teacherId = $_SESSION['user_id'];
$bankId = isset($_GET['id']) ? intval($_GET['id']) : null;
$message = '';
$messageType = '';
$bank = null;
$questions = [];
$user = getCurrentUser();

if ($bankId) {
    $stmt = $pdo->prepare("SELECT * FROM question_banks WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$bankId, $teacherId]);
    $bank = $stmt->fetch();
    if (!$bank) {
        header('Location: question_bank.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM question_bank_questions WHERE bank_id = ? ORDER BY order_num ASC");
    $stmt->execute([$bankId]);
    $questions = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = $_POST['description'] ?? '';

    if ($name === '') {
        $message = '题库名称不能为空。';
        $messageType = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            if ($bankId) {
                $stmt = $pdo->prepare("UPDATE question_banks SET name = ?, description = ? WHERE id = ? AND teacher_id = ?");
                $stmt->execute([$name, $description, $bankId, $teacherId]);

                $stmt = $pdo->prepare("DELETE FROM question_bank_questions WHERE bank_id = ?");
                $stmt->execute([$bankId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO question_banks (teacher_id, name, description, status) VALUES (?, ?, ?, 'draft')");
                $stmt->execute([$teacherId, $name, $description]);
                $bankId = $pdo->lastInsertId();
            }

            if (isset($_POST['questions']) && is_array($_POST['questions'])) {
                $orderNum = 1;
                $insertStmt = $pdo->prepare("INSERT INTO question_bank_questions (bank_id, question_text, question_type, section_label, options, correct_answer, score, order_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($_POST['questions'] as $q) {
                    if (empty(trim($q['question_text'] ?? ''))) {
                        continue;
                    }

                    $options = null;
                    if (!empty($q['options'])) {
                        if (is_array($q['options'])) {
                            $options = json_encode($q['options'], JSON_UNESCAPED_UNICODE);
                        } else {
                            $options = $q['options'];
                        }
                    }

                    $sectionLabel = trim($q['section_label'] ?? '') ?: null;

                    $insertStmt->execute([
                        $bankId,
                        $q['question_text'],
                        $q['question_type'],
                        $sectionLabel,
                        $options,
                        $q['correct_answer'] ?? '',
                        $q['score'] ?? 0,
                        $orderNum++
                    ]);
                }
            }

            $pdo->commit();
            $message = '题库已保存。';
            $messageType = 'success';

            header('Location: question_bank.php');
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = '保存失败：' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$initialQuestions = [];
foreach ($questions as $q) {
    $options = [];
    if (!empty($q['options'])) {
        $decoded = json_decode($q['options'], true);
        if (is_array($decoded)) {
            $options = $decoded;
        }
    }
    $initialQuestions[] = [
        'question_text' => $q['question_text'],
        'question_type' => $q['question_type'],
        'section_label' => $q['section_label'] ?? '',
        'options' => $options,
        'correct_answer' => $q['correct_answer'],
        'score' => $q['score']
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $bankId ? '编辑题库' : '新建题库'; ?></title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .question-item {
            background: #f8fafc;
            padding: 18px;
            margin: 18px 0;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .btn-remove {
            background: #dc2626;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('question_bank', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1><?php echo $bankId ? '编辑题库' : '新建题库'; ?></h1>
                    <p>集中管理题目，准备好题库后就能随时调用到考试中，节省重复录入的时间。</p>
                </div>
                <div>
                    <a href="question_bank.php" class="btn btn-muted btn-small">返回题库列表</a>
                </div>
            </section>

            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" id="bankForm">
                    <div class="form-group">
                        <label>题库名称：</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($bank['name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>题库说明：</label>
                        <textarea name="description"><?php echo htmlspecialchars($bank['description'] ?? ''); ?></textarea>
                    </div>

                    <h3>题目列表</h3>
                    <p style="color:#6b7280;margin-bottom:10px;">支持单选、多选、判断、填空、主观题。可输入选项文本，系统自动转换为JSON。</p>
                    <div id="questionsContainer"></div>
                    <button type="button" class="btn btn-secondary" onclick="addQuestion()">添加题目</button>

                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">保存题库</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        let questionIndex = 0;
        const initialQuestions = <?php echo json_encode($initialQuestions, JSON_UNESCAPED_UNICODE); ?>;

        function addQuestion(data = null) {
            const container = document.getElementById('questionsContainer');
            const div = document.createElement('div');
            div.className = 'question-item';
            div.id = 'question-' + questionIndex;

            const questionText = data ? data.question_text : '';
            const questionType = data ? data.question_type : 'single_choice';
            const score = data ? data.score : 5;

            div.innerHTML = `
                <div class="form-group">
                    <label>标题/序号（可选）：</label>
                    <input type="text" name="questions[${questionIndex}][section_label]" placeholder="如：一、选择题" value="${data ? (data.section_label || '') : ''}">
                </div>
                <div class="question-header">
                    <strong>题目 ${questionIndex + 1}</strong>
                    <button type="button" class="btn-remove" onclick="removeQuestion(${questionIndex})">删除</button>
                </div>
                <div class="form-group">
                    <label>题目内容：</label>
                    <textarea name="questions[${questionIndex}][question_text]" required>${questionText || ''}</textarea>
                </div>
                <div class="form-group">
                    <label>题型：</label>
                    <select name="questions[${questionIndex}][question_type]" onchange="changeQuestionType(${questionIndex}, this.value)">
                        <option value="single_choice" ${questionType === 'single_choice' ? 'selected' : ''}>单选题</option>
                        <option value="multiple_choice" ${questionType === 'multiple_choice' ? 'selected' : ''}>多选题</option>
                        <option value="judge" ${questionType === 'judge' ? 'selected' : ''}>判断题</option>
                        <option value="fill_blank" ${questionType === 'fill_blank' ? 'selected' : ''}>填空题</option>
                        <option value="essay" ${questionType === 'essay' ? 'selected' : ''}>主观题</option>
                        <option value="big_question" ${questionType === 'big_question' ? 'selected' : ''}>一大题</option>
                        <option value="answer_question" ${questionType === 'answer_question' ? 'selected' : ''}>二答题</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>分值：</label>
                    <input type="number" name="questions[${questionIndex}][score]" step="0.01" min="0" value="${score}" required>
                </div>
                <div id="options-${questionIndex}"></div>
                <div id="answer-${questionIndex}"></div>
            `;

            container.appendChild(div);
            changeQuestionType(questionIndex, questionType, data);
            questionIndex++;
        }

        function changeQuestionType(index, type, data = null) {
            const optionsDiv = document.getElementById('options-' + index);
            const answerDiv = document.getElementById('answer-' + index);

            optionsDiv.innerHTML = '';
            answerDiv.innerHTML = '';

            const correctAnswer = data ? (data.correct_answer || '') : '';
            const options = data && data.options ? data.options : null;

            if (type === 'single_choice' || type === 'multiple_choice') {
                let optionsText = '';
                if (options) {
                    const lines = [];
                    Object.keys(options).forEach(key => {
                        lines.push(`${key}. ${options[key]}`);
                    });
                    optionsText = lines.join('\n');
                }

                optionsDiv.innerHTML = `
                    <div class="form-group">
                        <label>选项（每行一个）：</label>
                        <textarea name="questions[${index}][options_text]" rows="4" placeholder="A. 选项1&#10;B. 选项2">${optionsText}</textarea>
                    </div>
                    <div class="form-group">
                        <label>正确答案（${type === 'single_choice' ? '单选' : '多选，用逗号分隔'}）：</label>
                        <input type="text" name="questions[${index}][correct_answer]" value="${correctAnswer}" placeholder="${type === 'single_choice' ? '如：A' : '如：A,B'}" required>
                    </div>
                `;
            } else if (type === 'judge') {
                answerDiv.innerHTML = `
                    <div class="form-group">
                        <label>正确答案：</label>
                        <select name="questions[${index}][correct_answer]" required>
                            <option value="正确" ${correctAnswer === '正确' ? 'selected' : ''}>正确</option>
                            <option value="错误" ${correctAnswer === '错误' ? 'selected' : ''}>错误</option>
                        </select>
                    </div>
                `;
            } else if (type === 'fill_blank') {
                answerDiv.innerHTML = `
                    <div class="form-group">
                        <label>正确答案（可选）：</label>
                        <input type="text" name="questions[${index}][correct_answer]" value="${correctAnswer}">
                    </div>
                `;
            } else {
                answerDiv.innerHTML = `
                    <div class="form-group">
                        <label>参考答案/评分说明（可选）：</label>
                        <textarea name="questions[${index}][correct_answer]">${correctAnswer}</textarea>
                    </div>
                `;
            }
        }

        function removeQuestion(index) {
            const element = document.getElementById('question-' + index);
            if (element) {
                element.remove();
            }
        }

        document.getElementById('bankForm').addEventListener('submit', function() {
            const questions = document.querySelectorAll('[name^="questions["]');
            questions.forEach(function(input) {
                if (input.name.includes('[options_text]')) {
                    const index = input.name.match(/\[(\d+)\]/)[1];
                    const optionsText = input.value.split('\n').filter(line => line.trim());
                    const options = {};
                    optionsText.forEach((line, i) => {
                        const match = line.match(/^([A-Z])\.\s*(.+)$/);
                        if (match) {
                            options[match[1]] = match[2].trim();
                        } else {
                            options[String.fromCharCode(65 + i)] = line.trim();
                        }
                    });

                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = `questions[${index}][options]`;
                    hiddenInput.value = JSON.stringify(options);
                    input.parentNode.appendChild(hiddenInput);
                }
            });
        });

        window.onload = function() {
            if (initialQuestions.length) {
                initialQuestions.forEach(q => addQuestion(q));
            } else {
                addQuestion();
            }
        };
    </script>
</body>
</html>

