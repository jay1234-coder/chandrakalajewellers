# ChandraKala Jewellers — Migration Analysis (Phase 1)

**Date:** 18 September 2026  
**Source of truth:** Existing PHP application in this repository (live production: `https://chandrakalajewellers.in`)  
**Status:** Inspection complete. PHP is **not** moved, deleted, or overwritten. Implementation continues in controlled phases.

This document is based on reading the actual PHP, SQL dump, CSS/JS, admin panel, and the already-started Flutter / Node work. It does **not** invent features that are absent from the code.

---

## 1. Executive summary

Chandrakala Jewellers (CJ) is a **family jewellery store website** for a physical shop on Civil Road, Khedbrahma, Gujarat. It is **not** a generic Shopify-style catalogue:

- Shop stock lives in `jewellery_items` (unique pieces, live metal-rate pricing, 24-hour reservation).
- Design photos live in a separate `products` table (custom-order catalogue, WhatsApp inquiry).
- Cart is **browser `localStorage`**, not a server cart table.
- Checkout creates a **reserved order** (stock lock 24h), then WhatsApp, Razorpay, or pay-later / cash.
- Customer accounts are **optional** and never required at checkout.
- Admin is a **secret-gated PHP panel** with roles `admin` and `superadmin` (and a third staff user `deven` stored as `admin`).

A **partial rewrite already exists** in this folder:

| Path | What it is |
|------|------------|
| PHP at repo root | Production application (source of truth for UI and business logic) |
| `frontend/` | Flutter Web customer app (routes and many screens exist) |
| `admin_app/` | Separate Flutter admin app |
| `backend/` | Vercel **serverless TypeScript** APIs (`api/*.ts`), PostgreSQL (`pg`) |
| `database/` | Partial PostgreSQL schema + seed |

That rewrite is **not complete**, and several parts **do not match the PHP source of truth** (dummy/enriched product copy, incomplete schema, hardcoded JWT fallback, account screen not using PHP auth, images not in the local repo).

**Do not treat the Flutter/Node work as finished.** Treat PHP as the design and behaviour contract.

---

## 2. Existing architecture (PHP)

### 2.1 Runtime

- **Language:** PHP (no Composer; no framework)
- **Web server:** Apache / LiteSpeed (InfinityFree production) + XAMPP locally
- **Database:** MariaDB 11.4 / MySQL (`if0_38635607_cjdb`)
- **Sessions:** PHP sessions + CSRF token; HttpOnly / SameSite=Lax cookies
- **Local override:** `config/db.php` forces `localhost` / `root` / empty password / `if0_38635607_cjdb` when host is localhost

### 2.2 Layout (do not move these during migration)

```
CJ/                          # production PHP site (keep in place)
├── index.php                # splash (bots 301 to index1)
├── index1.php               # homepage
├── gallery.php              # shop / product listing
├── product.php              # product detail
├── Catalogue.php            # design catalogue
├── cart.php, checkout.php, buynow.php, pay.php, success.php, place_order.php
├── account.php, account-auth.php, wishlist.php
├── about.php, contact.php, blog.php, blog-post.php, follow-us.php
├── gold-rate-khedbrahma.php, jewellers-in-khedbrahma.php
├── welcome.php, secret.php  # PWA welcome / admin gate
├── webhook-razorpay.php, verify-payment.php, review-submit.php
├── sitemap.php, robots.php, manifest.php, img.php, get_cart_items.php
├── admin/                   # store admin + superadmin
├── api/                     # small JSON endpoints (push, track, abandon-cart)
├── includes/                # business logic
├── config/                  # db, env, secrets
├── css/, js/                # styles and interactions
├── uploads/, catalogue/     # product / catalogue images (mostly on production host)
├── sql/                     # incremental MySQL patches
├── cron/                    # push jobs
├── backup_ui_pre_opt/       # previous UI snapshot
└── if0_38635607_cjdb.sql    # phpMyAdmin dump (schema + data)
```

There is **no MVC router**. Each public URL is a PHP file. `.htaccess` strips `.php` and rewrites extensionless paths.

### 2.3 Shared includes (backend logic)

