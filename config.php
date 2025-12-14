<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'admin');
define('DB_USER', 'admin');
define('DB_PASS', 'admin');
define('DB_CHARSET', 'utf8mb4');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 开启会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 数据库连接
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            die("数据库连接失败: " . $e->getMessage());
        }
    }
    return $pdo;
}

// 检查登录状态
function checkLogin() {
    // 获取当前文件路径（统一使用正斜杠）
    $currentFile = basename($_SERVER['PHP_SELF']);
    $currentDir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    
    // 如果当前是登录页面，不进行重定向检查
    if ($currentFile === 'login.php') {
        return;
    }
    
    // 如果未登录，重定向到对应目录的登录页面
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // 根据目录确定登录页面路径（兼容Windows和Linux路径）
        // 统一转换为小写进行比较，避免大小写问题
        $currentDirLower = strtolower($currentDir);
        
        // 明确判断路径，避免误判
        $isAdminDir = (strpos($currentDirLower, '/admin') !== false);
        $isTeacherDir = (strpos($currentDirLower, '/teacher') !== false);
        $isStudentDir = (strpos($currentDirLower, '/student') !== false);
        
        if ($isAdminDir) {
            header('Location: login.php');
        } elseif ($isTeacherDir) {
            header('Location: login.php');
        } elseif ($isStudentDir) {
            header('Location: login.php');
        } else {
            // 根目录，根据session中的role重定向，如果没有role则默认到学生登录
            if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'teacher', 'student'])) {
                header('Location: ' . $_SESSION['role'] . '/login.php');
            } else {
                header('Location: student/login.php');
            }
        }
        exit;
    }
}

// 获取当前所在的端（admin/teacher/student）
function getCurrentEndpoint() {
    $currentDir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    $currentDirLower = strtolower($currentDir);
    
    // 精确匹配目录路径，使用正则表达式确保准确匹配
    if (preg_match('#[/\\\\]admin($|[/\\\\])#', $currentDirLower)) {
        return 'admin';
    } elseif (preg_match('#[/\\\\]teacher($|[/\\\\])#', $currentDirLower)) {
        return 'teacher';
    } elseif (preg_match('#[/\\\\]student($|[/\\\\])#', $currentDirLower)) {
        return 'student';
    }
    return null;
}

// 检查角色 - 每个端完全独立
function checkRole($requiredRole) {
    // 先检查登录状态
    checkLogin();
    
    // 如果未登录，checkLogin已经处理了重定向并exit，不会执行到这里
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return;
    }
    
    $userRole = $_SESSION['role'];
    $currentEndpoint = getCurrentEndpoint();
    
    // 超级管理员可以访问所有页面
    if ($userRole === 'admin') {
        return true;
    }
    
    // 角色匹配，允许访问（不管当前在哪个目录）
    if ($userRole === $requiredRole) {
        return true;
    }
    
    // 角色不匹配时，重定向到对应角色的首页
    // 根据用户的实际角色重定向到正确的首页
    // 使用相对路径，从当前目录返回到项目根目录，再进入对应端
    if ($userRole === 'admin') {
        if ($currentEndpoint === 'admin') {
            // 如果已经在admin目录，重定向到index.php
            header('Location: index.php');
        } else {
            // 从其他目录重定向到admin目录
            header('Location: ../admin/index.php');
        }
    } elseif ($userRole === 'teacher') {
        if ($currentEndpoint === 'teacher') {
            header('Location: index.php');
        } else {
            header('Location: ../teacher/index.php');
        }
    } elseif ($userRole === 'student') {
        if ($currentEndpoint === 'student') {
            header('Location: index.php');
        } else {
            header('Location: ../student/index.php');
        }
    } else {
        // 未知角色，重定向到学生登录
        header('Location: ../student/login.php');
    }
    exit;
}

// 检查是否为超级管理员
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// 获取当前用户信息
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>

