<?php
require_once '../config.php';
require_once 'admin_sidebar.php';
checkRole('admin');

$pdo = getDB();
$message = '';
$messageType = '';
$currentAdmin = getCurrentUser();

// 处理更新用户班级
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student_class'])) {
    $studentId = intval($_POST['student_id']);
    $classId = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;

    try {
        $stmt = $pdo->prepare("UPDATE users SET class_id = ? WHERE id = ? AND role = 'student'");
        $stmt->execute([$classId, $studentId]);
        $message = '更新成功！';
        $messageType = 'success';

        $searchClass = $_POST['search_class_id'] ?? '';
        $searchKeyword = $_POST['search_keyword'] ?? '';
        if (!empty($searchClass) || !empty($searchKeyword)) {
            $redirectUrl = "user_list.php?";
            if (!empty($searchClass)) {
                $redirectUrl .= "class_id=" . urlencode($searchClass);
            }
            if (!empty($searchKeyword)) {
                $redirectUrl .= (!empty($searchClass) ? "&" : "") . "keyword=" . urlencode($searchKeyword);
            }
            header("Location: " . $redirectUrl);
            exit;
        }
    } catch (Exception $e) {
        $message = '更新失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 处理删除用户
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id']);

    try {
        if ($userId == $_SESSION['user_id']) {
            $message = '不能删除自己的账号';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $message = '删除成功！';
            $messageType = 'success';

            $searchClass = $_POST['search_class_id'] ?? '';
            $searchKeyword = $_POST['search_keyword'] ?? '';
            if (!empty($searchClass) || !empty($searchKeyword)) {
                $redirectUrl = "user_list.php?";
                if (!empty($searchClass)) {
                    $redirectUrl .= "class_id=" . urlencode($searchClass);
                }
                if (!empty($searchKeyword)) {
                    $redirectUrl .= (!empty($searchClass) ? "&" : "") . "keyword=" . urlencode($searchKeyword);
                }
                header("Location: " . $redirectUrl);
                exit;
            }
        }
    } catch (Exception $e) {
        $message = '删除失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 处理给老师分配班级
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_teacher_class'])) {
    $teacherId = intval($_POST['teacher_id']);
    $classId = intval($_POST['class_id']);

    if ($classId <= 0) {
        $message = '请选择班级后再添加';
        $messageType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id) VALUES (?, ?)");
            $stmt->execute([$teacherId, $classId]);
            $message = '班级分配成功！';
            $messageType = 'success';
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'unique_teacher_class') !== false) {
                $message = '该老师已经管理此班级';
            } else {
                $message = '分配失败：' . $e->getMessage();
            }
            $messageType = 'error';
        }
    }
}