| File | Responsibility |
|------|----------------|
| `config/db.php` | mysqli connect, session, timezone Asia/Kolkata |
| `config/env.php` | `.env` loader (`CJ_DB_*`, Razorpay) |
| `includes/security.php` | CSP, HSTS-related HTTPS detection, session cookie flags |
| `includes/auth.php` | Admin login, remember-me tokens, roles |
| `includes/customers.php` | Optional customer register/login (phone + password_hash) |
| `includes/pricing.php` | Metal rate formula + making charges |
| `includes/orders.php` | Reserve / expire / confirm / refund, cached prices |
| `includes/payments.php` | Razorpay REST (curl, no SDK) |
| `includes/catalog.php` | Extra images, reviews, abandoned carts schema |
| `includes/settings.php` | `site_settings` key/value + feature flags |
| `includes/header.php` / `footer.php` / `bottom-nav.php` | Chrome UI |
| `includes/gallery-search.php` | Synonym / NLP-ish shop search |
| `includes/recommend.php` | Related / “AI-style” recommendations |
| `includes/mailer.php` | Optional invoice email (off by default) |
| `includes/push.php` + `includes/pwa-schema.php` | Web push |
| `includes/visitor-analytics-*.php` | Session / pageview analytics |

---

## 3. All discovered customer-facing pages

Extensionless URLs are the public routes (`.htaccess`).

| URL | File | Purpose |
|-----|------|---------|
| `/` | `index.php` | 3-second splash; bots 301 to `/index1` |
| `/index1` | `index1.php` | Home: greeting, live rates, CTAs, favourite pieces, FAQ |
| `/gallery` | `gallery.php` | Shop grid: metal filter, sort, search, price range, pagination (12/page), AJAX |
| `/product?id=` | `product.php` | Detail: gallery, price breakdown, specs, reviews, related, add to cart / buy now |
| `/Catalogue` | `Catalogue.php` | Custom design photo catalogue (lazy load) |
| `/cart` | `cart.php` | Cart from `localStorage` |
| `/checkout` | `checkout.php` | Name, phone, address, pincode, email, payment method |
| `/buynow?id=` | `buynow.php` | Single-item checkout |
| `/pay` | `pay.php` | Razorpay checkout for reserved order |
| `/success` | `success.php` | Post-order confirmation; clears cart |
| `/wishlist` | `wishlist.php` | Heart list from `localStorage` (feature flag) |
| `/account` | `account.php` | Optional login/register + order history |
| `/about` | `about.php` | Store story |
| `/contact` | `contact.php` | Address, map, WhatsApp, phone |
| `/blog` | `blog.php` | 4 hardcoded posts (not a DB) |
| `/blog-post?slug=` | `blog-post.php` | Static post body |
| `/follow-us` | `follow-us.php` | Instagram / Google review / QR |
| `/gold-rate-khedbrahma` | `gold-rate-khedbrahma.php` | SEO rates page |
| `/jewellers-in-khedbrahma` | `jewellers-in-khedbrahma.php` | SEO local page |
| `/welcome` | `welcome.php` | PWA first-run |
| `/secret` | `secret.php` | Admin login gate (noindex) |

**JSON / utility endpoints (customer or shared):**

| URL | File | Purpose |
|-----|------|---------|
| `POST /place_order` | `place_order.php` | Create reserved order (CSRF) |
| `GET /get_cart_items` | `get_cart_items.php` | Hydrate cart IDs with live prices |
| `POST /review-submit` | `review-submit.php` | Product review |
| `POST /verify-payment` | `verify-payment.php` | Razorpay signature verify |
| `POST /webhook-razorpay` | `webhook-razorpay.php` | Razorpay webhook |
| `GET /img.php?f=&w=` | `img.php` | Resize/cache uploads or catalogue images |
| `GET /sitemap.xml` | `sitemap.php` | SEO sitemap |
| `GET /robots.txt` | `robots.php` | Robots |
| `GET /manifest.webmanifest` | `manifest.php` | PWA manifest |
| `POST /api/track` | `api/track.php` | Visitor analytics |
| `POST /api/abandon-cart` | `api/abandon-cart.php` | Abandoned cart ping |
| `POST /api/push-subscribe` | `api/push-subscribe.php` | Web push subscribe |
| `GET /api/push-latest` | `api/push-latest.php` | Latest push payload |
| `GET /account-auth` | `account-auth.php` | Account modal AJAX |

---

## 4. Existing features (inventory)

Present in PHP (migrate these). Absent features are marked so they are **not invented**.

### 4.1 Present

