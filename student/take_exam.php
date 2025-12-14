<?php
require_once '../config.php';
checkRole('student');

$pdo = getDB();
$examId = $_GET['id'] ?? 0;

// 获取考试信息
$exam = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND status = 'published'");
$exam->execute([$examId]);
$exam = $exam->fetch();

if (!$exam) {
    header('Location: exam_list.php');
    exit;
}

// 检查时间
$now = time();
$startTime = strtotime($exam['start_time']);
$endTime = strtotime($exam['end_time']);

if ($now < $startTime) {
    die('考试尚未开始');
}

if ($now > $endTime) {
    die('考试已结束');
}

// 检查是否已提交（如果允许再次考试，则可以继续）
$submitted = $pdo->prepare("SELECT * FROM scores WHERE exam_id = ? AND student_id = ?");
$submitted->execute([$examId, $_SESSION['user_id']]);
$submitted = $submitted->fetch();

$allowRetake = $exam['allow_retake'] ?? 0;
if ($submitted && !$allowRetake) {
    header('Location: exam_list.php');
    exit;
}

// 获取题目
$questions = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY order_num");
$questions->execute([$examId]);
$questions = $questions->fetchAll();

// 获取已有答案
$existingAnswers = [];
$answers = $pdo->prepare("SELECT * FROM answers WHERE exam_id = ? AND student_id = ?");
$answers->execute([$examId, $_SESSION['user_id']]);
foreach ($answers->fetchAll() as $ans) {
    $existingAnswers[$ans['question_id']] = $ans['answer_text'];
}

$message = '';
$messageType = '';

