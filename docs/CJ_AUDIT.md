# ChandraKala Jewellers — Full Software Audit

Date: 18 Sep 2026  
Scope: **Production PHP shop** (repo root). Flutter / Node / Postgres apps were reviewed only for overlap, not redesigned.  
Existing storefront design and checkout flow were preserved.

All **65** PHP checks in `tests/run.php` pass after the fixes below.

---

## How to read this report

| Severity | Meaning |
|----------|---------|
| Critical | Money, stock, or admin takeover |
| High | Customer PII, payments, or catalogue pricing wrong |
| Medium | Quality, CSRF hygiene, operations |
| Low | Hardening / polish |

**Fixed in this pass** means the PHP source now contains the patch. Deploy to InfinityFree before production is protected. After deploy, **rotate the MySQL password** and set `CJ_CRON_SECRET` / `CJ_ADMIN_GATE_SECRET` in `config/.env`.

---

## 1. UI & UX

**Status:** Storefront is coherent (gold palette, rates board, hot favourites, skip-to-content). Live homepage loads, FAQ, cart, and account prompt work.

| Finding | Sev | Action |
|---------|-----|--------|
| Empty / missing product photos showed a broken `src` | High | Placeholder SVG + `img.php` fallback + `onerror` |
| Cart/checkout built HTML from product names (XSS if a name is poisoned) | Medium | Names/URLs escaped |
| Checkout could submit sold-out lines | Medium | Sold-out lines excluded; submit disabled |
| Product page showed only a final rupee | Medium | Making % / fixed-price note added |

---

## 2. Responsive Design

**Status:** Header, bottom nav, gallery filters, and cart tables already adapt. No layout rewrite.

Remaining: admin tables on small phones still scroll sideways (accepted for staff).

---

## 3. Product Catalog

**Status:** Unique-piece model (qty 1) is correct for jewellery. Gallery cards, sold-out badge, and related items are in place.

| Finding | Sev | Action |
|---------|-----|--------|
| Product fetch used interpolated SQL | Low | Prepared statement |
| Copper pieces labelled “Unknown” | Medium | Copper added to metal maps and shop filters |

---

## 4. Jewellery Data

| Finding | Sev | Action |
|---------|-----|--------|
| `manual_price` saved in admin but ignored on shop/cart/order | High | `cj_item_unit_price()` honours override |
| Purity (18K/24K) was display-only; all gold priced as 22K | High | Fineness maps into the 22K formula step |
| Copper stored **per kg** (₹2,550/KG on the live board) but formula treated it as per gram (~1000×) | High | `cj_calculate_price` divides copper by 1000; board still shows /KG |
| Rates portal labelled gold/silver **per 10g** while engine is **per 1g** | High | Labels aligned to per 1g; cache refresh after save |
| No hallmark field | Medium | `hallmark` column + product spec row (admin form still optional) |

---

## 5. Search & Filters

**Status:** Gallery search + price + metal filters work. Copper filter added.

Remaining: full-catalog PHP scan when `q` is set will get slow if the catalogue grows a lot.

---

## 6. Pricing & Tax

| Finding | Sev | Action |
|---------|-----|--------|
| Razorpay verify compared **subtotal**, gateway charges **payable** (GST exclusive would fail) | High | Verify uses `cj_order_payable_rupees()` |
| Cached prices ignored manual override / purity | High | Refresh uses `cj_item_unit_price()` |

Remaining: gold formula already `÷ 1.03` (GST in the board). Turning **on** checkout GST as well can double-count tax. Keep `feat_gst` off unless you remove the 1.03 step.

---

## 7. Inventory

| Finding | Sev | Action |
|---------|-----|--------|
| Admin “Available” toggle ignored an active reservation | High | Block restock while `reserved_order_id` is set |
| Paying after the 24h hold expired captured money but left the order expired | Critical | 2-hour Razorpay grace; then re-lock or auto-refund |

Place-order lock (`FOR UPDATE` + `affected_rows === 1`) was already solid.

---

## 8. Cart

**Status:** Prices are recomputed on the server (not trusted from `localStorage`). XSS + sold-out + placeholder images fixed.

Remaining: cart is device-local only (no account cart).

---

## 9. Checkout

**Status:** CSRF on `place_order.php` was already present. Redirect now includes an unguessable `t=` access token.

Sold-out lines no longer inflate the payable total in the UI.

---

## 10. Payments

| Finding | Sev | Action |
|---------|-----|--------|
| Verify amount ≠ GST payable | High | Fixed |
| Webhook marked paid without amount check | High | Amount mismatch rejected |
| Expired reservation + captured payment | Critical | Relock or refund |

HMAC checkout + webhook signatures were already correct (fail closed if webhook secret empty).

---

## 11. Shipping

**Status:** PIN optional, WhatsApp confirmation, no courier API. WhatsApp does not reserve stock (by design for catalogue enquiries). Buy Now goes through checkout (correct).

