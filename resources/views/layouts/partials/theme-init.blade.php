<script data-theme-init>
    (() => {
        const root = document.documentElement;
        root.classList.remove('dark');
        root.style.colorScheme = 'light';
        try { window.localStorage.removeItem('dark'); } catch (e) {}
    })();
</script>
