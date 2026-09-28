<script data-theme-init>
    (() => {
        const root = document.documentElement;
        const savedTheme = window.localStorage.getItem('dark');
        const isDark = root.dataset.theme !== 'light'
            && (savedTheme === 'true' || savedTheme === '"true"');

        root.classList.toggle('dark', isDark);
        root.style.colorScheme = isDark ? 'dark' : 'light';
    })();
</script>
