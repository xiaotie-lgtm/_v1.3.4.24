<?php
function renderTeacherSidebar($activeKey = 'dashboard', $user = null) {
    $menu = [
        'dashboard' => ['label' => '教学总览', 'href' => 'index.php'],
        'question_bank' => ['label' => '题库管理', 'href' => 'question_bank.php'],
        'create_exam' => ['label' => '发布考试/作业', 'href' => 'create_exam.php'],
        'quick_generate' => ['label' => '快速组卷', 'href' => 'quick_generate_exam.php'],
        'exam_list' => ['label' => '考试/作业列表', 'href' => 'exam_list.php'],
        'grade' => ['label' => '批改作业', 'href' => 'grade_exam.php'],
        'delete' => ['label' => '删除考试/作业', 'href' => 'delete_exam.php'],
        'publish_score' => ['label' => '发布成绩', 'href' => 'publish_score.php'],
        'import' => ['label' => '导入试题', 'href' => 'import_exam.php'],
        'classes' => ['label' => '班级管理', 'href' => 'manage_classes.php'],
        'students' => ['label' => '创建学生账号', 'href' => 'create_student.php'],
    ];

    $userName = $user ? htmlspecialchars($user['name']) : '老师';
    ?>
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="切换菜单">☰</button>
    <div class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>
    <aside class="app-sidebar" id="app-sidebar">
        <div>
            <div class="sidebar-brand">老师工作台</div>
            <div class="sidebar-user">
                <strong><?php echo $userName; ?></strong>
                <span>欢迎回来</span>
            </div>
        </div>
        <nav class="sidebar-menu">
            <?php foreach ($menu as $key => $item): ?>
                <a href="<?php echo $item['href']; ?>" class="<?php echo $key === $activeKey ? 'active' : ''; ?>" onclick="closeMobileMenu()">
                    <?php echo htmlspecialchars($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <a href="logout.php" class="btn btn-secondary btn-small" onclick="closeMobileMenu()">退出登录</a>
    </aside>
    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('app-sidebar');
            const overlay = document.querySelector('.mobile-menu-overlay');
            sidebar.classList.toggle('mobile-open');
            if (overlay) {
                overlay.style.display = sidebar.classList.contains('mobile-open') ? 'block' : 'none';
            }
        }
        function closeMobileMenu() {
            const sidebar = document.getElementById('app-sidebar');
            const overlay = document.querySelector('.mobile-menu-overlay');
            sidebar.classList.remove('mobile-open');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
        // 点击外部关闭菜单
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('app-sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            const overlay = document.querySelector('.mobile-menu-overlay');
            if (sidebar && sidebar.classList.contains('mobile-open')) {
                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    closeMobileMenu();
                }
            }
        });
    </script>
    <?php
}