- Splash → home
- Live gold / silver / 925 / copper rates (per-gram except copper per kg)
- Rate formula (default gold: `board × 0.9166 ÷ 1.03`) stored in `site_settings.rate_formula_json`
- Standard vs **manual rates** per item (`use_manual_rates`)
- Making charges %; optional apply-to-silver
- Cached price on `jewellery_items.cached_price`
- Shop filters: metal (`all|gold|silver|silver_925`), sort (`newest|price_asc|price_desc|name_asc`), min/max price
- Synonym / intent search (`includes/gallery-search.php`)
- Pagination + AJAX infinite-style load (`?ajax=1`)
- Product images from `/uploads/` via `img.php`
- Extra gallery images (`jewellery_item_images`)
- Sold-out / reserved overlay
- Favourites on home (`is_favourite`)
- “Special Rate” badge when `manual_price` is set and lower than computed price (not a coupon system)
- Tap-to-zoom, specs table, related pieces, recommendations (feature flags)
- Reviews with admin approval
- Compare / select-and-share (`js/select-compare-share.js`)
- Cart + wishlist in `localStorage`
- Abandoned-cart banner + `abandoned_carts` table
- Optional customer accounts (10-digit phone, password ≥ 6, PHP `password_hash`)
- Guest checkout always allowed
- 24-hour stock reservation (`SELECT … FOR UPDATE`)
- Payment methods: **WhatsApp**, **Razorpay** (if keys + flag), **cash** (normalized in PHP; not COD label)
- GST breakup (off by default; prices treated GST-inclusive when enabled)
- Order history on account (flag)
- Email invoices (off; needs SMTP)
- Light / dark / system theme
- Sticky header + mobile bottom nav (Home, Shop, Designs, Contact, Cart)
- PWA (manifest, `sw.js`, install prompt)
- Web push (VAPID) + in-app alerts
- Visitor analytics + optional geolocation
- SEO: GTM, schema.org LocalBusiness, FAQ, breadcrumbs, IndexNow, sitemap
- WhatsApp order deep links (`919427080359`)

### 4.2 Not present (do not add unless product later requires them)

- Coupon / promo codes
- Traditional SKU variants (size/color matrix)
- Multi-qty for unique jewellery pieces (line qty exists on orders but shop is one-of-a-kind reservation)
- Customer saved-address book (checkout fields only)
- Multi-currency
- CMS blog in the database (blog is a PHP array of 4 posts)

---

## 5. Existing database schema (MySQL / MariaDB)

Dump file: `if0_38635607_cjdb.sql` (generated 15 Aug 2026 from InfinityFree).

**Engine mix:** newer tables are InnoDB/utf8mb4; older catalogue tables (`jewellery_items`, `products`, `rates`, `manual_rates`, `payments`) are **MyISAM / latin1**. Migration to PostgreSQL **must** convert charset and engines. Several columns used by current PHP (`copper_rate`, `tags`) are added at runtime via `cj_ensure_*_schema()` and may be missing from the dump.

### 5.1 Tables discovered

| Table | Role |
|-------|------|
| `jewellery_items` | In-stock shop pieces |
| `jewellery_item_images` | Extra photos |
| `products` | Custom design catalogue images |
| `product_reviews` | Reviews (approval flag) |
| `rates` | Latest board rates |
| `manual_rates` | Operator override rates |
| `customers` | Optional shoppers (unique phone) |
| `orders` | Reserved / confirmed / cancelled / expired / refunded |
| `order_items` | Line items |
| `payments` | Legacy payment rows (MyISAM; parallel to Razorpay fields on `orders`) |
| `abandoned_carts` | Session cart tracker |
| `admin_users` | Staff (`admin`, `superadmin`; user `deven` is role `admin`) |
| `admin_remember_tokens` | Persistent admin login |
| `admin_login_attempts` | Login rate limit |
| `admin_activity_log` | Audit log |
| `site_settings` | Feature flags + integrations |
| `internal_messages` | Superadmin messaging |
| `push_subscriptions`, `push_templates`, `push_queue`, `push_logs` | PWA push |
| `visitor_sessions`, `visitor_pageviews` | Analytics |
| `cron_meta` | Cron watermarks |

### 5.2 Core column notes

**`jewellery_items` (dump):** `id`, `name`, `image`, `description`, `purity`, `stone`, `category`, `sku`, `dimensions`, `weight DECIMAL(10,2)`, `cached_price`, `metal_type ENUM('gold','silver','silver_925')`, `manual_price`, `created_at`, `is_favourite`, `is_sold_out`, `reserved_order_id`, `reserved_until`, `use_manual_rates`. PHP also expects `tags` and `copper` metal in newer code.

