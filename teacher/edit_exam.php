<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$user = getCurrentUser();
$examId = $_GET['id'] ?? 0;

// 获取考试信息
$exam = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
$exam->execute([$examId, $_SESSION['user_id']]);
$exam = $exam->fetch();

if (!$exam) {
    header('Location: exam_list.php');
    exit;
}

// 获取题目
$questions = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY order_num");
$questions->execute([$examId]);
$questions = $questions->fetchAll();

$message = '';
$messageType = '';

// 处理更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 更新考试信息
        $duration = isset($_POST['duration']) && trim($_POST['duration']) !== '' 
            ? intval($_POST['duration']) 
            : null;
        $allowRetake = isset($_POST['allow_retake']) && $_POST['allow_retake'] == '1' ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE exams SET title = ?, description = ?, type = ?, start_time = ?, end_time = ?, duration = ?, allow_retake = ? WHERE id = ? AND teacher_id = ?");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'] ?? '',
            $_POST['type'],
            $_POST['start_time'],
            $_POST['end_time'],
            $duration,
            $allowRetake,
            $examId,
            $_SESSION['user_id']
        ]);
        
        // 删除旧题目
        $pdo->prepare("DELETE FROM questions WHERE exam_id = ?")->execute([$examId]);
        
        // 添加新题目
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            $orderNum = 1;
            foreach ($_POST['questions'] as $q) {
                if (empty($q['question_text'])) continue;
                
                $options = null;
                if (in_array($q['question_type'], ['single_choice', 'multiple_choice'])) {
                    if (isset($q['options'])) {
                        if (is_string($q['options'])) {
                            $options = $q['options'];
                        } else {
                            $options = json_encode($q['options'], JSON_UNESCAPED_UNICODE);
                        }
                    }
                }
                
                $sectionLabel = trim($q['section_label'] ?? '') ?: null;
                
                $stmt = $pdo->prepare("INSERT INTO questions (exam_id, question_text, question_type, section_label, options, correct_answer, score, order_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $examId,
                    $q['question_text'],
                    $q['question_type'],
                    $sectionLabel,
                    $options,
                    $q['correct_answer'] ?? '',
                    $q['score'],
                    $orderNum++
                ]);
            }
        }
        
        $pdo->commit();
        $message = '修改成功！';
        $messageType = 'success';
        
        // 重新获取数据
        $exam = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
        $exam->execute([$examId, $_SESSION['user_id']]);
        $exam = $exam->fetch();
        
        $questions = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY order_num");
        $questions->execute([$examId]);
        $questions = $questions->fetchAll();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '修改失败：' . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改考试/作业</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .question-item {
            background: #f8f9fa;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .option-item {
            display: flex;
            align-items: center;
            margin: 8px 0;
        }
        .option-item input[type="text"] {
            flex: 1;
            margin-left: 10px;
        }
        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('exam_list', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>修改考试/作业</h1>
                    <p>编辑考试/作业的基本信息和题目内容，保存后立即生效。</p>
                    <div class="hero-actions">
                        <a href="exam_list.php" class="btn btn-secondary btn-small">返回列表</a>
                    </div>
                </div>
            </section>
            
            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
            <form method="POST" id="examForm">
                <div class="form-group">
                    <label>标题：</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($exam['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>描述：</label>
                    <textarea name="description"><?php echo htmlspecialchars($exam['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>类型：</label>
                    <select name="type" required>
                        <option value="exam" <?php echo $exam['type'] === 'exam' ? 'selected' : ''; ?>>考试</option>
                        <option value="homework" <?php echo $exam['type'] === 'homework' ? 'selected' : ''; ?>>作业</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>开始时间：</label>
                    <input type="datetime-local" name="start_time" value="<?php echo date('Y-m-d\TH:i', strtotime($exam['start_time'])); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>结束时间：</label>
                    <input type="datetime-local" name="end_time" value="<?php echo date('Y-m-d\TH:i', strtotime($exam['end_time'])); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>时长（分钟，选填）：</label>
                    <input type="number" name="duration" min="1" value="<?php echo $exam['duration'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="allow_retake" value="1" <?php echo $exam['allow_retake'] ? 'checked' : ''; ?>>
                        允许再次考试（学生提交后可以重新答题）
                    </label>
                </div>
                
                <h3>题目列表</h3>
                <div id="questionsContainer"></div>
                <button type="button" class="btn btn-secondary" onclick="addQuestion()">添加题目</button>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">保存修改</button>
                </div>
            </form>
        </div>
        </main>
    </div>
    
    <script>
        let questionIndex = 0;
        const existingQuestions = <?php echo json_encode($questions, JSON_UNESCAPED_UNICODE); ?>;
        
        function addQuestion(questionData = null) {
            const container = document.getElementById('questionsContainer');
            const div = document.createElement('div');
            div.className = 'question-item';
            div.id = 'question-' + questionIndex;
            
            const isNew = !questionData;
            const q = questionData || {
                question_text: '',
                question_type: 'single_choice',
                score: 5,
                correct_answer: '',
                options: null
            };
            
            div.innerHTML = `
                <div class="form-group">
                    <label>标题/序号（可选）：</label>
                    <input type="text" name="questions[${questionIndex}][section_label]" placeholder="如：一、选择题" value="${escapeHtml(q.section_label || '')}">
                </div>
                <div class="question-header">
                    <strong>题目 ${questionIndex + 1}</strong>
                    <button type="button" class="btn-remove" onclick="removeQuestion(${questionIndex})">删除</button>
                </div>
                <div class="form-group">
                    <label>题目内容：</label>
                    <textarea name="questions[${questionIndex}][question_text]" required>${escapeHtml(q.question_text || '')}</textarea>
                </div>
                <div class="form-group">
                    <label>题型：</label>
                    <select name="questions[${questionIndex}][question_type]" onchange="changeQuestionType(${questionIndex}, this.value)">
                        <option value="single_choice" ${q.question_type === 'single_choice' ? 'selected' : ''}>单选题</option>
                        <option value="multiple_choice" ${q.question_type === 'multiple_choice' ? 'selected' : ''}>多选题</option>
                        <option value="judge" ${q.question_type === 'judge' ? 'selected' : ''}>判断题</option>
                        <option value="fill_blank" ${q.question_type === 'fill_blank' ? 'selected' : ''}>填空题</option>
                        <option value="essay" ${q.question_type === 'essay' ? 'selected' : ''}>主观题</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>分值：</label>
                    <input type="number" name="questions[${questionIndex}][score]" step="0.01" min="0" value="${q.score || 5}" required>
                </div>
                <div id="options-${questionIndex}"></div>
                <div id="answer-${questionIndex}"></div>
            `;
            
            container.appendChild(div);
            changeQuestionType(questionIndex, q.question_type, q);
            questionIndex++;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function changeQuestionType(index, type, questionData = null) {
            const optionsDiv = document.getElementById('options-' + index);
            const answerDiv = document.getElementById('answer-' + index);
            
            optionsDiv.innerHTML = '';
            answerDiv.innerHTML = '';
            
            if (type === 'single_choice' || type === 'multiple_choice') {
                let optionsText = '';
                let correctAnswer = questionData ? (questionData.correct_answer || '') : '';
                
                if (questionData && questionData.options) {
                    const options = typeof questionData.options === 'string' 
                        ? JSON.parse(questionData.options) 
                        : questionData.options;
                    optionsText = Object.entries(options).map(([key, value]) => `${key}. ${value}`).join('\n');
                }
                
                optionsDiv.innerHTML = `
                    <div class="form-group">
                        <label>选项（每行一个）：</label>
                        <textarea name="questions[${index}][options_text]" rows="4" placeholder="A. 选项1&#10;B. 选项2&#10;C. 选项3&#10;D. 选项4">${escapeHtml(optionsText)}</textarea>
                    </div>
                    <div class="form-group">
                        <label>正确答案（${type === 'single_choice' ? '单选' : '多选，用逗号分隔'}）：</label>
                        <input type="text" name="questions[${index}][correct_answer]" placeholder="${type === 'single_choice' ? '如：A' : '如：A,B'}" value="${escapeHtml(correctAnswer)}" required>
                    </div>
                `;
            } else if (type === 'judge') {
                const correctAnswer = questionData ? (questionData.correct_answer || '正确') : '正确';
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
                const correctAnswer = questionData ? (questionData.correct_answer || '') : '';
                answerDiv.innerHTML = `
                    <div class="form-group">
                        <label>正确答案（可选）：</label>
                        <input type="text" name="questions[${index}][correct_answer]" value="${escapeHtml(correctAnswer)}">
                    </div>
                `;
            }
        }
        
        function removeQuestion(index) {
            document.getElementById('question-' + index).remove();
        }
        
        // 处理表单提交，将选项文本转换为数组
        document.getElementById('examForm').addEventListener('submit', function(e) {
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
                    
                    // 创建隐藏输入
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = `questions[${index}][options]`;
                    hiddenInput.value = JSON.stringify(options);
                    input.parentNode.appendChild(hiddenInput);
                }
            });
        });
        
        // 页面加载时添加已有题目
        window.onload = function() {
            if (existingQuestions.length > 0) {
                existingQuestions.forEach(function(q) {
                    addQuestion(q);
                });
            } else {
                addQuestion();
            }
        };
    </script>
</body>
</html>

