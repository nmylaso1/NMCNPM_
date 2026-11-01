<?php
// Shared admin layout close — include at bottom of every admin page
?>
    </div><!-- end page content -->
</div><!-- end #admin-main -->

<script>
window.addEventListener('load', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

function openSidebar() {
    document.getElementById('admin-sidebar').classList.add('open');
    document.getElementById('sidebar-overlay').classList.add('open');
}
function closeSidebar() {
    document.getElementById('admin-sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
}
function toggleTheme() {
    document.documentElement.classList.toggle('dark');
    const isDark = document.documentElement.classList.contains('dark');
    localStorage.setItem('admin-theme', isDark ? 'dark' : 'light');
}
</script>
<?php include __DIR__ . '/../includes/order_tracking_widget.php'; ?>
</body>
</html>