**`orders`:** `order_no`, customer name/phone/address/pincode/email, optional `customer_id`, `status`, `source`, money fields, Razorpay IDs, `reserved_until`, timestamps.

**`admin_users`:** bcrypt `$2y$10$` hashes (PHP `PASSWORD_DEFAULT`). Three staff rows exist in the dump. **Do not copy hashes into git seeds.**

**`customers`:** one row in the dump. Same bcrypt family. PHP hashes **can** be verified in Node by normalising `$2y$` → `$2a$`/`$2b$` before `bcrypt.compare`.

### 5.3 Relationships (logical; dump has limited FKs)

```
customers 1—N orders
orders 1—N order_items
jewellery_items 1—N jewellery_item_images
jewellery_items 1—N product_reviews
jewellery_items 1—N order_items (product_id)
orders 1—N jewellery_items via reserved_order_id
admin_users 1—N admin_remember_tokens, admin_activity_log
```

No stored procedures were found. Schema is evolved in PHP with `CREATE TABLE IF NOT EXISTS` + `ALTER TABLE` helpers.

### 5.4 Data volumes (dump snapshot)

- Shop items: ~70 jewellery rows (IDs not contiguous; some duplicates such as gold butti 88/89).
- Catalogue `products`: hundreds of design photos (IDs from 161+).
- Orders / order_items: schema present; dump section had no order INSERTs in the inspected range (treat live DB as possibly newer than dump).
- Rates example in dump: gold 15100 / gram, silver 237, 925 650, making 28%, apply making to silver on.

**Do not run destructive SQL against InfinityFree production.** Import a copy locally, then ETL into PostgreSQL.

---

## 6. Existing API and backend logic

PHP is mostly **server-rendered forms**, not a public REST API. JSON exists only where AJAX needs it.

### 6.1 Pricing (must be bit-identical)

From `includes/pricing.php`:

1. Pick board rate for metal (`gold_rate`, `silver_rate`, `silver_925_rate`, `copper_rate`).
2. Apply operator chain from `rate_formula_json` (`+ - * /`).
3. `metal_value = counted_rate_per_gram * weight_grams` (copper is per kg in UI; confirm weight unit when implementing copper SKUs).
4. Add making % for gold, and for silver if `apply_making_to_silver`.
5. `manual_price` can override display; “Special Rate” if lower.

Frontend JS previously divided by 10 (per-10g). PHP comments state that is **wrong** for this store. Flutter `JewelleryPriceEngine` must follow PHP, not the old JS bug.

### 6.2 Checkout / reservation

`cj_place_order` in `includes/orders.php`:

- Expire stale reservations first.
- Lock items with `FOR UPDATE`.
- Reject sold-out / reserved pieces.
- Insert `orders` + `order_items`, set `reserved_until = NOW() + 24 hours`, mark items sold-out/reserved.
- WhatsApp path returns a `wa.me` URL; Razorpay path creates/reuses a Razorpay order.

### 6.3 Auth

- **Customers:** PHP session keys `cj_customer_id/name/phone/email`. Phone normalised to 10 digits.
- **Admins:** `secret.php` or `admin/index.php?secret=…` gate, then username+password (or legacy password-only as user `admin`). Remember-me: selector:validator cookie, SHA-256 hashed validator in DB, ~30 days. Login rate limiting. CSRF on POSTs. Superadmin hitting store admin without `embed=1` is redirected to `superadmin.php?tab=store`.

---

## 7. Admin panel

### 7.1 Entry

- Public URL `/secret` (styled “Control Gate”).
- Hidden query gate on `admin/index.php?secret=…` (fallback string in PHP if `config/admin-secret.php` missing — **rotate this**; do not commit the live secret).
- Failed gate returns fake **404**.

### 7.2 Store admin (`admin/index.php`) — roles `admin`, `superadmin`

- Login + remember me
- Update **standard** and **manual** rates + show/hide rate metals
- Rate formula builder
- CRUD jewellery items (image compress/upload AJAX, extra images, favourite, sold-out, manual rates, AI description helper)
- Catalogue product uploads (`products` table)
- Visitor map (Leaflet)
- Links to orders / notifications / manual rate pages

