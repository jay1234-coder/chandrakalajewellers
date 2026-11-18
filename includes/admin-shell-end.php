<?php
/**
 * Unified Admin App Shell — Closing Layout + Mobile Bottom App Navbar + Shell JS
 */
$cjShellPage          = isset($cjShellPage) ? (string)$cjShellPage : 'overview';
$cjShellEmbed         = !empty($cjShellEmbed);
$cjShellPendingOrders = isset($cjShellPendingOrders) ? (int)$cjShellPendingOrders : 0;

$cjShellMenuPages = ['add-item', 'catalogue', 'reels', 'customers', 'notifications', 'follow-us', 'analytics'];
$cjShellMenuActive = in_array($cjShellPage, $cjShellMenuPages, true);
?>
<?php if ($cjShellEmbed): ?>
    </main>
<?php else: ?>
        </main>
    </div><!-- /.cj-shell-workspace -->
</div><!-- /.cj-shell-layout -->

<!-- Fixed Mobile Bottom App Navbar (< 1024px) -->
<nav class="cj-shell-mob-bottom" aria-label="Admin quick tabs">
    <a href="/admin/index.php?view=overview" class="cj-shell-tab <?= $cjShellPage === 'overview' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/></svg>
        <span>Overview</span>
    </a>
    <a href="/admin/index.php?view=rates" class="cj-shell-tab <?= $cjShellPage === 'rates' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
        <span>Rates</span>
    </a>
    <a href="/admin/index.php?view=inventory" class="cj-shell-tab <?= $cjShellPage === 'inventory' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        <span>Stock</span>
    </a>
    <a href="/admin/orders.php" class="cj-shell-tab <?= $cjShellPage === 'orders' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        <?php if ($cjShellPendingOrders > 0): ?>
            <span class="cj-shell-tab-badge"><?= $cjShellPendingOrders ?></span>
        <?php endif; ?>
        <span>Orders</span>
    </a>
    <button type="button" id="cjShellMobMenuTab" class="cj-shell-tab <?= $cjShellMenuActive ? 'is-active' : '' ?>" aria-label="More admin sections">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1.5" fill="currentColor"/><circle cx="19" cy="12" r="1.5" fill="currentColor"/><circle cx="5" cy="12" r="1.5" fill="currentColor"/></svg>
        <span>More</span>
    </button>
</nav>

<script>
(function () {
    var drawer = document.getElementById('cjShellDrawer');
    var backdrop = document.getElementById('cjShellBackdrop');
    var openBtn = document.getElementById('cjShellDrawerOpen');
    var closeBtn = document.getElementById('cjShellDrawerClose');
    var moreTab = document.getElementById('cjShellMobMenuTab');

    function setDrawer(open) {
        if (!drawer || !backdrop) return;
        drawer.classList.toggle('is-open', open);
        backdrop.classList.toggle('is-open', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.style.overflow = open ? 'hidden' : '';
        if (open && window.CJTheme && window.CJTheme.sync) {
            window.CJTheme.sync();
        }
    }

    if (openBtn) openBtn.addEventListener('click', function () { setDrawer(true); });
    if (moreTab) moreTab.addEventListener('click', function () { setDrawer(true); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setDrawer(false); });
    if (backdrop) backdrop.addEventListener('click', function () { setDrawer(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer && drawer.classList.contains('is-open')) {
            setDrawer(false);
        }
    });

    // Quick Light/Dark toggle in mobile topbar
    var mobThemeBtn = document.getElementById('cjShellMobThemeBtn');
    if (mobThemeBtn) {
        var sunSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>';
        var moonSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
        function paintMobTheme() {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            mobThemeBtn.innerHTML = isDark ? sunSvg : moonSvg;
            mobThemeBtn.title = isDark ? 'Switch to Light mode' : 'Switch to Dark mode';
        }
        paintMobTheme();
        mobThemeBtn.addEventListener('click', function () {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (window.CJTheme && window.CJTheme.set) {
                window.CJTheme.set(isDark ? 'light' : 'dark');
            }
            paintMobTheme();
        });
        document.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('[data-theme-opt]')) {
                setTimeout(paintMobTheme, 20);
            }
        });
    }

    // Live IST Clock in Desktop Topbar + Mobile Drawer
    var clocks = document.querySelectorAll('[data-cj-shell-clock]');
    if (clocks.length && typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
        var fmt = new Intl.DateTimeFormat('en-IN', {
            timeZone: 'Asia/Kolkata',
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        function tickShellClock() {
            var txt = fmt.format(new Date()).replace(/\bam\b/i, 'AM').replace(/\bpm\b/i, 'PM') + ' IST';
            clocks.forEach(function (el) { el.textContent = txt; });
        }
        tickShellClock();
        setInterval(tickShellClock, 1000);
    }
})();
</script>
<?php endif; ?>
