<?php
require_once 'config.php';

$error = '';

function redirectByRole(string $role): void {
    $destinations = [
        'admin'   => 'admin/index.php',
        'teacher' => 'teacher/index.php',
        'student' => 'student/index.php'
    ];

    header('Location: ' . ($destinations[$role] ?? 'student/index.php'));
    exit;
}

function authenticate(PDO $pdo, string $username, string $password): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new RuntimeException('用户名不存在，请检查数据库是否已导入数据');
    }

    if (!password_verify($password, $user['password'])) {
        throw new RuntimeException('密码错误，请联系管理员重置密码。');
    }

    return $user;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = '请输入用户名和密码';
    } else {
        try {
            $user = authenticate(getDB(), $username, $password);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name']     = $user['name'];
            $_SESSION['role']     = $user['role'];
            redirectByRole($user['role']);
        } catch (RuntimeException $runtimeError) {
            $error = $runtimeError->getMessage();
        } catch (Exception $e) {
            $error = '数据库连接失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>校园答题系统 - 登录</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* 登录页面高级样式 */
        body.login-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            width: 100%;
            padding: 20px;
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            max-width: 1000px;
            width: 100%;
        }

        .login-banner {
            color: white;
            animation: slideInLeft 0.8s ease-out;
        }

        .login-banner h1 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .login-banner p {
            font-size: 16px;
            line-height: 1.8;
            opacity: 0.95;
            margin-bottom: 30px;
        }

        .feature-list {
            list-style: none;
        }

        .feature-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            font-size: 15px;
        }

        .feature-list li::before {
            content: "✓";
            display: inline-block;
            width: 24px;
            height: 24px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            margin-right: 12px;
            font-weight: bold;
        }

        .login-box {
            background: white;
            padding: 50px 40px;
            border-radius: 24px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            animation: slideInRight 0.8s ease-out;
            backdrop-filter: blur(10px);
        }

        .login-box h2 {
            font-size: 28px;
            color: #1f2937;
            margin-bottom: 8px;
            text-align: center;
            font-weight: 700;
        }

        .login-box .subtitle {
            text-align: center;
            color: #9ca3af;
            margin-bottom: 35px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #374151;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .form-group input::placeholder {
            color: #d1d5db;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .login-box .btn {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            margin-top: 10px;
        }

        .login-box .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .login-box .btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: #fee2e2;
            border: 2px solid #fca5a5;
            color: #991b1b;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            animation: shake 0.5s ease-in-out;
        }

        .error-message::before {
            content: "⚠ ";
            font-weight: bold;
        }

        .tips {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
        }

        .tips a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .tips a:hover {
            color: #764ba2;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .login-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .login-banner {
                text-align: center;
            }

            .login-banner h1 {
                font-size: 32px;
            }

            .login-banner p {
                font-size: 14px;
            }

            .feature-list {
                display: none;
            }

            .login-box {
                padding: 40px 30px;
            }

            .login-box h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-content">
                <div class="login-banner">
                    <h1>校园答题系统</h1>
                    <p>专业的在线考试平台，为教师和学生提供高效的教学和学习体验</p>
                    <ul class="feature-list">
                        <li>🎯 智能出题与自动阅卷</li>
                        <li>📊 实时成绩统计分析</li>
                        <li>👥 支持多用户管理</li>
                        <li>🔐 数据安全加密保护</li>
                    </ul>
                </div>

                <div class="login-box">
                    <h2>欢迎登录</h2>
                    <p class="subtitle">请输入您的账号信息</p>
                    
                    <?php if ($error): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="username">用户名</label>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                placeholder="请输入用户名"
                                required 
                                autofocus
                            >
                        </div>

                        <div class="form-group">
                            <label for="password">密码</label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="请输入密码"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-primary">登录</button>
                    </form>

                    <div class="tips">
                        <p>首次使用？请联系管理员获取账号</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

