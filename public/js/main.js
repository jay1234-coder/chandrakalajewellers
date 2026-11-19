(function () {
    'use strict';

    function initMobileMenu() {
        var menuToggle = document.getElementById('menu-toggle');
        var navMenu = document.getElementById('nav-menu');
        var backdrop = document.getElementById('nav-backdrop');
        var settingsBtn = document.getElementById('header-settings-btn');
        var settingsPanel = document.getElementById('header-settings-panel');
        var settingsClose = document.getElementById('header-settings-close');
        var header = document.getElementById('siteHeader');

        function closeNav() {
            if (!navMenu) return;
            navMenu.classList.remove('show');
            if (menuToggle) {
                menuToggle.setAttribute('aria-expanded', 'false');
                menuToggle.classList.remove('is-open');
            }
            if (backdrop) backdrop.hidden = true;
            document.body.classList.remove('nav-open');
        }

        function openNav() {
            if (!navMenu) return;
            closeSettings();
            navMenu.classList.add('show');
            if (menuToggle) {
                menuToggle.setAttribute('aria-expanded', 'true');
                menuToggle.classList.add('is-open');
            }
            if (backdrop) backdrop.hidden = false;
            document.body.classList.add('nav-open');
        }

        function closeSettings() {
            if (!settingsPanel) return;
            settingsPanel.hidden = true;
            if (settingsBtn) settingsBtn.setAttribute('aria-expanded', 'false');
        }

        function openSettings() {
            if (!settingsPanel) return;
            closeNav();
            settingsPanel.hidden = false;
            if (settingsBtn) settingsBtn.setAttribute('aria-expanded', 'true');
            try {
                document.dispatchEvent(new CustomEvent('cj:settings-open'));
            } catch (e) {}
            if (window.CJTheme && window.CJTheme.sync) window.CJTheme.sync();
        }

        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                if (navMenu.classList.contains('show')) closeNav();
                else openNav();
            });

            navMenu.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', closeNav);
            });
        }

        if (settingsBtn && settingsPanel) {
            settingsBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (settingsPanel.hidden) openSettings();
                else closeSettings();
            });
        }

        if (settingsClose) {
            settingsClose.addEventListener('click', function (e) {
                e.stopPropagation();
                closeSettings();
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                closeNav();
                closeSettings();
            });
        }

        document.addEventListener('click', function (e) {
            if (settingsPanel && !settingsPanel.hidden) {
                var wrap = document.getElementById('headerSettings');
                if (wrap && !wrap.contains(e.target)) closeSettings();
            }
            if (navMenu && navMenu.classList.contains('show')) {
                if (navMenu.contains(e.target) || (menuToggle && menuToggle.contains(e.target))) return;
                closeNav();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeNav();
                closeSettings();
            }
        });

        window.addEventListener('resize', function () {
            if (window.matchMedia('(min-width: 961px)').matches) {
                closeNav();
            }
        });
    }

    function getCart() {
        try {
            return JSON.parse(localStorage.getItem('cart')) || [];
        } catch (err) {
            return [];
        }
    }

    function updateCartCount() {
        const count = getCart().length;
        const el = document.getElementById('cart-count');
        if (el) el.textContent = count;

        const appBadge = document.getElementById('app-cart-count');
        if (appBadge) {
            appBadge.textContent = count > 0 ? String(count) : '';
            appBadge.setAttribute('data-empty', count > 0 ? '0' : '1');
        }
    }

    function showToast(message) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML =
            '<svg style="width: 18px; height: 18px; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> <span></span>';
        toast.querySelector('span').textContent = message;
        container.appendChild(toast);

        setTimeout(function () {
            toast.style.animation = 'slideOut 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards';
            setTimeout(function () {
                toast.remove();
            }, 300);
        }, 3000);
    }

    function addToCart(productId, productName) {
        var cart = getCart();

        if (!cart.includes(productId) && !cart.includes(String(productId))) {
            cart.push(productId);
            localStorage.setItem('cart', JSON.stringify(cart));
            try { localStorage.setItem('cart_saved_at', String(Date.now())); } catch (e) {}
            showToast('"' + productName + '" added to cart!');
            updateCartCount();
            pulseCartTab();
            pingAbandonedCart();
        } else {
            showToast('"' + productName + '" is already in your cart!');
        }
    }

    function pulseCartTab() {
        var tab = document.querySelector('.app-tab[data-tab="cart"]');
        if (!tab) return;
        tab.style.transform = 'scale(1.08)';
        setTimeout(function () {
            tab.style.transform = '';
        }, 220);
    }

    function getWishlist() {
        try {
            return JSON.parse(localStorage.getItem('wishlist')) || [];
        } catch (err) {
            return [];
        }
    }

    function updateWishlistButtonStates() {
        var list = getWishlist();
        var buttons = document.querySelectorAll('[data-product-id]');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var id = Number(btn.getAttribute('data-product-id'));
            var isInWishlist = list.indexOf(id) >= 0;
            if (isInWishlist) {
                btn.classList.add('active');
                if (btn.classList.contains('btn-secondary') && btn.getAttribute('id') === 'wishlist-btn-detail') {
                    btn.textContent = 'Saved to Wishlist';
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-primary');
                }
            } else {
                btn.classList.remove('active');
                if (btn.classList.contains('btn-primary') && btn.getAttribute('id') === 'wishlist-btn-detail') {
                    btn.textContent = 'Wishlist';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-secondary');
                }
            }
        }
    }

    function updateWishlistCount() {
        var count = getWishlist().length;
        var el = document.getElementById('wishlist-count');
        if (el) el.textContent = count > 0 ? String(count) : '';
        try {
            updateWishlistButtonStates();
        } catch (e) {}
    }

    function toggleWishlist(productId, productName) {
        var list = getWishlist();
        var id = Number(productId);
        var idx = list.findIndex(function (x) { return Number(x) === id; });
        var added = false;
        if (idx >= 0) {
            list.splice(idx, 1);
            showToast('Removed from wishlist');
        } else {
            list.push(id);
            added = true;
            showToast('"' + productName + '" saved to wishlist');
        }
        localStorage.setItem('wishlist', JSON.stringify(list));
        updateWishlistCount();
        if (added) {
            maybeAskAccount('wishlist');
        }
    }

    function accountPromptDone() {
        try {
            return localStorage.getItem('cj_account_prompt_done') === '1';
        } catch (e) {
            return false;
        }
    }

    function markAccountPromptDone() {
        try { localStorage.setItem('cj_account_prompt_done', '1'); } catch (e) {}
    }

    function closeAccountModal() {
        var dlg = document.getElementById('cj-account-modal');
        if (dlg && typeof dlg.close === 'function') dlg.close();
    }

    var pendingReviewForm = null;

    function maybeAskAccount(reason, form) {
        var shop = window.CJ_SHOP || {};
        if (!shop.askAccount || shop.loggedIn || accountPromptDone()) {
            return false;
        }
        var dlg = document.getElementById('cj-account-modal');
        if (!dlg || typeof dlg.showModal !== 'function') {
            return false;
        }
        pendingReviewForm = form || null;
        var reasonEl = document.getElementById('cj-account-reason');
        if (reasonEl) {
            reasonEl.textContent = reason === 'review' ? 'Before you review' : 'Saved to wishlist';
        }
        dlg.showModal();
        return true;
    }

    function initAccountModal() {
        var dlg = document.getElementById('cj-account-modal');
        if (!dlg) return;
        var form = document.getElementById('cj-account-form');
        var skip = document.getElementById('cj-account-skip');
        var closeBtn = document.getElementById('cj-account-close');
        var toggle = document.getElementById('cj-account-toggle');
        var action = document.getElementById('cj-account-action');
        var submit = document.getElementById('cj-account-submit');
        var err = document.getElementById('cj-account-error');
        var nameRow = document.getElementById('cj-account-name');
        var emailRow = document.getElementById('cj-account-email');
        var isLogin = false;

        function finishAndContinue() {
            markAccountPromptDone();
            closeAccountModal();
            if (pendingReviewForm) {
                var f = pendingReviewForm;
                pendingReviewForm = null;
                f.submit();
            }
        }

        function skipPrompt() {
            markAccountPromptDone();
            finishAndContinue();
        }

        if (skip) skip.addEventListener('click', skipPrompt);
        if (closeBtn) closeBtn.addEventListener('click', skipPrompt);
        if (toggle) {
            toggle.addEventListener('click', function () {
                isLogin = !isLogin;
                if (action) action.value = isLogin ? 'login' : 'register';
                if (submit) submit.textContent = isLogin ? 'Sign in' : 'Create account';
                toggle.textContent = isLogin ? 'Need an account? Register' : 'Already have an account? Sign in';
                if (nameRow) nameRow.closest('label').hidden = isLogin;
                if (emailRow) emailRow.closest('label').hidden = isLogin;
            });
        }
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (err) { err.hidden = true; err.textContent = ''; }
                var fd = new FormData(form);
                fetch('account-auth.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.ok) {
                            if (err) {
                                err.hidden = false;
                                err.textContent = (data && data.message) || 'Could not continue.';
                            }
                            return;
                        }
                        if (window.CJ_SHOP) window.CJ_SHOP.loggedIn = true;
                        showToast(data.message || 'Saved');
                        finishAndContinue();
                    })
                    .catch(function () {
                        if (err) {
                            err.hidden = false;
                            err.textContent = 'Network error. You can skip for now.';
                        }
                    });
            });
        }

        var reviewForm = document.getElementById('review-form');
        if (reviewForm) {
            reviewForm.addEventListener('submit', function (e) {
                if (maybeAskAccount('review', reviewForm)) {
                    e.preventDefault();
                }
            });
        }
    }

    function pingAbandonedCart() {
        var ids = getCart();
        var session = '';
        try {
            session = localStorage.getItem('cj_cart_session') || '';
            if (!session) {
                session = 'c' + Math.random().toString(36).slice(2) + Date.now().toString(36);
                localStorage.setItem('cj_cart_session', session);
            }
        } catch (e) {
            session = 'anon';
        }
        try {
            fetch('api/abandon-cart', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: ids, session: session }),
                keepalive: true
            }).catch(function () {});
        } catch (e) {}
    }

    function initAbandonedBanner() {
        var shop = window.CJ_SHOP || {};
        if (shop.featAbandoned === false) return;
        var cart = getCart();
        if (!cart.length) return;
        var saved = 0;
        try { saved = parseInt(localStorage.getItem('cart_saved_at') || '0', 10); } catch (e) { saved = 0; }
        if (!saved || (Date.now() - saved) < 45 * 60 * 1000) {
            pingAbandonedCart();
            return;
        }
        if (document.getElementById('abandon-banner')) return;
        var bar = document.createElement('div');
        bar.id = 'abandon-banner';
        bar.setAttribute('role', 'status');
        bar.style.cssText = 'position:sticky;top:0;z-index:50;background:#fff7ed;border-bottom:1px solid #fdba74;padding:10px 16px;text-align:center;font-size:13px;';
        bar.innerHTML = 'You still have pieces in your cart. <a href="cart" style="font-weight:700;color:#c2410c;">Complete your order</a>';
        document.body.insertBefore(bar, document.body.firstChild);
        pingAbandonedCart();
    }

    function ensureMainId() {
        var main = document.querySelector('main');
        if (main && !main.id) main.id = 'main-content';
    }

    function initScrollReveal() {
        const reveals = document.querySelectorAll('.reveal');
        if (!reveals.length) return;

        if (!('IntersectionObserver' in window)) {
            reveals.forEach(function (el) {
                el.classList.add('visible');
            });
            return;
        }

        const observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
        );

        reveals.forEach(function (el) {
            observer.observe(el);
        });
    }

    function initGreeting() {
        var root = document.getElementById('app-greeting');
        var shopLine = document.getElementById('shop-greeting');
        var hour = new Date().getHours();

        var data;
        if (hour >= 5 && hour < 12) {
            data = {
                eye: 'Good Morning',
                title: 'Namaste, shine begins',
                sub: 'Browse today\'s gold rate and fresh favourites from ChandraKala.',
                short: 'Good morning — find your sparkle'
            };
        } else if (hour >= 12 && hour < 17) {
            data = {
                eye: 'Good Afternoon',
                title: 'Welcome back',
                sub: 'Discover 916 gold & silver pieces crafted for every occasion.',
                short: 'Good afternoon — shop gold & silver'
            };
        } else if (hour >= 17 && hour < 21) {
            data = {
                eye: 'Good Evening',
                title: 'Evening sparkle awaits',
                sub: 'Find festive jewellery or message us on WhatsApp anytime.',
                short: 'Good evening — explore festive designs'
            };
        } else {
            data = {
                eye: 'Welcome',
                title: 'Jewellery that lasts',
                sub: 'Explore hallmark gold & silver from our Khedbrahma store.',
                short: 'Welcome to ChandraKala Jewellers'
            };
        }

        if (root) {
            var eyebrow = document.getElementById('greeting-eyebrow');
            var title = document.getElementById('greeting-title');
            var sub = document.getElementById('greeting-sub');
            if (eyebrow) eyebrow.textContent = data.eye;
            if (title) title.textContent = data.title;
            if (sub) sub.textContent = data.sub;
        }

        if (shopLine) {
            shopLine.style.display = 'block';
            shopLine.textContent = data.short;
        }

        try {
            if (!sessionStorage.getItem('cj_greeted')) {
                sessionStorage.setItem('cj_greeted', '1');
                setTimeout(function () {
                    showToast(data.eye + ' — ChandraKala Jewellers');
                }, 900);
            }
        } catch (err) {}
    }

    function initAppShell() {
        document.body.classList.add('has-app-nav');

        // Soft press feedback on tabs
        document.querySelectorAll('.app-tab').forEach(function (tab) {
            tab.addEventListener('touchstart', function () {
                tab.classList.add('is-pressing');
            }, { passive: true });
            tab.addEventListener('touchend', function () {
                tab.classList.remove('is-pressing');
            });
        });
    }

    window.showToast = showToast;
    window.addToCart = addToCart;
    window.updateCartCount = updateCartCount;
    window.toggleWishlist = toggleWishlist;
    window.updateWishlistCount = updateWishlistCount;

    document.addEventListener('DOMContentLoaded', function () {
        initMobileMenu();
        updateCartCount();
        updateWishlistCount();
        initScrollReveal();
        initGreeting();
        initAppShell();
        ensureMainId();
        initAbandonedBanner();
        initAccountModal();
    });
})();
