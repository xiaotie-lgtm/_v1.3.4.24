<?php
/**
 * 删除默认学生账号和老师账号的脚本
 * 执行此脚本将删除数据库中用户名='student'和username='teacher'的账号
 */

require_once 'config.php';

$pdo = getDB();

try {
    // 开始事务
    $pdo->beginTransaction();
    
    // 查找并删除默认学生账号
    $stmt = $pdo->prepare("SELECT id, username, name FROM users WHERE username = 'student' AND role = 'student'");
    $stmt->execute();
    $defaultStudent = $stmt->fetch();
    
    if ($defaultStudent) {
        $deleteStudent = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $deleteStudent->execute([$defaultStudent['id']]);
        echo "✓ 已删除默认学生账号：{$defaultStudent['username']} ({$defaultStudent['name']})\n";
    } else {
        echo "ℹ 未找到默认学生账号（username='student'）\n";
    }
    
    // 查找并删除默认老师账号
    $stmt = $pdo->prepare("SELECT id, username, name FROM users WHERE username = 'teacher' AND role = 'teacher'");
    $stmt->execute();
    $defaultTeacher = $stmt->fetch();
    
    if ($defaultTeacher) {
        $deleteTeacher = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $deleteTeacher->execute([$defaultTeacher['id']]);
        echo "✓ 已删除默认老师账号：{$defaultTeacher['username']} ({$defaultTeacher['name']})\n";
    } else {
        echo "ℹ 未找到默认老师账号（username='teacher'）\n";
    }
    
    // 提交事务
    $pdo->commit();
    echo "\n✅ 操作完成！\n";
    
} catch (Exception $e) {
    // 回滚事务
    $pdo->rollBack();
    echo "❌ 删除失败：" . $e->getMessage() . "\n";
    exit(1);
}

