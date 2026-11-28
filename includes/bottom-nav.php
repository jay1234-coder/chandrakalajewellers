<?php
/**
 * Mobile app-style bottom navigation + New Reels Feature Launch Notification.
 * Requires $activePage: home | shop | reels | catalogue | contact | blog | cart
 */
if (!isset($activePage)) {
    $activePage = '';
}
$tabs = [
    ['id' => 'home', 'href' => 'index1', 'label' => 'Home', 'icon' => 'home'],
    ['id' => 'shop', 'href' => 'gallery', 'label' => 'Shop', 'icon' => 'shop'],
    ['id' => 'reels', 'href' => 'reels', 'label' => 'Reels', 'icon' => 'reels', 'is_new' => true],
    ['id' => 'catalogue', 'href' => 'Catalogue', 'label' => 'Designs', 'icon' => 'grid'],
    ['id' => 'contact', 'href' => 'contact', 'label' => 'Contact', 'icon' => 'pin'],
    ['id' => 'cart', 'href' => 'cart', 'label' => 'Cart', 'icon' => 'bag'],
];
?>
<style>
/* Animated NEW Reels button in Mobile Bottom Navbar */
.app-tab.is-reels-new .app-tab-icon {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(245, 230, 168, 0.25) 0%, rgba(212, 175, 55, 0.35) 100%);
    color: var(--primary-gold-dark, #b8972c);
    animation: cjReelIconFloat 2.4s ease-in-out infinite;
    box-shadow: 0 0 0 0 rgba(212, 175, 55, 0.55);
}
.app-tab.is-reels-new .app-tab-icon::before {
    content: "";
    position: absolute;
    inset: -3px;
    border-radius: 12px;
    border: 1.5px solid rgba(212, 175, 55, 0.65);
    animation: cjReelHaloPulse 2s cubic-bezier(0.22, 1, 0.36, 1) infinite;
    pointer-events: none;
}
.app-tab.is-reels-new.is-active .app-tab-icon {
    background: linear-gradient(135deg, #fff3c4 0%, #e5be54 48%, #b88b1e 100%);
    color: #1a1204;
    animation: none;
}
.app-tab.is-reels-new.is-active .app-tab-icon::before {
    display: none;
}
.app-tab-new-pill {
    position: absolute;
    top: -7px;
    right: -12px;
    z-index: 3;
    padding: 1.5px 5px;
    border-radius: 999px;
    background: linear-gradient(135deg, #ef4444 0%, #d97706 100%);
    color: #fff;
    font-size: 7.5px;
    font-weight: 800;
    letter-spacing: 0.05em;
    line-height: 1.25;
    text-transform: uppercase;
    box-shadow: 0 2px 8px rgba(217, 119, 6, 0.5);
    animation: cjReelBadgePop 2.2s ease-in-out infinite;
    pointer-events: none;
}
.app-tab.is-reels-new .app-tab-label {
    font-weight: 700;
    color: var(--primary-gold-dark, #b8972c);
}
html[data-theme="dark"] .app-tab.is-reels-new .app-tab-icon,
html[data-theme="dark"] .app-tab.is-reels-new .app-tab-label {
    color: #fde68a;
}
@media (max-width: 768px) {
    .app-bottom-nav .app-tab {
        padding-left: 2px;
        padding-right: 2px;
    }
    .app-bottom-nav .app-tab-label {
        font-size: 10px;
    }
}
@keyframes cjReelIconFloat {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-2.5px) scale(1.06); }
}
@keyframes cjReelHaloPulse {
    0% { transform: scale(0.96); opacity: 0.9; }
    70% { transform: scale(1.26); opacity: 0; }
    100% { transform: scale(1.26); opacity: 0; }
}
@keyframes cjReelBadgePop {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.12); }
}

/* Luxury New Reels Launch Notification Banner */
.cj-reels-announce {
    position: fixed;
    left: 12px;
    right: 12px;
    bottom: 78px;
    z-index: 9500;
    max-width: 420px;
    margin: 0 auto;
    padding: 13px 14px;
    border-radius: 18px;
    background: linear-gradient(135deg, #161a22 0%, #1f2533 100%);
    color: #f9fafb;
    border: 1.5px solid rgba(240, 215, 140, 0.6);
    box-shadow: 0 18px 46px rgba(0, 0, 0, 0.38), 0 0 28px rgba(212, 175, 55, 0.25);
    display: flex;
    align-items: center;
    gap: 12px;
    opacity: 0;
    transform: translateY(24px) scale(0.96);
    pointer-events: none;
    transition: opacity 0.35s ease, transform 0.38s cubic-bezier(0.22, 1, 0.36, 1);
}
@media (min-width: 769px) {
    .cj-reels-announce {
        left: auto;
        right: 22px;
        bottom: 24px;
        margin: 0;
    }
}
.cj-reels-announce.is-visible {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}
.cj-reels-announce-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #fff3c4 0%, #e5be54 48%, #b88b1e 100%);
    color: #1a1204;
    box-shadow: 0 0 18px rgba(240, 215, 140, 0.45);
    animation: cjReelIconFloat 2s ease-in-out infinite;
}
.cj-reels-announce-body {
    flex: 1;
    min-width: 0;
}
.cj-reels-announce-eyebrow {
    display: inline-block;
    font-size: 9.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #fde68a;
    margin-bottom: 2px;
}
.cj-reels-announce-title {
    font-size: 13px;
    font-weight: 700;
    line-height: 1.25;
    margin: 0 0 3px;
    color: #fff;
}
.cj-reels-announce-sub {
    font-size: 11.5px;
    line-height: 1.35;
    color: #cbd5e1;
    margin: 0 0 8px;
}
.cj-reels-announce-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.cj-reels-announce-cta {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 13px;
    border-radius: 6px;
    background: #d4af37;
    color: #1a1204;
    font-size: 11.5px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}