---

## 12. Orders

| Finding | Sev | Action |
|---------|-----|--------|
| Guessable `CKJ` + date + 4 hex `?ref=` leaked name/phone/address | High | Longer order no, `access_token`, PII hidden without token/session |

---

## 13. Customer Accounts

Optional accounts remain optional. Login now rate-limits and regenerates the session id.

---

## 14. Authentication

| Finding | Sev | Action |
|---------|-----|--------|
| Rates portal hardcoded password minted a full `loggedin` admin session | Critical | Portal requires real admin/superadmin login |
| Password-only admin login (no username) | High | Removed; username + password required |
| Guessable `?secret=cjsecret123` gate | High | Secret only from `CJ_ADMIN_GATE_SECRET` / config file (no default) |

Use `/secret` to sign in. Set a long `CJ_ADMIN_GATE_SECRET` if you still want the extra 404 gate.

---

## 15. Admin Panel

| Finding | Sev | Action |
|---------|-----|--------|
| Gemini API key embedded in page JavaScript | High | Server proxy `admin/gemini-describe.php`; key not printed in HTML |
| AJAX image upload had no CSRF | Medium | CSRF token required |
| Upload dirs `0777` | Medium | `0755` |

Remaining: favourite/sold-out/delete still use GET + token in the URL (leaks via Referer). Convert to POST later.

---

## 16. Database

Credentials live in `config/.env` (blocked by `config/.htaccess`). A SQL dump still exists in the web tree; `.sql` is forbidden by rewrite, but **delete it from production** and store backups outside the docroot.

Rotate the InfinityFree MySQL password after any previous leak.

---

## 17. Backend APIs

PHP JSON endpoints (`place_order`, `verify-payment`, `get_cart_items`, `account-auth`) now send JSON headers where missing and reprice on the server.

Node/Express in `backend/` is the migration preview, not live traffic.

---

## 18. Security

| Finding | Sev | Action |
|---------|-----|--------|
| Unauthenticated `/db-diagnostic` | High | Superadmin only |
| `/scratch/*` printed the admin gate secret | High | HTTP denied (`scratch/.htaccess` + rewrite) |
| `/tools/*` reachable | Medium | HTTP denied |
| Weak cron key fallback | High | Empty secret never matches; use `CJ_CRON_SECRET` |

Already good: session cookies HttpOnly / SameSite=Lax / Secure on HTTPS, CSP, HSTS, uploads cannot run PHP.

**You must do on the server:** set `CJ_CRON_SECRET` to the same value currently used in cron-job.org (then rotate both). Daily push will 403 until that env is set.

---

## 19. Performance

Lazy images, `img.php` resize/WebP cache, gzip, and long-cache static assets were already in place. Missing files no longer 404 the whole card.

---

## 20. Accessibility

Skip link, many `aria-label`s, and form errors exist. Remaining: some admin icon-only controls, colour contrast on gold-on-cream, and cart tables without row headers.

---

## 21. SEO & Sharing

Canonical host, sitemap/robots PHP, Open Graph image fallback to logo were already present. Order pages stay `noindex`.

---

## 22. Media & Assets

Placeholder + `img.php` 404 → SVG. Catalogue folder still blocks PHP execution.

---

## 23. Notifications

Push cron is gated on a non-empty secret. Abandoned-cart beacon is still an open write (admin list only) — rate-limit later.

---

## 24. Testing & Reliability

`php tests/run.php` — **65 passed, 0 failed** (pricing, GST, payable, purity, manual price, copper kg→g, order access, placeholders).

---

## 25. Deployment & Operations

PHP remains the live shop. Do not deploy the repo root as a Vercel app.

**Deploy checklist**

1. Upload the patched PHP files (not `frontend/` / `backend/` as the public site).
2. Add to `config/.env` on InfinityFree:
   - `CJ_CRON_SECRET=` (required for daily/engage push)
   - `CJ_ADMIN_GATE_SECRET=` (optional extra gate)
3. Rotate MySQL password; remove `if0_38635607_cjdb.sql` from the server.
4. Confirm Razorpay webhook still hits `/webhook-razorpay` with the dashboard secret.
5. Sign in at `/secret` with **username + password**.
6. Update one manual-rate SKU and one 18K piece; confirm gallery price matches product/cart.

---

## Flutter + Node (planned stack)

Not live. Do not cut over until: JWT secrets are not default, payments amount-check matches PHP, and `access_token` / reservation expiry behaviour is copied. See `MIGRATION_CHECKLIST.md`.

---

## Remaining (next pass)

1. Convert admin delete / fav / sold-out from GET to POST.
2. Hallmark field on the admin product form.
3. Per-IP checkout reservation cap (stock DoS).
4. Optional signed-in cart sync.
5. Document one GST policy (board ÷1.03 **or** checkout GST, not both).
