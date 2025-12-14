<?php
function renderAdminSidebar($activeKey = 'dashboard', $user = null) {
    $menu = [
        'dashboard' => ['label' => '系统概览', 'href' => 'index.php'],
        'create_teacher' => ['label' => '创建老师账号', 'href' => 'create_teacher.php'],
        'teacher_list' => ['label' => '老师列表', 'href' => 'teacher_list.php'],
        'manage_classes' => ['label' => '班级管理', 'href' => 'manage_classes.php'],
        'user_list' => ['label' => '用户管理', 'href' => 'user_list.php'],
    ];

    $userName = $user ? htmlspecialchars($user['name']) : '管理员';
    ?>
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="切换菜单">☰</button>
    <div class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>
    <aside class="app-sidebar" id="app-sidebar">
        <div>
            <div class="sidebar-brand">超级管理员</div>
            <div class="sidebar-user">
                <strong><?php echo $userName; ?></strong>
                <span>系统控制台</span>
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

