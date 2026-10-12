{{-- Light only: the site is a single product with one look. --}}
<meta name="color-scheme" content="light">
<script>
(function () {
    var root = document.documentElement;
    function force() { if (root.classList.contains('dark')) root.classList.remove('dark'); }
    try { ['theme', 'darkMode', 'color-theme', 'dark'].forEach(function (k) { localStorage.removeItem(k); }); } catch (e) {}
    force();
    new MutationObserver(force).observe(root, { attributes: true, attributeFilter: ['class'] });
})();
</script>