Related files: `admin/edit.php`, `admin/delete_c.php`, `admin/orders.php`, `admin/notifications.php`, `admin/manual_rate.php`, `admin/manual_rate_update.php`.

**Orders admin:** filter by status; confirm (keep sold); cancel (release stock); refund (Razorpay + flags `feat_refunds` / `perm_admin_refunds`).

### 7.3 Super Admin (`admin/superadmin.php`) — role `superadmin` only

Tabs: `overview`, `store` (embeds store admin), `integrations`, `controls`, `data`, `notifications`, `activity`.

- Integrations: WhatsApp, phone, email, Instagram, Google review, GTM/GA4, search console tokens, CDN base URL, SMTP, Razorpay keys, review auto-approve
- Site controls: all `feat_*` flags, GST %, recs limit
- Data browsers: products, catalogue, visitors, push, messages
- Push broadcast + internal messages
- Activity log

### 7.4 Staff users (from dump — names only)

- `admin` — Hasmukh Hiralal Soni (store admin)
- `superadmin` — Soni Jaykumar Hasmukh
- `deven` — Deven Hasmukhbhai Soni (DB role `admin`; Flutter `admin_app` currently treats username `deven` as a separate dashboard)

---

## 8. UI styles and assets (source of truth)

### 8.1 Design tokens (`css/style.css`)

Light:

- Gold `#d4af37` / dark gold `#b8972c` / light gold `#f3e5ab`
- Background `#f9fafb`, card `#ffffff`, text `#111827` / `#4b5563` / `#9ca3af`
- Border `#e5e7eb`, header white
- Fonts: **Poppins** (body), **Playfair Display** (brand / titles)
- Header min-height 52px, logo 36×36, max content width 1200px

Dark (`html[data-theme="dark"]`):

- Background `#0c0e12`, card `#161a22`, header `#12151c`, gold `#f0d78c`

Admin uses Tailwind CDN + `css/admin.css` (gold/cream glass cards), not the customer stylesheet.

### 8.2 JS interactions

`js/main.js` (cart/wishlist), `js/gallery-shop.js`, `js/pricing.js`, `js/theme.js`, `js/pwa.js`, `js/lazy.js`, `js/catalogue-lazy.js`, `js/visitor-analytics.js`, `js/select-compare-share.js`.

### 8.3 Assets

- Logo referenced as `images/logo.png` (local `images/` currently only has `favicon.svg` in this workspace).
- Product files: `uploads/` (shop) and `catalogue/` (designs). **Local copies are missing or gitignored**; production serves `https://chandrakalajewellers.in/uploads/` and `/catalogue/`.
- Flutter already points CDN at that production host (`AppConstants.cdnBaseUrl`).
- `img.php` resizes to WebP/JPEG cache under `uploads/cache/`.

**Do not replace these with stock photos.**

---

## 9. Existing dependencies

### PHP / production

- PHP mysqli, GD (`img.php`), curl (Razorpay)
- No Composer packages
- CDN: Google Fonts, Tailwind CDN (admin + some pages), Font Awesome / cdnjs, Razorpay Checkout, GTM, Leaflet (admin map), unpkg

### Third parties

| Service | Use | Config |
|---------|-----|--------|
| Razorpay | Online pay, refunds, webhook | Superadmin integrations / `CJ_RAZORPAY_*` |
| WhatsApp | Order messages | `whatsapp_number` default `919427080359` |
| Google Tag Manager | Analytics | `GTM-TFC4WZRJ` |
| SMTP | Invoices (optional) | Superadmin |
| Web Push (VAPID) | PWA notifications | `config/vapid.php` |
| Nominatim / Maps | Visitor geo + store directions | |
| Gemini (optional) | Admin AI descriptions | `gemini_api_key` setting |
| InfinityFree MySQL | Production DB | `config/.env` (never commit) |

### Already-started Node / Flutter

- Backend: `pg`, `bcryptjs`, `jsonwebtoken`, `@vercel/node`, TypeScript
- Frontend: Flutter 3, `flutter_bloc`, `dio`, `go_router`, `google_fonts`, `cached_network_image`, `photo_view`, `shimmer`, `shared_preferences`, `url_launcher`

---

## 10. Already-started Flutter + Node work (gap analysis)

### 10.1 What exists

Customer Flutter routes in `frontend/lib/app.dart`: `/`, `/home`, `/index1`, `/gallery`, `/shop`, `/catalogue`, `/product/:id`, `/cart`, `/checkout`, `/buynow`, `/wishlist`, `/blog`, `/about`, `/contact`, `/follow-us`, `/account`, SEO pages.

