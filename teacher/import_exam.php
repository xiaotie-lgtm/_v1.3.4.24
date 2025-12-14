<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$message = '';
$messageType = '';
$user = getCurrentUser();
$parsedQuestions = [];
$examData = [
    'title' => '',
    'description' => '',
    'type' => 'exam',
    'start_time' => date('Y-m-d\TH:i'),
    'end_time' => date('Y-m-d\TH:i', strtotime('+1 day')),
    'duration' => '',
    'allow_retake' => 0
];

/**
 * 解析 docx 文件并抽取题目
 */
function parseDocxQuestions($filePath) {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== TRUE) {
        throw new Exception('无法打开Word文件');
    }

    $xmlContent = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xmlContent === false) {
        throw new Exception('无法读取Word内容，请确认文件不是加密格式');
    }

    // 将段落结束替换为换行
    $xmlContent = str_replace(['</w:p>', '</w:tab>'], "\n", $xmlContent);
    $text = strip_tags($xmlContent);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    return parseQuestionsFromText($text);
}

/**
 * 根据纯文本解析题目
 */
function parseQuestionsFromText($text) {
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $questions = [];
    $current = null;
    $order = 1;

    $typeMap = [
        '单选' => 'single_choice',
        '多选' => 'multiple_choice',
        '判断' => 'judge',
        '填空' => 'fill_blank',
        '主观' => 'essay'
    ];

    // 结束一题并进行类型推断
    $flushQuestion = function() use (&$current, &$questions, &$order) {
        if ($current !== null) {
            // 类型自动推断
            if (empty($current['question_type'])) {
                // 判断题：答案是 对/错/正确/错误 或 题干中有“对/错”且无选项
                if (!empty($current['correct_answer']) && preg_match('/^(对|错|正确|错误)$/u', $current['correct_answer'])) {
                    $current['question_type'] = 'judge';
                } elseif (strpos($current['question_text'], '对') !== false || strpos($current['question_text'], '错') !== false) {
                    $current['question_type'] = 'judge';
                }

                // 有选项的题目：根据答案判断单选/多选
                if (empty($current['question_type']) && !empty($current['options'])) {
                    if (!empty($current['correct_answer']) && strpos($current['correct_answer'], ',') !== false) {
                        $current['question_type'] = 'multiple_choice';
                    } else {
                        $current['question_type'] = 'single_choice';
                    }
                }

                // 填空题：题干中有下划线或“______”等
                if (empty($current['question_type']) && preg_match('/(_{3,}|＿{3,}|___+|____+)/u', $current['question_text'])) {
                    $current['question_type'] = 'fill_blank';
                }

                // 默认主观题
                if (empty($current['question_type'])) {
                    $current['question_type'] = 'essay';
                }
            }

            if (empty($current['score'])) {
                $current['score'] = 5;
            }
            if (empty($current['question_text'])) {
                $current['question_text'] = '（题目内容未识别）';
            }
            if (empty($current['correct_answer'])) {
                if ($current['question_type'] === 'judge') {
                    $current['correct_answer'] = '正确';
                } elseif ($current['question_type'] === 'multiple_choice') {
                    $current['correct_answer'] = 'A,B';
                } elseif ($current['question_type'] === 'single_choice') {
                    $current['correct_answer'] = 'A';
                } else {
                    $current['correct_answer'] = '';
                }
            }

            // 拼接选项文本
            if (!empty($current['options']) && is_array($current['options'])) {
                $optionsText = [];
                foreach ($current['options'] as $key => $value) {
                    $optionsText[] = "{$key}. {$value}";
                }
                $current['options_text'] = implode("\n", $optionsText);
            } else {
                $current['options_text'] = '';
            }

            $current['order_num'] = $order++;
            $questions[] = $current;
        }
        $current = null;
    };

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // 尝试识别“题目开始”行：
        // 支持：1. [单选] 题目内容(5分)、1、题目内容（5分）、1. 题目内容 等
        if (preg_match('/^\d+[\.、]?\s*(.+)$/u', $line, $m)) {
            $flushQuestion();

            $body = $m[1];
            $typeLabel = null;
            $score = null;

            // 可选的 [类型] 标记
            if (preg_match('/\[(单选|多选|判断|填空|主观)\]/u', $body, $mType)) {
                $typeLabel = $mType[1];
                $body = str_replace($mType[0], '', $body);
            }

            // 可选的 (5分) / （5分） 标记
            if (preg_match('/[（(](\d+(?:\.\d+)?)\s*分?[)）]/u', $body, $mScore)) {
                $score = floatval($mScore[1]);
                $body = str_replace($mScore[0], '', $body);
            }

            $body = trim($body);

            $current = [
                'question_text'   => $body,
                'question_type'   => $typeLabel ? ($typeMap[$typeLabel] ?? null) : null,
                'score'           => $score,
                'options'         => [],
                'correct_answer'  => ''
            ];
            continue;
        }

        if ($current === null) {
            // 未进入任何题目块时，忽略零散文本
            continue;
        }

        // 解析选项：A. 选项 或 A、选项
        if (preg_match('/^([A-Z])[\.、]\s*(.+)$/u', $line, $optMatches)) {
            $letter = strtoupper($optMatches[1]);
            $current['options'][$letter] = trim($optMatches[2]);
            continue;
        }

        // 解析“答案:”行
        if (preg_match('/^(答案|正确答案)\s*[:：]\s*(.+)$/u', $line, $answerMatches)) {
            $current['correct_answer'] = strtoupper(str_replace(['，', ' '], [',', ''], trim($answerMatches[2])));
            continue;
        }

        // 解析“分值:”行
        if (preg_match('/^(分值|分数)\s*[:：]\s*(\d+(?:\.\d+)?)/u', $line, $scoreMatches)) {
            $current['score'] = floatval($scoreMatches[2]);
            continue;
        }

        // 其他内容视为题干补充
        $current['question_text'] .= "\n" . $line;
    }

    $flushQuestion();

    return $questions;
}

