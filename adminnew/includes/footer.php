            </div><!-- .admin-content -->
        </main>
    </div><!-- .admin-wrapper -->

    <script <?= csp_nonce(); ?>>
    // App Switcher Dropdown
    const appSwitcherBtn = document.getElementById('appSwitcherBtn');
    const appSwitcherDropdown = document.getElementById('appSwitcherDropdown');

    if (appSwitcherBtn && appSwitcherDropdown) {
        appSwitcherBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = appSwitcherDropdown.classList.toggle('open');
            // Toggle chevron rotation
            appSwitcherBtn.closest('.app-switcher').classList.toggle('open', isOpen);
            // Close user menu if open
            const userMenu = document.getElementById('userMenuDropdown');
            if (userMenu) userMenu.classList.remove('open');
        });
    }

    // User Menu Dropdown
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenuDropdown = document.getElementById('userMenuDropdown');

    if (userMenuBtn && userMenuDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('open');
            // Close app switcher if open
            if (appSwitcherDropdown) appSwitcherDropdown.classList.remove('open');
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (appSwitcherDropdown && !appSwitcherDropdown.contains(e.target) && !appSwitcherBtn.contains(e.target)) {
            appSwitcherDropdown.classList.remove('open');
            appSwitcherBtn.closest('.app-switcher').classList.remove('open');
        }
        if (userMenuDropdown && !userMenuDropdown.contains(e.target) && !userMenuBtn.contains(e.target)) {
            userMenuDropdown.classList.remove('open');
        }
    });

    // Close dropdowns on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (appSwitcherDropdown) {
                appSwitcherDropdown.classList.remove('open');
                appSwitcherBtn.closest('.app-switcher').classList.remove('open');
            }
            if (userMenuDropdown) userMenuDropdown.classList.remove('open');
        }
    });

    // Mobile Sidebar Toggle
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebar = document.querySelector('.admin-sidebar');

    if (mobileSidebarToggle && sidebar) {
        mobileSidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            document.body.classList.toggle('sidebar-open');
        });
    }

    // Close sidebar when clicking overlay (mobile)
    document.addEventListener('click', function(e) {
        if (sidebar && sidebar.classList.contains('mobile-open')) {
            if (!sidebar.contains(e.target) && !mobileSidebarToggle.contains(e.target)) {
                sidebar.classList.remove('mobile-open');
                document.body.classList.remove('sidebar-open');
            }
        }
    });

    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        const lightIcon = themeToggle.querySelector('.theme-light');
        const darkIcon = themeToggle.querySelector('.theme-dark');

        function updateThemeIcons() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (lightIcon && darkIcon) {
                lightIcon.style.display = isDark ? 'none' : 'block';
                darkIcon.style.display = isDark ? 'block' : 'none';
            }
        }

        updateThemeIcons();

        themeToggle.addEventListener('click', function() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('admin_theme', newTheme);
            updateThemeIcons();
        });
    }

    // Global search
    const globalSearch = document.getElementById('globalSearch');
    if (globalSearch) {
        globalSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && this.value.trim()) {
                window.location.href = '/adminnew?module=people&page=index&q=' + encodeURIComponent(this.value.trim());
            }
        });
    }

    // Active tab indicator animation
    const activeTab = document.querySelector('.topbar-nav-link.active');
    if (activeTab) {
        // Smooth scroll to active tab on mobile if needed
        activeTab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }

    // Keyboard navigation for app switcher
    if (appSwitcherDropdown) {
        const appLinks = appSwitcherDropdown.querySelectorAll('.app-link:not(.coming-soon)');
        let focusedIndex = -1;

        appSwitcherBtn.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown' || e.key === 'Enter') {
                e.preventDefault();
                appSwitcherDropdown.classList.add('open');
                focusedIndex = 0;
                appLinks[focusedIndex]?.focus();
            }
        });

        appSwitcherDropdown.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                focusedIndex = Math.min(focusedIndex + 1, appLinks.length - 1);
                appLinks[focusedIndex]?.focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                focusedIndex = Math.max(focusedIndex - 1, 0);
                appLinks[focusedIndex]?.focus();
            } else if (e.key === 'Tab') {
                appSwitcherDropdown.classList.remove('open');
            }
        });
    }
    </script>
</body>
</html>
