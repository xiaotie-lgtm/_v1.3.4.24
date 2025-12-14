<?php
require_once '../config.php';
checkRole('teacher');
require_once 'teacher_sidebar.php';

$pdo = getDB();
$teacherId = $_SESSION['user_id'];
$user = getCurrentUser();
$message = '';
$messageType = '';

function getBankById($pdo, $bankId, $teacherId) {
    $stmt = $pdo->prepare("SELECT * FROM question_banks WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$bankId, $teacherId]);
    return $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bankId = isset($_POST['bank_id']) ? intval($_POST['bank_id']) : 0;

    try {
        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM question_banks WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$bankId, $teacherId]);
            if ($stmt->rowCount() > 0) {
                $message = '题库已删除。';
                $messageType = 'success';
            }
        } elseif (in_array($action, ['publish', 'unpublish'])) {
            $status = $action === 'publish' ? 'published' : 'draft';
            $stmt = $pdo->prepare("UPDATE question_banks SET status = ? WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$status, $bankId, $teacherId]);
            if ($stmt->rowCount() > 0) {
                $message = $status === 'published' ? '题库已发布。' : '题库已设为草稿。';
                $messageType = 'success';
            }
        } elseif ($action === 'share') {
            $targetUsername = trim($_POST['target_teacher_username'] ?? '');
            if ($targetUsername === '') {
                throw new Exception('请输入要分享的老师账号。');
            }
            $teacherStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' AND username = ?");
            $teacherStmt->execute([$targetUsername]);
            $targetTeacher = $teacherStmt->fetch();
            if (!$targetTeacher) {
                throw new Exception('未找到该老师账号。');
            }
            $targetTeacherId = intval($targetTeacher['id']);
            if ($targetTeacherId === $teacherId) {
                throw new Exception('无法分享给自己。');
            }
            $bank = getBankById($pdo, $bankId, $teacherId);
            if ($bank) {
                $stmt = $pdo->prepare("INSERT INTO question_bank_shares (bank_id, owner_teacher_id, target_teacher_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)");
                $stmt->execute([$bankId, $teacherId, $targetTeacherId]);
                $message = '题库已分享给指定老师。';
                $messageType = 'success';
            }
        } elseif ($action === 'unshare') {
            $targetTeacherId = intval($_POST['target_teacher_id'] ?? 0);
            if ($targetTeacherId) {
                $stmt = $pdo->prepare("DELETE FROM question_bank_shares WHERE bank_id = ? AND owner_teacher_id = ? AND target_teacher_id = ?");
                $stmt->execute([$bankId, $teacherId, $targetTeacherId]);
                if ($stmt->rowCount() > 0) {
                    $message = '已取消分享。';
                    $messageType = 'success';
                }
            }
        } elseif ($action === 'clone_shared') {
            $sharedBankId = $bankId;
            $stmt = $pdo->prepare("SELECT qb.* FROM question_banks qb INNER JOIN question_bank_shares qbs ON qb.id = qbs.bank_id WHERE qbs.target_teacher_id = ? AND qb.id = ?");
            $stmt->execute([$teacherId, $sharedBankId]);
            $sourceBank = $stmt->fetch();
            if ($sourceBank) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO question_banks (teacher_id, name, description, status) VALUES (?, ?, ?, 'draft')");
                $stmt->execute([
                    $teacherId,
                    $sourceBank['name'] . '（副本）',
                    $sourceBank['description']
                ]);
                $newBankId = $pdo->lastInsertId();

                $questionsStmt = $pdo->prepare("SELECT * FROM question_bank_questions WHERE bank_id = ? ORDER BY order_num ASC");
                $questionsStmt->execute([$sourceBank['id']]);
                $insertQuestion = $pdo->prepare("INSERT INTO question_bank_questions (bank_id, question_text, question_type, section_label, options, correct_answer, score, order_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $order = 1;
                while ($question = $questionsStmt->fetch()) {
                    $insertQuestion->execute([
                        $newBankId,
                        $question['question_text'],
                        $question['question_type'],
                        $question['section_label'] ?? null,
                        $question['options'],
                        $question['correct_answer'],
                        $question['score'],
                        $order++
                    ]);
                }

                $pdo->commit();
                $message = '已将共享题库复制到我的题库，状态为草稿。您可以进一步编辑。';
                $messageType = 'success';
            }
        } elseif ($action === 'bulk_publish') {
            $bankIds = $_POST['bank_ids'] ?? [];
            $bankIds = array_filter(array_map('intval', (array)$bankIds));
            if (empty($bankIds)) {
                throw new Exception('请选择需要发布的题库。');
            }
            $placeholders = implode(',', array_fill(0, count($bankIds), '?'));
            $params = array_merge(['published'], $bankIds, [$teacherId]);
            $stmt = $pdo->prepare("UPDATE question_banks SET status = ? WHERE id IN ($placeholders) AND teacher_id = ?");
            $updated = $stmt->execute($params);
            if ($updated) {
                $message = '选中的题库已发布。';
                $messageType = 'success';
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = '操作失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

$stmt = $pdo->prepare("
    SELECT qb.*, COUNT(qbq.id) AS question_count
    FROM question_banks qb
    LEFT JOIN question_bank_questions qbq ON qb.id = qbq.bank_id
    WHERE qb.teacher_id = ?
    GROUP BY qb.id
    ORDER BY qb.updated_at DESC
");
$stmt->execute([$teacherId]);
$myBanks = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT qb.*, u.name AS owner_name, COUNT(qbq.id) AS question_count
    FROM question_banks qb
    INNER JOIN question_bank_shares qbs ON qb.id = qbs.bank_id
    INNER JOIN users u ON qb.teacher_id = u.id
    LEFT JOIN question_bank_questions qbq ON qb.id = qbq.bank_id
    WHERE qbs.target_teacher_id = ?
    GROUP BY qb.id, u.name
    ORDER BY qb.updated_at DESC
");
$stmt->execute([$teacherId]);
$sharedBanks = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT qbs.bank_id, qbs.target_teacher_id, u.name AS teacher_name
    FROM question_bank_shares qbs
    INNER JOIN users u ON qbs.target_teacher_id = u.id
    WHERE qbs.owner_teacher_id = ?
");
$stmt->execute([$teacherId]);
$shareMap = [];
while ($row = $stmt->fetch()) {
    $shareMap[$row['bank_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>题库管理</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderTeacherSidebar('question_bank', $user); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>题库管理</h1>
                    <p>每位老师拥有独立题库，可灵活发布、复制与分享，让备课与出题更加高效。</p>
                    <div class="hero-actions">
                        <a href="question_bank_edit.php" class="btn btn-primary btn-small">新建题库</a>
                        <a href="create_exam.php" class="btn btn-muted btn-small">从题库发布考试</a>
                    </div>
                </div>
                <div>
                    <p>技巧：发布状态的题库才能进行分享；复制共享题库时会自动生成草稿，方便再次编辑。</p>
                </div>
            </section>

            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="section-title">
                    <h2>我的题库</h2>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <a href="question_bank_edit.php" class="btn btn-primary btn-small">新建题库</a>
                        <?php if (!empty($myBanks)): ?>
                            <form method="POST" id="bulkPublishForm">
                                <input type="hidden" name="action" value="bulk_publish">
                                <button type="submit" class="btn btn-success btn-small" id="bulkPublishButton" disabled>发布选中</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (empty($myBanks)): ?>
                    <p>暂未创建题库，点击“新建题库”开始创建。</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:40px;">选择</th>
                                    <th>名称</th>
                                    <th>题目数量</th>
                                    <th>状态</th>
                                    <th>最近更新</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myBanks as $bank): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="bank_ids[]" value="<?php echo $bank['id']; ?>" form="bulkPublishForm" class="bank-checkbox">
                                        </td>
                                        <td><?php echo htmlspecialchars($bank['name']); ?></td>
                                        <td><?php echo intval($bank['question_count']); ?></td>
                                        <td>
                                            <?php if ($bank['status'] === 'published'): ?>
                                                <span class="tag tag-published">已发布</span>
                                            <?php else: ?>
                                                <span class="tag tag-draft">草稿</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($bank['updated_at']); ?></td>
                                        <td class="table-actions">
                                            <a href="question_bank_edit.php?id=<?php echo $bank['id']; ?>" class="btn btn-secondary btn-small">编辑</a>
                                            <form method="POST" onsubmit="return confirm('确认删除该题库及题目？');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="bank_id" value="<?php echo $bank['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-small">删除</button>
                                            </form>
                                            <?php if ($bank['status'] === 'draft'): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="publish">
                                                    <input type="hidden" name="bank_id" value="<?php echo $bank['id']; ?>">
                                                    <button type="submit" class="btn btn-primary btn-small">发布</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="unpublish">
                                                    <input type="hidden" name="bank_id" value="<?php echo $bank['id']; ?>">
                                                    <button type="submit" class="btn btn-muted btn-small">取消发布</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="5">
                                            <strong>分享设置</strong>
                                            <form method="POST" class="inline-form" style="margin-top:8px;">
                                                <input type="hidden" name="action" value="share">
                                                <input type="hidden" name="bank_id" value="<?php echo $bank['id']; ?>">
                                                <input type="text" name="target_teacher_username" placeholder="输入老师账号" required>
                                                <button type="submit" class="btn btn-primary btn-small">分享</button>
                                            </form>
                                            <?php if (!empty($shareMap[$bank['id']])): ?>
                                                <div class="share-list">
                                                    已分享给：
                                                    <?php foreach ($shareMap[$bank['id']] as $share): ?>
                                                        <span class="tag tag-draft">
                                                            <?php echo htmlspecialchars($share['teacher_name']); ?>
                                                        </span>
                                                        <form method="POST" class="inline-form">
                                                            <input type="hidden" name="action" value="unshare">
                                                            <input type="hidden" name="bank_id" value="<?php echo $bank['id']; ?>">
                                                            <input type="hidden" name="target_teacher_id" value="<?php echo $share['target_teacher_id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-small">取消</button>
                                                        </form>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="share-list">暂未分享给其他老师。</div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card" style="margin-top:20px;">
                <div class="section-title">
                    <h2>共享给我的题库</h2>
                </div>
                <?php if (empty($sharedBanks)): ?>
                    <p>暂无其他老师分享的题库。</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>名称</th>
                                    <th>题目数量</th>
                                    <th>状态</th>
                                    <th>分享老师</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sharedBanks as $bank): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bank['name']); ?></td>
                                        <td><?php echo intval($bank['question_count']); ?></td>
                                        <td><?php echo $bank['status'] === 'published' ? '已发布' : '草稿'; ?></td>
                                        <td><?php echo htmlspecialchars($bank['owner_name']); ?></td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="clone_shared">
                                                <input type="hidden" name="bank_id" value="<?php echo $bank['id']; ?>">
                                                <button type="submit" class="btn btn-secondary btn-small">复制到我的题库</button>
                                            </form>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.bank-checkbox');
            const bulkButton = document.getElementById('bulkPublishButton');
            if (!bulkButton) return;
            function toggleBulkButton() {
                bulkButton.disabled = !Array.from(checkboxes).some(cb => cb.checked);
            }
            checkboxes.forEach(cb => cb.addEventListener('change', toggleBulkButton));
        });
    </script>
</body>
</html>