Admin Flutter is a **separate app** (`admin_app/`), not `/admin` inside the customer app.

Backend serverless endpoints include inventory, catalogue, rates, customer auth, order reserve/verify, cart items, reviews, blog, contact, and a set of `/api/admin/*` routes.

Deployed URLs referenced in READMEs:

- Frontend: `https://chandrakala-jewellers.vercel.app`
- Backend: `https://cj-backend-kappa.vercel.app`

### 10.2 Gaps vs PHP source of truth

| Area | Gap |
|------|-----|
| UI fidelity | Flutter approximates PHP (gold tokens, scaffold, home greeting) but is **not** a pixel-complete port of `css/style.css`. Material widgets, 24px settings sheet radius, serif fallback vs Playfair, etc. |
| Account | Flutter account view saves name/phone/address in `SharedPreferences` only — **does not call PHP-style login/register or show order history** |
| Cart | Flutter has a CartBloc; PHP cart is ID array in `localStorage` hydrated by `get_cart_items.php` |
| Dummy data | `backend/api/inventory/index.ts` ships **enriched seed copy** (invented purity/stone/category/SKU) when DB is empty — violates “no dummy data” |
| Schema | Postgres schema misses push, visitors, remember tokens, login attempts, internal messages, cron_meta, legacy `payments` |
| Auth secret | `JWT_SECRET` falls back to a **hardcoded string** in `backend/api/auth/login.ts` |
| Admin routing | Spec asked `/admin` in the customer Flutter app; current code uses a second Flutter project |
| Express | Spec asked Express; current backend is Vercel **serverless functions** (practical for Vercel, but not the requested Express layout) |
| Images | Local Flutter `assets/images/` only has favicon; relies on production CDN |
| PHP images | `uploads/` / `catalogue/` not present locally — ETL and CDN strategy required |
| Feature flags | PHP `site_settings` flags are not fully wired in Flutter |
| PWA push | PHP service worker + VAPID not replicated in Flutter Web |
| SEO | PHP server-rendered meta/schema; Flutter Web SPA will lose crawler HTML unless extra prerender/SSR strategy is added |

---

## 11. Target architecture (adapted — do not move PHP)

Requested tree used `legacy-php/`. **Moving the live PHP tree would break XAMPP and InfinityFree deploy paths.** Adapted layout:

```
CJ/
├── [existing PHP site — preserved at root]
├── frontend/          # Flutter Web customer shop (Vercel project 1)
├── admin_app/         # Flutter Web admin (Vercel project 2 — confirmed separate)
├── backend/           # Node serverless APIs (Vercel project 3)
├── database/
│   ├── schema.sql
│   ├── migrations/
│   └── seeds/
├── docs/
├── MIGRATION_ANALYSIS.md
├── MIGRATION_CHECKLIST.md
└── README.md
```

### 11.1 Backend (confirmed)

Keep the current **Vercel serverless TypeScript** APIs in `backend/` as the preview/production API for the new stack. PHP remains the live shop until cutover. Express can wait; Vercel compatibility is the constraint.

### 11.2 Admin (confirmed)

Admin is a **second Vercel project** (`admin_app/`). It is not merged into the customer Flutter app and is not linked from the public header.

### 11.3 Cutover (confirmed)

See [docs/CUTOVER.md](docs/CUTOVER.md).

- `chandrakalajewellers.in` stays on PHP until the Flutter + Node + Postgres stack is **100% migrated and verified**.
- A new MySQL dump of `if0_38635607_cjdb.sql` will be supplied before real data is loaded into Postgres.
- Product images keep using `https://chandrakalajewellers.in/uploads/` during the parallel run.


---

## 12. Migration risks