.cj-reels-announce-close {
    position: absolute;
    top: 8px;
    right: 10px;
    border: none;
    background: transparent;
    color: #94a3b8;
    font-size: 19px;
    line-height: 1;
    cursor: pointer;
    padding: 4px;
}
.cj-reels-announce-close:hover {
    color: #fff;
}
</style>

<nav class="app-bottom-nav" aria-label="Mobile app navigation">
    <?php foreach ($tabs as $tab):
        $isActive = ($activePage === $tab['id']) || ($tab['id'] === 'shop' && $activePage === 'shop');
        $isReelsNew = !empty($tab['is_new']);
    ?>
        <a href="<?= htmlspecialchars($tab['href']) ?>"
           class="app-tab<?= $isActive ? ' is-active' : '' ?><?= $isReelsNew ? ' is-reels-new' : '' ?>"
           data-tab="<?= htmlspecialchars($tab['id']) ?>">
            <span class="app-tab-icon" aria-hidden="true">
                <?php if ($tab['icon'] === 'home'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
                <?php elseif ($tab['icon'] === 'shop'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16l-1.2 13.2a1 1 0 01-1 .8H6.2a1 1 0 01-1-.8L4 7zm4-3h8l1 3H7l1-3z"/></svg>
                <?php elseif ($tab['icon'] === 'reels'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="4" ry="4"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h18M8.5 3L11 8M14.5 3L17 8"/>
                        <polygon points="10,11.2 16,14.5 10,17.8" fill="currentColor" stroke="none"/>
                    </svg>
                    <?php if (!$isActive): ?>
                        <span class="app-tab-new-pill">NEW</span>
                    <?php endif; ?>
                <?php elseif ($tab['icon'] === 'grid'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>
                <?php elseif ($tab['icon'] === 'pin'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.2 7-11a7 7 0 10-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <?php endif; ?>
                <?php if ($tab['id'] === 'cart'): ?>
                    <span class="app-tab-badge" id="app-cart-count">0</span>
                <?php endif; ?>
            </span>
            <span class="app-tab-label"><?= htmlspecialchars($tab['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($activePage !== 'reels'): ?>
<div id="cjReelsAnnounce" class="cj-reels-announce" role="status" aria-live="polite" hidden>
    <button type="button" class="cj-reels-announce-close" id="cjReelsAnnounceClose" aria-label="Dismiss">&times;</button>
    <div class="cj-reels-announce-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="4" ry="4"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h18M8.5 3L11 8M14.5 3L17 8"/>
            <polygon points="10,11.2 16,14.5 10,17.8" fill="currentColor" stroke="none"/>
        </svg>
    </div>
    <div class="cj-reels-announce-body">
        <span class="cj-reels-announce-eyebrow">New Feature Live</span>
        <p class="cj-reels-announce-title">New Jewellery Reels Section!</p>
        <p class="cj-reels-announce-sub">Check out our brand-new Reels — live 916 gold &amp; bridal sparkle in motion.</p>
        <div class="cj-reels-announce-actions">
            <a href="/reels" id="cjReelsAnnounceCta" class="cj-reels-announce-cta">Checkout New Reels &rarr;</a>
        </div>
    </div>
</div>
<script>
(function () {
    var KEY = 'cj_reels_launch_notif_v1';
    var box = document.getElementById('cjReelsAnnounce');
    var closeBtn = document.getElementById('cjReelsAnnounceClose');
    var cta = document.getElementById('cjReelsAnnounceCta');
    if (!box) return;

    function isSeen() {
        try { if (localStorage.getItem(KEY) === '1') return true; } catch (e) {}
        return document.cookie.indexOf(KEY + '=1') !== -1;
    }
    function markSeen() {
        try { localStorage.setItem(KEY, '1'); } catch (e) {}
        document.cookie = KEY + '=1; path=/; max-age=2592000; SameSite=Lax';
    }
    function hideBox() {
        markSeen();
        box.classList.remove('is-visible');
        setTimeout(function () { box.hidden = true; }, 350);
    }

    if (closeBtn) closeBtn.addEventListener('click', hideBox);
    if (cta) cta.addEventListener('click', markSeen);

    // Also mark seen when tapping the Reels tab in bottom nav
    var reelsTab = document.querySelector('.app-tab[data-tab="reels"]');
    if (reelsTab) reelsTab.addEventListener('click', markSeen);

    if (!isSeen()) {
        setTimeout(function () {
            box.hidden = false;
            requestAnimationFrame(function () {
                box.classList.add('is-visible');
            });
        }, 1600);
    }
})();
</script>
<?php else: ?>
<script>
try { localStorage.setItem('cj_reels_launch_notif_v1', '1'); } catch (e) {}
document.cookie = 'cj_reels_launch_notif_v1=1; path=/; max-age=2592000; SameSite=Lax';
</script>
<?php endif; ?>
