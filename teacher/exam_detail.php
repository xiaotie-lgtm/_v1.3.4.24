<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$user = getCurrentUser();
$examId = $_GET['id'] ?? 0;

$exam = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
$exam->execute([$examId, $_SESSION['user_id']]);
$exam = $exam->fetch();

if (!$exam) {
    header('Location: exam_list.php');
    exit;
}

$questions = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY order_num");
$questions->execute([$examId]);
$questions = $questions->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>考试详情</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('exam_list', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>考试详情</h1>
                    <p>查看考试/作业的完整信息，包括所有题目和正确答案。</p>
                    <div class="hero-actions">
                        <a href="exam_list.php" class="btn btn-secondary btn-small">返回列表</a>
                    </div>
                </div>
            </section>
            
            <div class="card">
            <div class="card-header">
                <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
            </div>
            <p><strong>类型：</strong><?php echo $exam['type'] === 'exam' ? '考试' : '作业'; ?></p>
            <p><strong>描述：</strong><?php echo htmlspecialchars($exam['description']); ?></p>
            <p><strong>开始时间：</strong><?php echo date('Y-m-d H:i', strtotime($exam['start_time'])); ?></p>
            <p><strong>结束时间：</strong><?php echo date('Y-m-d H:i', strtotime($exam['end_time'])); ?></p>
            <?php if ($exam['duration']): ?>
                <p><strong>时长：</strong><?php echo $exam['duration']; ?> 分钟</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>题目列表</h2>
            </div>
            <?php 
            $lastSectionLabel = '';
            foreach ($questions as $index => $q): 
                // 如果当前题目的大标题与前一个不同，显示大标题
                if (!empty($q['section_label']) && $q['section_label'] !== $lastSectionLabel):
                    $lastSectionLabel = $q['section_label'];
            ?>
                <div style="margin: 20px 0 10px 0; padding: 12px 20px; background: linear-gradient(135deg, #e0e7ff, #c7d2fe); border-left: 4px solid #4338ca; border-radius: 8px;">
                    <h3 style="margin: 0; color: #4338ca; font-size: 18px; font-weight: 600;"><?php echo htmlspecialchars($q['section_label']); ?></h3>
                </div>
            <?php 
                elseif (empty($q['section_label'])):
                    $lastSectionLabel = '';
                endif;
            ?>
                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
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
                        echo $typeMap[$q['question_type']] ?? '主观题';
                    ?>，<?php echo $q['score']; ?>分）：</strong></p>
                    <p><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></p>
                    
                    <?php if (in_array($q['question_type'], ['single_choice', 'multiple_choice'])): ?>
                        <?php $options = json_decode($q['options'], true); ?>
                        <?php if ($options): ?>
                            <ul class="options-list">
                                <?php foreach ($options as $key => $value): ?>
                                    <li><?php echo $key; ?>. <?php echo htmlspecialchars($value); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <p><strong>正确答案：</strong><?php echo htmlspecialchars($q['correct_answer']); ?></p>
                    <?php elseif ($q['question_type'] === 'judge'): ?>
                        <p><strong>正确答案：</strong><?php echo htmlspecialchars($q['correct_answer']); ?></p>
                    <?php elseif ($q['question_type'] === 'fill_blank' && $q['correct_answer']): ?>
                        <p><strong>参考答案：</strong><?php echo htmlspecialchars($q['correct_answer']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        </main>
    </div>
</body>
</html>

