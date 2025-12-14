<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$message = '';
$messageType = '';
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $duration = isset($_POST['duration']) && trim($_POST['duration']) !== '' 
            ? intval($_POST['duration']) 
            : null;
        
        $allowRetake = isset($_POST['allow_retake']) && $_POST['allow_retake'] == '1' ? 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO exams (title, description, type, teacher_id, start_time, end_time, duration, allow_retake, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published')");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'] ?? '',
            $_POST['type'],
            $_SESSION['user_id'],
            $_POST['start_time'],
            $_POST['end_time'],
            $duration,
            $allowRetake
        ]);
        
        $examId = $pdo->lastInsertId();
        
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            $questions = array_values($_POST['questions']);
            usort($questions, function($a, $b) {
                $orderA = isset($a['order']) ? intval($a['order']) : PHP_INT_MAX;
                $orderB = isset($b['order']) ? intval($b['order']) : PHP_INT_MAX;
                return $orderA <=> $orderB;
            });
            
            $orderNum = 1;
            foreach ($questions as $q) {
                $questionText = trim($q['question_text'] ?? '');
                if ($questionText === '') {
                    continue;
                }
                
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
                    $questionText,
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
        $message = '考试/作业创建成功！';
        $messageType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '创建失败：' . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发布考试/作业</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .form-grid .form-group {
            margin-bottom: 0;
        }
        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 30px 0 10px;
        }
        .question-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .question-item {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            padding: 20px;
            margin: 16px 0;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            gap: 12px;
        }
        .question-header h4 {
            margin: 0;
            font-size: 16px;
            color: #0f172a;
        }
        .question-actions {
            display: flex;
            gap: 8px;
        }
        .question-actions button {
            border: none;
            background: #e2e8f0;
            color: #475569;
            border-radius: 8px;
            padding: 6px 10px;
            cursor: pointer;
            transition: all .2s ease;
        }
        .question-actions button:hover {
            background: #cbd5f5;
            color: #1e3a8a;
        }
        .btn-remove {
            background: #fee2e2;
            color: #b91c1c;
        }
        .btn-remove:hover {
            background: #fecaca;
        }
        .section-label-input {
            border: 1px dashed #cbd5f5;
        }
        .hint {
            color: #64748b;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('create_exam', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>发布考试/作业</h1>
                    <p>灵活设置考试或作业时间、题型与评分策略，并可一次性录入整套题目。</p>
                    <div class="hero-actions">
                        <a href="exam_list.php" class="btn btn-muted btn-small">查看我的考试</a>
                    </div>
                </div>
                <div>
                    <p>小提示：题目支持单选、多选、判断、填空、主观题；如需后续复用，可在题库中创建后导入。</p>
                </div>
            </section>
            
            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST" id="examForm">
                    <div class="section-title" style="margin-top:0;">
                        <div>
                            <h2>基础信息</h2>
                            <p class="hint">设置考试/作业名称、时间与提交规则。</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>标题：</label>
                        <input type="text" name="title" placeholder="如：高一数学阶段测试" required>
                    </div>
                    
                    <div class="form-group">
                        <label>描述：</label>
                        <textarea name="description" placeholder="向学生说明考试重点、资源或注意事项"></textarea>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>类型：</label>
                            <select name="type" required>
                                <option value="exam">考试</option>
                                <option value="homework">作业</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>开始时间：</label>
                            <input type="datetime-local" name="start_time" required>
                        </div>
                        <div class="form-group">
                            <label>结束时间：</label>
                            <input type="datetime-local" name="end_time" required>
                        </div>
                        <div class="form-group">
                            <label>时长（分钟，选填）：</label>
                            <input type="number" name="duration" min="1" placeholder="不限则留空">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top:12px;">
                        <label>
                            <input type="checkbox" name="allow_retake" value="1">
                            允许再次考试（学生可在提交后重新作答）
                        </label>
                    </div>
                    
                    <div class="section-title">
                        <div>
                            <h2>题目结构</h2>
                            <p class="hint">支持添加标题大题（如“一、选择题”）及普通题目，可上下拖动调整顺序。</p>
                        </div>
                        <div class="question-toolbar">
                            <button type="button" class="btn btn-secondary btn-small" onclick="addQuestion()">添加题目</button>
                            <button type="button" class="btn btn-muted btn-small" onclick="applySectionLabels()">自动生成标题序号</button>
                        </div>
                    </div>
                    <p class="hint" style="margin-top:-8px;">提示：标题/序号字段可填写“一、选择题”“二、填空题”等，也可留空。</p>
                    <div id="questionsContainer"></div>
                    
                    <div style="margin-top: 30px; text-align:right;">
                        <button type="submit" class="btn btn-primary">发布</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        let questionIndex = 0;
        const cnNums = ['零','一','二','三','四','五','六','七','八','九'];
        
        function numberToChinese(num) {
            if (num <= 10) {
                if (num === 10) return '十';
                return cnNums[num];
            }
            if (num < 20) {
                return '十' + cnNums[num % 10];
            }
            if (num < 100) {
                const tens = Math.floor(num / 10);
                const units = num % 10;
                return cnNums[tens] + '十' + (units === 0 ? '' : cnNums[units]);
            }
            return num;
        }
        
        function addQuestion(defaultType = 'single_choice') {
            const container = document.getElementById('questionsContainer');
            const div = document.createElement('div');
            div.className = 'question-item';
            div.id = 'question-' + questionIndex;
            div.dataset.index = questionIndex;
            
            div.innerHTML = `
                <div class="form-group">
                    <label>标题/序号（可选）：</label>
                    <input type="text" class="section-label-input" name="questions[${questionIndex}][section_label]" placeholder="如：一、选择题">
                </div>
                <div class="question-header">
                    <h4 class="question-title">题目</h4>
                    <div class="question-actions">
                        <button type="button" onclick="moveQuestion(${questionIndex}, 'up')" title="上移">↑</button>
                        <button type="button" onclick="moveQuestion(${questionIndex}, 'down')" title="下移">↓</button>
                        <button type="button" class="btn-remove" onclick="removeQuestion(${questionIndex})">删除</button>
                    </div>
                </div>
                <input type="hidden" name="questions[${questionIndex}][order]" class="question-order-input" value="0">
                <div class="form-group">
                    <label>题目内容：</label>
                    <textarea name="questions[${questionIndex}][question_text]" placeholder="请输入题干或说明" required></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>题型：</label>
                        <select name="questions[${questionIndex}][question_type]" onchange="changeQuestionType(${questionIndex}, this.value)">
                            <option value="single_choice">单选题</option>
                            <option value="multiple_choice">多选题</option>
                            <option value="judge">判断题</option>
                            <option value="fill_blank">填空题</option>
                            <option value="essay">主观题</option>
                            <option value="big_question">一大题</option>
                            <option value="answer_question">二答题</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>分值：</label>
                        <input type="number" name="questions[${questionIndex}][score]" step="0.01" min="0" value="5" required>
                    </div>
                </div>
                <div id="options-${questionIndex}"></div>
                <div id="answer-${questionIndex}"></div>
            `;
            
            container.appendChild(div);
            changeQuestionType(questionIndex, defaultType);
            refreshQuestionMeta();
            questionIndex++;
        }
        
        function changeQuestionType(index, type) {
            const optionsDiv = document.getElementById('options-' + index);
            const answerDiv = document.getElementById('answer-' + index);
            
            if (!optionsDiv || !answerDiv) return;
            
            optionsDiv.innerHTML = '';
            answerDiv.innerHTML = '';
            
            if (type === 'single_choice' || type === 'multiple_choice') {
                optionsDiv.innerHTML = `
                    <div class="form-group">
                        <label>选项（每行一个）：</label>
                        <textarea name="questions[${index}][options_text]" rows="4" placeholder="A. 选项1&#10;B. 选项2&#10;C. 选项3&#10;D. 选项4"></textarea>
                    </div>
                    <div class="form-group">
                        <label>正确答案（${type === 'single_choice' ? '单选' : '多选，用逗号分隔'}）：</label>
                        <input type="text" name="questions[${index}][correct_answer]" placeholder="${type === 'single_choice' ? '如：A' : '如：A,B'}" required>
                    </div>
                `;
            } else if (type === 'judge') {
                answerDiv.innerHTML = `
                    <div class="form-group">
                        <label>正确答案：</label>
                        <select name="questions[${index}][correct_answer]" required>
                            <option value="正确">正确</option>
                            <option value="错误">错误</option>
                        </select>
                    </div>
                `;
            } else if (type === 'fill_blank') {
                answerDiv.innerHTML = `
                    <div class="form-group">
                        <label>正确答案（可选）：</label>
                        <input type="text" name="questions[${index}][correct_answer]" placeholder="如有多个空，用逗号分隔">
                    </div>
                `;
            } else {
                answerDiv.innerHTML = `
                    <div class="form-group">
                        <label>参考答案/评分说明（可选）：</label>
                        <textarea name="questions[${index}][correct_answer]" rows="3" placeholder="可填写评分标准、参考答案或步骤提示"></textarea>
                    </div>
                `;
            }
        }
        
        function removeQuestion(index) {
            const target = document.getElementById('question-' + index);
            if (target) {
                target.remove();
                refreshQuestionMeta();
            }
        }
        
        function moveQuestion(index, direction) {
            const card = document.getElementById('question-' + index);
            if (!card) return;
            const container = document.getElementById('questionsContainer');
            if (direction === 'up' && card.previousElementSibling) {
                container.insertBefore(card, card.previousElementSibling);
            }
            if (direction === 'down' && card.nextElementSibling) {
                const next = card.nextElementSibling.nextElementSibling;
                container.insertBefore(card, next);
            }
            refreshQuestionMeta();
        }
        
        function refreshQuestionMeta() {
            const cards = document.querySelectorAll('#questionsContainer .question-item');
            cards.forEach((card, idx) => {
                const title = card.querySelector('.question-title');
                if (title) {
                    title.textContent = `题目 ${idx + 1}`;
                }
                const orderInput = card.querySelector('.question-order-input');
                if (orderInput) {
                    orderInput.value = idx + 1;
                }
            });
        }
        
        function applySectionLabels() {
            const cards = document.querySelectorAll('#questionsContainer .question-item');
            cards.forEach((card, idx) => {
                const input = card.querySelector('.section-label-input');
                if (input && !input.value.trim()) {
                    input.value = `${numberToChinese(idx + 1)}、`;
                }
            });
        }
        
        document.getElementById('examForm').addEventListener('submit', function() {
            refreshQuestionMeta();
            const optionTextareas = document.querySelectorAll('textarea[name*="[options_text]"]');
            optionTextareas.forEach(function(input) {
                const match = input.name.match(/\[(\d+)\]/);
                if (!match) return;
                const index = match[1];
                const optionsText = input.value.split('\n').filter(line => line.trim());
                const options = {};
                optionsText.forEach((line, i) => {
                    const matchLine = line.match(/^([A-Z])\.\s*(.+)$/);
                    if (matchLine) {
                        options[matchLine[1]] = matchLine[2].trim();
                    } else {
                        options[String.fromCharCode(65 + i)] = line.trim();
                    }
                });
                
                const parent = input.parentNode;
                if (!parent) return;
                const existing = parent.querySelector(`input[name="questions[${index}][options]"]`);
                if (existing) {
                    existing.remove();
                }
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = `questions[${index}][options]`;
                hiddenInput.value = JSON.stringify(options);
                parent.appendChild(hiddenInput);
            });
        });
        
        window.onload = function() {
            addQuestion();
        };
    </script>
</body>
</html>

