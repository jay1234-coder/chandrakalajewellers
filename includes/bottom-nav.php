<?php
/**
 * Mobile app-style bottom navigation.
 * Requires $activePage: home | shop | catalogue | contact | blog | cart
 */
if (!isset($activePage)) {
    $activePage = '';
}
$tabs = [
    ['id' => 'home', 'href' => 'index1', 'label' => 'Home', 'icon' => 'home'],
    ['id' => 'shop', 'href' => 'gallery', 'label' => 'Shop', 'icon' => 'shop'],
    ['id' => 'catalogue', 'href' => 'Catalogue', 'label' => 'Designs', 'icon' => 'grid'],
    ['id' => 'contact', 'href' => 'contact', 'label' => 'Contact', 'icon' => 'pin'],
    ['id' => 'cart', 'href' => 'cart', 'label' => 'Cart', 'icon' => 'bag'],
];
?>
<nav class="app-bottom-nav" aria-label="Mobile app navigation">
    <?php foreach ($tabs as $tab):
        $isActive = ($activePage === $tab['id']) || ($tab['id'] === 'shop' && $activePage === 'shop');
    ?>
        <a href="<?= htmlspecialchars($tab['href']) ?>" class="app-tab<?= $isActive ? ' is-active' : '' ?>" data-tab="<?= htmlspecialchars($tab['id']) ?>">
            <span class="app-tab-icon" aria-hidden="true">
                <?php if ($tab['icon'] === 'home'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
                <?php elseif ($tab['icon'] === 'shop'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16l-1.2 13.2a1 1 0 01-1 .8H6.2a1 1 0 01-1-.8L4 7zm4-3h8l1 3H7l1-3z"/></svg>
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