1. **Visual drift** — Flutter will not match PHP unless each page is compared against `css/style.css` and live HTML.
2. **Price mismatch** — formula, making charges, GST inclusive flag, manual rates, cached_price refresh.
3. **Oversell** — reservation must stay transactional (`FOR UPDATE`) across serverless instances.
4. **Charset** — latin1 MyISAM names (e.g. mangalsutra) can corrupt if imported naively.
5. **Images** — filenames in DB without files in git; production CDN must stay available during cutover.
6. **Password hashes** — PHP `$2y$` bcrypt; migrate hashes, do not re-hash with a dummy password.
7. **JWT hardcoded fallback** — must require env in production.
8. **SEO regression** — SPA vs PHP HTML; Google currently indexes `index1`, gallery, blog, local landing pages.
9. **PWA / push** — customers may already have the PHP app installed.
10. **Razorpay webhooks** — URL change needs dashboard update; keep PHP webhook until cutover.
11. **Admin secret URL** — changing `/secret` without a redirect locks staff out.
12. **Dump vs live DB** — dump is dated 15 Aug 2026; live InfinityFree data may have newer products/orders.
13. **copper_rate / tags** — PHP mutates schema at runtime; dump may lag.
14. **Dual write** — running PHP and Node against different databases will diverge stock.

---

## 13. Confirmed decisions (18 Sep 2026)

1. **Cutover:** PHP stays live on `chandrakalajewellers.in` until the Vercel stack is complete. Domain is pointed only after 100% migration.
2. **Admin:** `admin_app/` is a **second Vercel project**.
3. **Database:** A new dump of `if0_38635607_cjdb.sql` will be provided; do not treat the current dump as final.
4. **Images:** Keep serving from the PHP host during the parallel run.
5. **Backend:** Vercel serverless Node APIs (current `backend/`).

Still open: Postgres host credentials, Razorpay live vs test keys on the Node project, Deven dashboard vs PHP `admin` role.

---

## 14. Critical blockers (must resolve before calling migration complete)

| ID | Blocker | Impact |
|----|---------|--------|
| B1 | Local `uploads/` and `catalogue/` images missing | Product grids cannot be verified offline |
| B2 | Production MySQL must not be dropped or overwritten | Data loss |
| B3 | Postgres not confirmed connected in this workspace | APIs fall back to seed/dummy items |
| B4 | Exact UI not verified in a browser against PHP | Spec requirement unmet |
| B5 | Hardcoded `JWT_SECRET` fallback | Security |
| B6 | Flutter account was local-only | IN PROGRESS — now calls `/api/auth/*` + `/api/orders/mine` |
| B7 | Schema incomplete vs PHP | Push, analytics, remember-me, messages dropped |
| B8 | Vercel Express layout not implemented | Spec mismatch (documented, not fatal if A is accepted) |

---

## 15. Controlled implementation plan (from this codebase)

PHP remains the live site until a documented cutover.

1. **Phase 1 — Inspect** (this document).  
2. **Phase 2 — Scaffold docs + Postgres parity** without moving PHP: `docs/`, `database/migrations/`, `.env.example`, root `README.md`.  
3. **Phase 3 — Harden backend:** env-only secrets, parameterized SQL, PHP-compatible pricing, real inventory (no invented SKU copy), order reserve matching `cj_place_order`.  
4. **Phase 4 — Flutter UI pass:** restyle widgets to `css/style.css` tokens/spacing; wire APIs; compare each PHP page.  
5. **Phase 5 — Admin:** separate Vercel project (`admin_app`); JWT on all admin APIs. PHP `/secret` stays live.  
6. **Phase 6 — Data:** wait for the new MySQL dump, then ETL into Postgres.  
7. **Phase 7 — Payments:** Razorpay on Node only after preview is verified; PHP webhook stays until DNS cutover.  
8. **Phase 8 — Deploy:** three Vercel projects; never deploy the repo root.  
9. **Phase 9 — Validate + DNS:** only then point `chandrakalajewellers.in` at the frontend Vercel project.

---

## 16. Security notes (do not publish secrets)

- `config/.env` exists locally and is gitignored via `config/.gitignore` — never commit it.
- SQL dump contains bcrypt hashes and activity IPs — treat the dump as **sensitive**; do not paste hashes into seeds.
- Rotate InfinityFree DB password if it was ever leaked (already noted in `config/.env.example`).
- Remove hardcoded JWT fallback before any production Node deploy.
- **Removed this session:** plaintext staff passwords and JWT fallback strings that were committed in `backend/src/utils/admin-auth.ts` and `backend/src/db/migrate.ts`. Admin login now requires `admin_users` in Postgres (imported PHP hashes) or a one-time `ADMIN_BOOTSTRAP_*` env pair. Rotate any password that was previously stored in source.
- Keep admin 404-gate behaviour so `/admin` is not a public landing page.

---

*End of Phase 1 analysis. Next: documentation + database parity files only; PHP untouched.*
