<?php
/**
 * Layout footer — closes the tags opened by main.php.
 * Include this at the very end of every page file.
 */
?>
        </main><!-- /main -->
    </div><!-- /content area -->
</div><!-- /flex wrapper -->

<script>
/**
 * Toggle the sidebar on mobile screens.
 */
function toggleSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebar-overlay');
    const isHidden = sidebar.classList.contains('-translate-x-full');

    if (isHidden) {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }
}
</script>
</body>
</html>
