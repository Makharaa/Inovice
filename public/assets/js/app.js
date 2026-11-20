(() => {
    const body = document.body;
    const root = document.documentElement;
    const themeMeta = document.querySelector('meta[name="theme-color"]');
    const sidebar = document.getElementById('sidebar');
    const sidebarTriggers = document.querySelectorAll('[data-sidebar-toggle]');

    const refreshIcons = () => {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons({
                attrs: {
                    'stroke-width': 1.8,
                },
            });
        }
    };

    const updateThemeControls = () => {
        const isDark = root.dataset.theme === 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.setAttribute('aria-pressed', String(isDark));
            button.setAttribute('aria-label', isDark ? 'ღია თემის ჩართვა' : 'მუქი თემის ჩართვა');
            button.title = isDark ? 'ღია თემა' : 'მუქი თემა';
        });

        if (themeMeta) {
            themeMeta.setAttribute('content', isDark ? '#111816' : '#f4f7f6');
        }
    };

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
            root.classList.add('theme-changing');
            root.dataset.theme = nextTheme;

            try {
                localStorage.setItem('makhara-theme', nextTheme);
            } catch (error) {
                // The selected theme still applies for the current page.
            }

            updateThemeControls();

            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => root.classList.remove('theme-changing'));
            });
        });
    });

    const setSidebarState = (open) => {
        body.classList.toggle('sidebar-open', open);
        sidebarTriggers.forEach((trigger) => trigger.setAttribute('aria-expanded', String(open)));
    };

    sidebarTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => setSidebarState(!body.classList.contains('sidebar-open')));
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm || 'დარწმუნებული ხართ?')) {
                event.preventDefault();
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && body.classList.contains('sidebar-open')) {
            setSidebarState(false);
        }
    });

    if (sidebar) {
        sidebar.addEventListener('click', (event) => {
            if (window.innerWidth <= 900 && event.target.closest('a')) {
                setSidebarState(false);
            }
        });
    }

    updateThemeControls();
    refreshIcons();
})();