// 处理移除老师与班级的关联
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_teacher_class'])) {
    $teacherId = intval($_POST['teacher_id']);
    $classId = intval($_POST['class_id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = ? AND class_id = ?");
        $stmt->execute([$teacherId, $classId]);
        $message = '已从老师班级列表移除';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = '移除失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 处理移除老师与班级的关联
// 搜索条件
$searchClass = $_GET['class_id'] ?? '';
$searchKeyword = trim($_GET['keyword'] ?? '');

// 查询用户
$query = "SELECT u.*, c.name AS class_name
          FROM users u
          LEFT JOIN classes c ON u.class_id = c.id
          WHERE 1=1";
$params = [];

if (!empty($searchClass)) {
    $query .= " AND u.class_id = ?";
    $params[] = $searchClass;
}

if (!empty($searchKeyword)) {
    $query .= " AND (u.name LIKE ? OR u.username LIKE ?)";
    $pattern = '%' . $searchKeyword . '%';
    $params[] = $pattern;
    $params[] = $pattern;
}

$query .= " ORDER BY u.role, u.name";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$userStats = [
    'total' => count($users),
    'admin' => 0,
    'teacher' => 0,
    'student' => 0
];
foreach ($users as $row) {
    if (isset($userStats[$row['role']])) {
        $userStats[$row['role']]++;
    }
}

$classes = $pdo->query("SELECT * FROM classes ORDER BY name")->fetchAll();

// 老师-班级映射
$teacherClassMap = [];
$teacherClassStmt = $pdo->query("SELECT tc.teacher_id, c.id AS class_id, c.name AS class_name
                                 FROM teacher_classes tc
                                 INNER JOIN classes c ON c.id = tc.class_id
                                 ORDER BY c.name");
while ($tc = $teacherClassStmt->fetch()) {
    $teacherClassMap[$tc['teacher_id']][] = [
        'id' => $tc['class_id'],
        'name' => $tc['class_name']
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-shell">
        <?php renderAdminSidebar('user_list', $currentAdmin); ?>
        <main class="app-content">
            <section class="page-hero">
                <div>
                    <h1>用户管理</h1>
                    <p>按照角色或班级筛选账号，快速更新班级归属或停用异常账号。</p>
                </div>
            </section>

            <section class="stats-grid">
                <div class="stat-card">
                    <span>当前列表</span>
                    <strong><?php echo $userStats['total']; ?></strong>
                </div>
                <div class="stat-card">
                    <span>超级管理员</span>
                    <strong><?php echo $userStats['admin']; ?></strong>
                </div>
                <div class="stat-card">
                    <span>老师</span>
                    <strong><?php echo $userStats['teacher']; ?></strong>
                </div>
                <div class="stat-card">
                    <span>学生</span>
                    <strong><?php echo $userStats['student']; ?></strong>
                </div>
            </section>

            <?php if ($message): ?>
                <div class="<?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>搜索用户</h2>
                </div>
                <form method="GET" class="search-form" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
                    <div class="form-group" style="flex:1;min-width:200px;">
                        <label>班级筛选：</label>
                        <select name="class_id">
                            <option value="">全部班级</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $searchClass == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;min-width:200px;">
                        <label>姓名/用户名：</label>
                        <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="输入姓名或用户名">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">搜索</button>
                        <a href="user_list.php" class="btn btn-secondary">重置</a>
                    </div>
                </form>
                <?php if (!empty($searchClass) || !empty($searchKeyword)): ?>
                    <p style="margin-top:10px;color:#6b7280;">
                        搜索结果：共 <strong><?php echo count($users); ?></strong> 个用户
                        <?php if (!empty($searchClass)): ?>
                            | 班级：<?php 
                                $selectedClass = array_filter($classes, function($c) use ($searchClass) { return $c['id'] == $searchClass; });
                                echo htmlspecialchars($selectedClass ? reset($selectedClass)['name'] : '');
                            ?>
                        <?php endif; ?>
                        <?php if (!empty($searchKeyword)): ?>
                            | 关键词：<?php echo htmlspecialchars($searchKeyword); ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>用户名</th>
                                <th>姓名</th>
                                <th>角色</th>
                                <th>班级</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;color:#94a3b8;">暂无用户</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>
                                            <?php $roleMap = ['admin' => '超级管理员', 'teacher' => '老师', 'student' => '学生']; ?>
                                            <?php echo $roleMap[$row['role']] ?? $row['role']; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['role'] === 'student'): ?>
                                                <form method="POST" style="display:flex;gap:8px;align-items:center;">
                                                    <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                                    <select name="class_id" onchange="this.form.submit()">
                                                        <option value="">未分配</option>
                                                        <?php foreach ($classes as $class): ?>
                                                            <option value="<?php echo $class['id']; ?>" <?php echo $row['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($class['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="hidden" name="update_student_class" value="1">
                                                    <?php if (!empty($searchClass)): ?>
                                                        <input type="hidden" name="search_class_id" value="<?php echo htmlspecialchars($searchClass); ?>">
                                                    <?php endif; ?>
                                                    <?php if (!empty($searchKeyword)): ?>
                                                        <input type="hidden" name="search_keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>">
                                                    <?php endif; ?>
                                                </form>
                                            <?php elseif ($row['role'] === 'teacher'): ?>
                                                <?php $assignedClasses = $teacherClassMap[$row['id']] ?? []; ?>
                                                <div class="teacher-class-manager">
                                                    <div class="teacher-class-tags">
                                                        <?php if (!empty($assignedClasses)): ?>
                                                            <?php foreach ($assignedClasses as $classInfo): ?>
                                                                <form method="POST" class="teacher-class-pill">
                                                                    <span><?php echo htmlspecialchars($classInfo['name']); ?></span>
                                                                    <input type="hidden" name="teacher_id" value="<?php echo $row['id']; ?>">
                                                                    <input type="hidden" name="class_id" value="<?php echo $classInfo['id']; ?>">
                                                                    <input type="hidden" name="remove_teacher_class" value="1">
                                                                    <?php if (!empty($searchClass)): ?>
                                                                        <input type="hidden" name="search_class_id" value="<?php echo htmlspecialchars($searchClass); ?>">
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($searchKeyword)): ?>
                                                                        <input type="hidden" name="search_keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>">
                                                                    <?php endif; ?>
                                                                    <button type="submit" class="pill-remove" title="移除该班级">×</button>
                                                                </form>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="muted-text">尚未分配班级</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <form method="POST">
                                                        <input type="hidden" name="teacher_id" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="assign_teacher_class" value="1">
                                                        <?php if (!empty($searchClass)): ?>
                                                            <input type="hidden" name="search_class_id" value="<?php echo htmlspecialchars($searchClass); ?>">
                                                        <?php endif; ?>
                                                        <?php if (!empty($searchKeyword)): ?>
                                                            <input type="hidden" name="search_keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>">
                                                        <?php endif; ?>
                                                        <select name="class_id" onchange="this.form.submit()" required>
                                                            <option value="">选择班级</option>
                                                            <?php
                                                                $assignedIds = array_column($assignedClasses, 'id');
                                                                foreach ($classes as $class):
                                                                    if (!in_array($class['id'], $assignedIds)):
                                                            ?>
                                                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                                            <?php
                                                                    endif;
                                                                endforeach;
                                                            ?>
                                                        </select>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="table-actions">
                                            <?php
                                                $queryParts = [];
                                                if (!empty($searchClass)) $queryParts[] = 'class_id=' . urlencode($searchClass);
                                                if (!empty($searchKeyword)) $queryParts[] = 'keyword=' . urlencode($searchKeyword);
                                                $editUrl = 'edit_user.php?id=' . $row['id'] . ($queryParts ? '&' . implode('&', $queryParts) : '');
                                            ?>
                                            <a href="<?php echo $editUrl; ?>" class="btn btn-muted btn-small">修改</a>
                                            <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" onsubmit="return confirm('确定要删除用户「<?php echo htmlspecialchars($row['name']); ?>」吗？');">
                                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                    <?php if (!empty($searchClass)): ?>
                                                        <input type="hidden" name="search_class_id" value="<?php echo htmlspecialchars($searchClass); ?>">
                                                    <?php endif; ?>
                                                    <?php if (!empty($searchKeyword)): ?>
                                                        <input type="hidden" name="search_keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>">
                                                    <?php endif; ?>
                                                    <button type="submit" name="delete_user" class="btn btn-danger btn-small">删除</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