// 处理提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 如果是再次考试，清除之前的答案和成绩
        if ($allowRetake && $submitted) {
            $pdo->prepare("DELETE FROM answers WHERE exam_id = ? AND student_id = ?")->execute([$examId, $_SESSION['user_id']]);
            $pdo->prepare("DELETE FROM scores WHERE exam_id = ? AND student_id = ?")->execute([$examId, $_SESSION['user_id']]);
        }
        
        // 获取所有题目
        $allQuestions = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
        $allQuestions->execute([$examId]);
        $allQuestions = $allQuestions->fetchAll();
        
        $autoScore = 0;
        
        // 保存答案并自动批改选择题和判断题
        foreach ($_POST['answers'] as $questionId => $answerText) {
            // 处理多选题（数组格式）
            if (is_array($answerText)) {
                $answerText = implode(',', array_map('trim', $answerText));
            } else {
                $answerText = trim($answerText);
            }
            
            if (empty($answerText)) continue;
            
            // 查找对应的题目
            $question = null;
            foreach ($allQuestions as $q) {
                if ($q['id'] == $questionId) {
                    $question = $q;
                    break;
                }
            }
            
            if (!$question) continue;
            
            // 保存答案
            $stmt = $pdo->prepare("INSERT INTO answers (exam_id, question_id, student_id, answer_text) 
                                   VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE answer_text = ?");
            $stmt->execute([$examId, $questionId, $_SESSION['user_id'], $answerText, $answerText]);
            
            // 自动批改选择题和判断题
            if (in_array($question['question_type'], ['single_choice', 'multiple_choice', 'judge'])) {
                $score = 0;
                $studentAnswer = trim($answerText);
                $correctAnswer = trim($question['correct_answer']);
                
                if ($question['question_type'] === 'judge') {
                    // 判断题：直接比较
                    if (strcasecmp($studentAnswer, $correctAnswer) === 0) {
                        $score = $question['score'];
                    }
                } elseif ($question['question_type'] === 'single_choice') {
                    // 单选题：直接比较（忽略大小写）
                    if (strcasecmp($studentAnswer, $correctAnswer) === 0) {
                        $score = $question['score'];
                    }
                } else {
                    // 多选题：比较数组（排序后比较）
                    $studentAnswers = array_map('trim', explode(',', $studentAnswer));
                    $correctAnswers = array_map('trim', explode(',', $correctAnswer));
                    // 转换为大写后排序比较
                    $studentAnswers = array_map('strtoupper', $studentAnswers);
                    $correctAnswers = array_map('strtoupper', $correctAnswers);
                    sort($studentAnswers);
                    sort($correctAnswers);
                    if ($studentAnswers === $correctAnswers) {
                        $score = $question['score'];
                    }
                }
                
                // 更新答案的分数和自动批改标记
                $updateStmt = $pdo->prepare("UPDATE answers SET score = ?, is_auto_graded = 1, graded_at = NOW() 
                                           WHERE exam_id = ? AND question_id = ? AND student_id = ?");
                $updateStmt->execute([$score, $examId, $questionId, $_SESSION['user_id']]);
                
                $autoScore += $score;
            }
        }
        
        // 创建或更新成绩记录
        $scoreRecord = $pdo->prepare("SELECT * FROM scores WHERE exam_id = ? AND student_id = ?");
        $scoreRecord->execute([$examId, $_SESSION['user_id']]);
        $scoreRecord = $scoreRecord->fetch();
        
        if ($scoreRecord) {
            // 更新自动批改分数
            $updateScore = $pdo->prepare("UPDATE scores SET auto_score = ?, status = 'submitted' WHERE exam_id = ? AND student_id = ?");
            $updateScore->execute([$autoScore, $examId, $_SESSION['user_id']]);
        } else {
            // 创建新记录
            $insertScore = $pdo->prepare("INSERT INTO scores (exam_id, student_id, auto_score, status) VALUES (?, ?, ?, 'submitted')");
            $insertScore->execute([$examId, $_SESSION['user_id'], $autoScore]);
        }
        
        $pdo->commit();
        $message = $allowRetake && $submitted ? '重新提交成功！' : '提交成功！';
        $messageType = 'success';
        
        // 延迟跳转
        header("refresh:2;url=exam_list.php");
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '提交失败：' . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>答题 - <?php echo htmlspecialchars($exam['title']); ?></title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .question-block {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .question-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        .question-meta {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .question-section-label {
            display: inline-flex;
            padding: 4px 12px;
            border-radius: 999px;
            background: #e0e7ff;
            color: #4338ca;
            font-weight: 600;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <?php require_once 'student_sidebar.php'; renderStudentSidebar('exam_list', getCurrentUser()); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
                    <p>请在规定时间内完成所有题目，未提交系统将自动提交。</p>
                </div>
                <div>
                    <div class="hero-actions">
                        <a href="exam_list.php" class="btn btn-secondary btn-small">返回</a>
                    </div>
                </div>
            </section>

            <div class="container">
        <?php if ($message): ?>
            <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                <?php echo htmlspecialchars($message); ?>
                <?php if ($messageType === 'success'): ?>
                    <p>2秒后自动跳转...</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <p><strong>描述：</strong><?php echo htmlspecialchars($exam['description']); ?></p>
            <?php if ($exam['duration']): ?>
                <p><strong>时长：</strong><?php echo $exam['duration']; ?> 分钟</p>
            <?php endif; ?>
        </div>
        
        <form method="POST" id="examForm">
            <?php 
            $lastSectionLabel = '';
            foreach ($questions as $index => $q): 
                // 如果当前题目的大标题与前一个不同，显示大标题
                if (!empty($q['section_label']) && $q['section_label'] !== $lastSectionLabel):
                    $lastSectionLabel = $q['section_label'];
            ?>
                <div class="section-header" style="margin: 20px 0 10px 0; padding: 12px 20px; background: linear-gradient(135deg, #e0e7ff, #c7d2fe); border-left: 4px solid #4338ca; border-radius: 8px;">
                    <h3 style="margin: 0; color: #4338ca; font-size: 18px; font-weight: 600;"><?php echo htmlspecialchars($q['section_label']); ?></h3>
                </div>
            <?php 
                elseif (empty($q['section_label'])):
                    $lastSectionLabel = '';
                endif;
            ?>
                <div class="question-block">
                    <div class="question-title">
                        第<?php echo $index + 1; ?>题
                    </div>
                    <div class="question-meta">
                        <?php 
                        $typeMap = [
                            'single_choice' => '单选题',
                            'multiple_choice' => '多选题',
                            'judge' => '判断题',
                            'fill_blank' => '填空题',
                            'essay' => '主观题',
                            'big_question' => '一大题',
                            'answer_question' => '二答题'
                        ];
                        echo $typeMap[$q['question_type']] ?? '主观题';
                        ?> | 分值：<?php echo $q['score']; ?> 分
                    </div>
                    <p class="question-text"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></p>
                    
                    <?php if (in_array($q['question_type'], ['single_choice', 'multiple_choice'])): ?>
                        <?php $options = json_decode($q['options'], true); ?>
                        <?php if ($options): ?>
                            <ul class="options-list">
                                <?php 
                                $existingAnswer = $existingAnswers[$q['id']] ?? '';
                                $existingAnswersArray = $q['question_type'] === 'multiple_choice' 
                                    ? array_map('trim', explode(',', $existingAnswer)) 
                                    : [];
                                ?>
                                <?php foreach ($options as $key => $value): ?>
                                    <li>
                                        <?php if ($q['question_type'] === 'single_choice'): ?>
                                            <input type="radio" 
                                                   name="answers[<?php echo $q['id']; ?>]" 
                                                   value="<?php echo htmlspecialchars($key); ?>"
                                                   <?php echo ($existingAnswer === $key) ? 'checked' : ''; ?>>
                                        <?php else: ?>
                                            <input type="checkbox" 
                                                   name="answers[<?php echo $q['id']; ?>][]" 
                                                   value="<?php echo htmlspecialchars($key); ?>"
                                                   <?php echo in_array($key, $existingAnswersArray) ? 'checked' : ''; ?>>
                                        <?php endif; ?>
                                        <label><?php echo $key; ?>. <?php echo htmlspecialchars($value); ?></label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php elseif ($q['question_type'] === 'judge'): ?>
                        <div class="judge-options">
                            <?php $existingAnswer = $existingAnswers[$q['id'] ?? '']; ?>
                            <label>
                                <input type="radio" 
                                       name="answers[<?php echo $q['id']; ?>]" 
                                       value="正确"
                                       <?php echo ($existingAnswer === '正确') ? 'checked' : ''; ?>>
                                正确
                            </label>
                            <label>
                                <input type="radio" 
                                       name="answers[<?php echo $q['id']; ?>]" 
                                       value="错误"
                                       <?php echo ($existingAnswer === '错误') ? 'checked' : ''; ?>>
                                错误
                            </label>
                        </div>
                    <?php else: ?>
                        <textarea name="answers[<?php echo $q['id']; ?>]" 
                                  rows="5" 
                                  class="answer-textarea"
                                  placeholder="请输入答案"><?php echo htmlspecialchars($existingAnswers[$q['id']] ?? ''); ?></textarea>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-large">提交答案</button>
            </div>
        </form>
            </div>
        </main>
    </div>
    
    <script>
        // 处理多选题提交（将数组格式转换为逗号分隔的字符串）
        document.getElementById('examForm').addEventListener('submit', function(e) {
            // 收集所有多选题
            const questionIds = new Set();
            document.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {
                const match = checkbox.name.match(/\[(\d+)\]/);
                if (match) {
                    questionIds.add(match[1]);
                }
            });
            
            // 为每个多选题创建隐藏输入（如果还没有）
            questionIds.forEach(function(questionId) {
                const name = 'answers[' + questionId + ']';
                // 移除已存在的隐藏输入
                const existing = document.querySelector('input[type="hidden"][name="' + name + '"]');
                if (existing) {
                    existing.remove();
                }
                
                const checked = document.querySelectorAll('input[name="answers[' + questionId + '][]"]:checked');
                if (checked.length > 0) {
                    const values = Array.from(checked).map(cb => cb.value).join(',');
                    // 创建隐藏输入
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = name;
                    hidden.value = values;
                    document.getElementById('examForm').appendChild(hidden);
                }
            });
        });
        
        // 倒计时
        <?php if ($exam['duration']): ?>
        let duration = <?php echo $exam['duration']; ?> * 60; // 转换为秒
        let endTime = <?php echo $endTime; ?>;
        
        function updateCountdown() {
            const now = Math.floor(Date.now() / 1000);
            const remaining = endTime - now;
            
            if (remaining <= 0) {
                document.getElementById('countdown').textContent = '时间已到';
                document.getElementById('examForm').submit();
                return;
            }
            
            const hours = Math.floor(remaining / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;
            
            let text = '';
            if (hours > 0) text += hours + '小时';
            if (minutes > 0) text += minutes + '分钟';
            text += seconds + '秒';
            
            document.getElementById('countdown').textContent = text;
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>
    </script>
</body>
</html>

