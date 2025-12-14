<?php
function renderStudentSidebar($activeKey = 'dashboard', $user = null) {
    $menu = [
        'dashboard' => ['label' => '首页', 'href' => 'index.php'],
        'exam_list' => ['label' => '考试/作业列表', 'href' => 'exam_list.php'],
        'scores' => ['label' => '查询成绩', 'href' => 'view_score.php'],
    ];

    $userName = $user ? htmlspecialchars($user['name']) : '学生';
    ?>
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="切换菜单">☰</button>
    <div class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>
    <aside class="app-sidebar" id="app-sidebar">
        <div>
            <div class="sidebar-brand">学生工作台</div>
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

?>