/**
 * 将选项文本转换为数组
 */
function parseOptionsText($optionsText) {
    $options = [];
    $lines = preg_split("/\r\n|\n|\r/", $optionsText);
    $index = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (preg_match('/^([A-Z])[\.、]\s*(.+)$/u', $line, $matches)) {
            $options[$matches[1]] = trim($matches[2]);
        } else {
            $letter = chr(65 + $index);
            $options[$letter] = $line;
        }
        $index++;
    }
    return $options;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_exam'])) {
        // 处理创建考试
        $examData['title'] = trim($_POST['title']);
        $examData['description'] = trim($_POST['description'] ?? '');
        $examData['type'] = $_POST['type'] ?? 'exam';
        $examData['start_time'] = $_POST['start_time'];
        $examData['end_time'] = $_POST['end_time'];
        $examData['duration'] = isset($_POST['duration']) && $_POST['duration'] !== '' ? intval($_POST['duration']) : null;
        $examData['allow_retake'] = isset($_POST['allow_retake']) ? 1 : 0;

        $parsedQuestions = $_POST['questions'] ?? [];

        if (empty($examData['title']) || empty($examData['start_time']) || empty($examData['end_time'])) {
            $message = '请填写考试的标题、开始时间和结束时间';
            $messageType = 'error';
        } elseif (empty($parsedQuestions)) {
            $message = '题目列表为空，请重新导入Word文件';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO exams (title, description, type, teacher_id, start_time, end_time, duration, allow_retake, status) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published')");
                $stmt->execute([
                    $examData['title'],
                    $examData['description'],
                    $examData['type'],
                    $_SESSION['user_id'],
                    $examData['start_time'],
                    $examData['end_time'],
                    $examData['duration'],
                    $examData['allow_retake']
                ]);

                $examId = $pdo->lastInsertId();
                $orderNum = 1;

                foreach ($parsedQuestions as $q) {
                    if (empty($q['question_text'])) continue;

                    $questionType = $q['question_type'] ?? 'single_choice';
                    $score = isset($q['score']) ? floatval($q['score']) : 5;
                    $correctAnswer = trim($q['correct_answer'] ?? '');

                    $options = null;
                    if (in_array($questionType, ['single_choice', 'multiple_choice'])) {
                        $optionsText = $q['options_text'] ?? '';
                        $optionsArr = parseOptionsText($optionsText);
                        $options = json_encode($optionsArr, JSON_UNESCAPED_UNICODE);
                    }

                    $sectionLabel = trim($q['section_label'] ?? '') ?: null;

                    $stmtQ = $pdo->prepare("INSERT INTO questions (exam_id, question_text, question_type, section_label, options, correct_answer, score, order_num) 
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtQ->execute([
                        $examId,
                        $q['question_text'],
                        $questionType,
                        $sectionLabel,
                        $options,
                        $correctAnswer,
                        $score,
                        $orderNum++
                    ]);
                }

                $pdo->commit();
                $message = '导入成功，考试已创建！';
                $messageType = 'success';
                $parsedQuestions = [];
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = '创建失败：' . $e->getMessage();
                $messageType = 'error';
                $parsedQuestions = $_POST['questions'];
            }
        }
    } elseif (isset($_POST['import_word'])) {
        if (!isset($_FILES['word_file']) || $_FILES['word_file']['error'] !== UPLOAD_ERR_OK) {
            $message = '请上传Word文件（.docx）';
            $messageType = 'error';
        } else {
            $file = $_FILES['word_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'docx') {
                $message = '仅支持 .docx 格式的Word文件';
                $messageType = 'error';
            } else {
                $tempPath = tempnam(sys_get_temp_dir(), 'word_import_');
                if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
                    $message = '上传文件失败，请重试';
                    $messageType = 'error';
                } else {
                    try {
                        $parsedQuestions = parseDocxQuestions($tempPath);
                        if (empty($parsedQuestions)) {
                            $message = '未识别到题目，请检查Word文件格式';
                            $messageType = 'error';
                        } else {
                            $message = '解析成功，请核对题目后创建考试';
                            $messageType = 'success';
                            $examData['title'] = pathinfo($file['name'], PATHINFO_FILENAME);
                        }
                    } catch (Exception $e) {
                        $message = '解析失败：' . $e->getMessage();
                        $messageType = 'error';
                    }
                    @unlink($tempPath);
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
    <title>导入Word试题</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .question-block {
            background: #f8fafc;
            padding: 18px;
            border-radius: 16px;
            margin-bottom: 18px;
            border: 1px solid #e2e8f0;
        }
        .question-block h4 {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('import', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>导入Word试题</h1>
                    <p>上传符合模板的 Word 文档，自动解析题目并一键创建考试。</p>
                </div>
                <div>
                    <a href="create_exam.php" class="btn btn-muted btn-small">返回发布考试</a>
                </div>
            </section>

            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>" style="white-space: pre-line;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>第一步：上传 Word 文件</h2>
                </div>
                <p>支持 .docx 格式。请按照以下模板编写题目（举例）：</p>
                <pre style="background:#f4f5f7;padding:15px;border-radius:5px;overflow:auto;">
1. [单选] 以下哪项属于操作系统？
A. Word
B. Windows
C. Excel
D. PPT
答案: B
分值: 5

2. [多选] 以下哪些属于编程语言？(10分)
A. Java
B. Python
C. HTML
D. C++
答案: A,B,D

3. [判断] 计算机可以直接运行高级语言程序。(5分)
答案: 错误

4. [填空] 计算机的三大核心部件是 ________。(5分)
答案: CPU/内存/输入输出设备

5. [主观] 请简述你最喜欢的编程语言及原因。(10分)
            </pre>

                <form method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
                    <div class="form-group">
                        <label>选择Word文件（.docx）：</label>
                        <input type="file" name="word_file" accept=".docx" required>
                    </div>
                    <button type="submit" name="import_word" class="btn btn-primary">解析 Word 文件</button>
                </form>
            </div>

            <?php if (!empty($parsedQuestions)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>第二步：确认考试信息并创建</h2>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="create_exam" value="1">
                        <div class="form-group">
                            <label>考试/作业标题：</label>
                            <input type="text" name="title" value="<?php echo htmlspecialchars($examData['title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>考试说明：</label>
                            <textarea name="description" rows="3"><?php echo htmlspecialchars($examData['description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>类型：</label>
                            <select name="type">
                                <option value="exam" <?php echo $examData['type'] === 'exam' ? 'selected' : ''; ?>>考试</option>
                                <option value="homework" <?php echo $examData['type'] === 'homework' ? 'selected' : ''; ?>>作业</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>开始时间：</label>
                            <input type="datetime-local" name="start_time" value="<?php echo htmlspecialchars($examData['start_time']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>结束时间：</label>
                            <input type="datetime-local" name="end_time" value="<?php echo htmlspecialchars($examData['end_time']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>时长（分钟，可选）：</label>
                            <input type="number" name="duration" min="1" value="<?php echo htmlspecialchars($examData['duration']); ?>">
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="allow_retake" value="1" <?php echo $examData['allow_retake'] ? 'checked' : ''; ?>> 允许再次考试</label>
                        </div>

                        <h3>识别到的题目（可修改）：</h3>
                        <?php foreach ($parsedQuestions as $index => $question): ?>
                            <div class="question-block">
                                <div class="form-group">
                                    <label>标题/序号（可选）：</label>
                                    <input type="text" name="questions[<?php echo $index; ?>][section_label]" placeholder="如：一、选择题" value="<?php echo htmlspecialchars($question['section_label'] ?? ''); ?>">
                                </div>
                                <h4>题目 <?php echo $index + 1; ?></h4>
                                <div class="form-group">
                                    <label>题目内容：</label>
                                    <textarea name="questions[<?php echo $index; ?>][question_text]" rows="3" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label>题型：</label>
                                    <select name="questions[<?php echo $index; ?>][question_type]">
                                        <option value="single_choice" <?php echo $question['question_type'] === 'single_choice' ? 'selected' : ''; ?>>单选题</option>
                                        <option value="multiple_choice" <?php echo $question['question_type'] === 'multiple_choice' ? 'selected' : ''; ?>>多选题</option>
                            <option value="judge" <?php echo $question['question_type'] === 'judge' ? 'selected' : ''; ?>>判断题</option>
                            <option value="fill_blank" <?php echo $question['question_type'] === 'fill_blank' ? 'selected' : ''; ?>>填空题</option>
                            <option value="essay" <?php echo $question['question_type'] === 'essay' ? 'selected' : ''; ?>>主观题</option>
                            <option value="big_question" <?php echo $question['question_type'] === 'big_question' ? 'selected' : ''; ?>>一大题</option>
                            <option value="answer_question" <?php echo $question['question_type'] === 'answer_question' ? 'selected' : ''; ?>>二答题</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>分值：</label>
                                    <input type="number" name="questions[<?php echo $index; ?>][score]" step="0.01" min="0" value="<?php echo htmlspecialchars($question['score']); ?>" required>
                                </div>

                                <?php if (in_array($question['question_type'], ['single_choice', 'multiple_choice'])): ?>
                                    <div class="form-group">
                                        <label>选项（每行一个，如：A. 选项内容）：</label>
                                        <textarea name="questions[<?php echo $index; ?>][options_text]" rows="4"><?php echo htmlspecialchars($question['options_text']); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>正确答案（如：A 或 A,B）：</label>
                                        <input type="text" name="questions[<?php echo $index; ?>][correct_answer]" value="<?php echo htmlspecialchars($question['correct_answer']); ?>">
                                    </div>
                                <?php elseif ($question['question_type'] === 'judge'): ?>
                                    <div class="form-group">
                                        <label>正确答案：</label>
                                        <select name="questions[<?php echo $index; ?>][correct_answer]">
                                            <option value="正确" <?php echo $question['correct_answer'] === '正确' ? 'selected' : ''; ?>>正确</option>
                                            <option value="错误" <?php echo $question['correct_answer'] === '错误' ? 'selected' : ''; ?>>错误</option>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <div class="form-group">
                                        <label>参考答案（可选）：</label>
                                        <textarea name="questions[<?php echo $index; ?>][correct_answer]" rows="2"><?php echo htmlspecialchars($question['correct_answer']); ?></textarea>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <button type="submit" class="btn btn-primary">创建考试</button>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

