<?php
$cjCust = function_exists('cj_customer_session') ? cj_customer_session() : null;
if (!function_exists('cj_feature') || !cj_feature('feat_customer_accounts')) {
    return;
}
?>
<dialog id="cj-account-modal" class="cj-account-dialog" aria-labelledby="cj-account-title">
    <div class="cj-account-card">
        <button type="button" class="cj-account-close" id="cj-account-close" aria-label="Close">&times;</button>
        <p class="cj-account-eyebrow" id="cj-account-reason">Optional account</p>
        <h2 id="cj-account-title">Save your orders?</h2>
        <p class="cj-account-copy">Create an account to see order history. You can skip — shopping as a guest still works.</p>
        <p id="cj-account-error" class="cj-account-error" hidden></p>
        <form id="cj-account-form" class="cj-account-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" id="cj-account-action" value="register">
            <label>Name
                <input name="name" id="cj-account-name" maxlength="120" autocomplete="name" value="<?= htmlspecialchars($cjCust['name'] ?? '') ?>">
            </label>
            <label>Mobile <span id="cj-account-phone-hint"></span>
                <input name="phone" id="cj-account-phone" type="text" maxlength="160" autocomplete="tel" required>
            </label>
            <label>Email <span>(optional, for invoices)</span>
                <input name="email" id="cj-account-email" type="text" inputmode="email" maxlength="160" autocomplete="email" autocapitalize="off" spellcheck="false">
            </label>
            <label>Password
                <input name="password" id="cj-account-password" type="password" minlength="6" autocomplete="new-password" required>
            </label>
            <button type="submit" class="btn" id="cj-account-submit">Create account</button>
        </form>
        <p class="cj-account-switch">
            <button type="button" id="cj-account-toggle">Already have an account? Sign in</button>
        </p>
        <button type="button" class="btn btn-secondary" id="cj-account-skip">Not now</button>
    </div>
</dialog>
