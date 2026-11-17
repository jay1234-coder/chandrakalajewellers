<?php
/**
 * Shared site header — mobile-first, minimal bar.
 * Set $activePage before including: home | shop | catalogue | about | contact | blog | cart
 */
if (!isset($activePage)) {
    $activePage = '';
}
?>
<?php include __DIR__ . '/gtm-body.php'; ?>
<a href="#main-content" class="skip-link">Skip to content</a>
<header class="header" id="siteHeader">
    <div class="header-content">
        <a href="index1" class="logo-container">
            <img src="images/logo.png" alt="ChandraKala Jewellers" class="header-logo" width="40" height="40" decoding="async" fetchpriority="high">
            <div class="logo-text">
                <span class="site-brand">ChandraKala</span>
                <span class="site-brand-full">ChandraKala Jewellers</span>
                <span class="subtitle">Elegance &amp; Sparkle</span>
            </div>
        </a>

        <div class="nav-right">
            <nav id="nav-menu" class="header-nav" aria-label="Main navigation">
                <a href="index1" class="<?= $activePage === 'home' ? 'active-link' : '' ?>">Home</a>
                <a href="gallery" class="<?= $activePage === 'shop' ? 'active-link' : '' ?>">Shop</a>
                <a href="Catalogue" class="<?= $activePage === 'catalogue' ? 'active-link' : '' ?>">Catalogue</a>
                <a href="about" class="<?= $activePage === 'about' ? 'active-link' : '' ?>">About</a>
                <a href="contact" class="<?= $activePage === 'contact' ? 'active-link' : '' ?>">Contact</a>
                <a href="blog" class="<?= $activePage === 'blog' ? 'active-link' : '' ?>">Blog</a>
            </nav>

            <?php if (function_exists('cj_feature') && cj_feature('feat_wishlist')): ?>
            <a href="wishlist" class="header-icon-btn<?= $activePage === 'wishlist' ? ' active-link' : '' ?>" aria-label="Wishlist">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                <span id="wishlist-count">0</span>
            </a>
            <?php endif; ?>

            <?php if (function_exists('cj_feature') && cj_feature('feat_customer_accounts')): ?>
            <a href="account" class="header-icon-btn<?= $activePage === 'account' ? ' active-link' : '' ?>" aria-label="<?= (function_exists('cj_customer_session') && cj_customer_session()) ? 'My account' : 'Sign in' ?>">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </a>
            <?php endif; ?>

            <a href="cart" class="header-icon-btn cart-icon<?= $activePage === 'cart' ? ' active-link' : '' ?>" aria-label="Shopping cart">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                <span id="cart-count">0</span>
            </a>

            <div class="header-settings" id="headerSettings">
                <button type="button" class="header-icon-btn" id="header-settings-btn" aria-label="Settings" aria-expanded="false" aria-haspopup="true" aria-controls="header-settings-panel">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 01-1.4 3.4h-.1a1.7 1.7 0 00-1.6 1.1 1.7 1.7 0 00-.3.9V22a2 2 0 01-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.9.3l-.1.1a2 2 0 01-3.4-1.4v-.1a1.7 1.7 0 00-1.1-1.6 1.7 1.7 0 00-.9-.3H2a2 2 0 010-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.9l-.1-.1a2 2 0 011.4-3.4h.1a1.7 1.7 0 001.6-1.1 1.7 1.7 0 00.3-.9V2a2 2 0 014 0v.1a1.7 1.7 0 001.1 1.5 1.7 1.7 0 001.9-.3l.1-.1a2 2 0 013.4 1.4v.1a1.7 1.7 0 001.1 1.6 1.7 1.7 0 00.9.3H22a2 2 0 010 4h-.1a1.7 1.7 0 00-1.5 1.1z"/>
                    </svg>
                    <span class="bell-badge" id="push-bell-badge" hidden>●</span>
                </button>

                <div class="settings-panel" id="header-settings-panel" hidden>
                    <div class="settings-panel-head">
                        <h4>Settings</h4>
                        <button type="button" class="settings-close" id="header-settings-close" aria-label="Close settings">&times;</button>
                    </div>
                    <div class="settings-panel-body">
                        <div class="setting-row setting-row-theme">
                            <div class="setting-info">
                                <strong>Theme</strong>
                                <p>Light, dark, or match your phone</p>
                            </div>
                            <div id="cjThemeToggle" class="cj-theme-seg" role="group" aria-label="Colour theme">
                                <button type="button" data-theme-opt="light" aria-pressed="false">Light</button>
                                <button type="button" data-theme-opt="dark" aria-pressed="false">Dark</button>
                                <button type="button" data-theme-opt="system" aria-pressed="false">Auto</button>
                            </div>
                        </div>

                        <div class="setting-row">
                            <div class="setting-info">
                                <strong>Push Alerts</strong>
                                <p>Offers &amp; live rates</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="push-notify-toggle">
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <div class="setting-row">
                            <div class="setting-info">
                                <strong>In-App Alerts</strong>
                                <p>Popups while browsing</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="in-app-notify-toggle">
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <div class="setting-row">
                            <div class="setting-info">
                                <strong>Location</strong>
                                <p>For store visit map</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="header-location-toggle">
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <div class="setting-row" id="header-install-row" hidden>
                            <div class="setting-info">
                                <strong>Install App</strong>
                                <p>Add to home screen</p>
                            </div>
                            <button type="button" class="btn btn-primary-gold btn-xs" id="header-install-btn">Install</button>
                        </div>
                    </div>
                    <div class="settings-panel-status" id="push-dropdown-status"></div>
                </div>
            </div>

            <button type="button" class="header-icon-btn" id="menu-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="nav-menu">
                <span class="menu-toggle-bars" aria-hidden="true"></span>
            </button>
        </div>
    </div>
    <div class="nav-backdrop" id="nav-backdrop" hidden></div>
</header>
