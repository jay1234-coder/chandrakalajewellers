# ChandraKala Jewellers — Migration Checklist

Track status against the **PHP application**, not against invented features.  
Legend: `TODO` · `IN PROGRESS` · `DONE` · `N/A` (not in PHP) · `BLOCKED`

Source: `MIGRATION_ANALYSIS.md` (18 Sep 2026).

---

## A. Pages (customer)

| PHP route | Flutter route | UI match | API wired | Notes | Status |
|-----------|---------------|----------|-----------|-------|--------|
| `/` splash | `/` | Partial | N/A | Splash exists; not compared in browser this phase | IN PROGRESS |
| `/index1` home | `/home`, `/index1` | Partial | Rates + gallery | Greeting/rates exist; CSS fidelity unverified | IN PROGRESS |
| `/gallery` | `/gallery`, `/shop` | Partial | Inventory list | Filters/search/sort must match PHP | IN PROGRESS |
| `/product?id=` | `/product/:id` | Partial | Inventory by id | Extra images, reviews, related, flags | IN PROGRESS |
| `/Catalogue` | `/catalogue` | Partial | Catalogue API | Lazy grid vs PHP | IN PROGRESS |
| `/cart` | `/cart` | Partial | Local + hydrate | PHP uses localStorage IDs | IN PROGRESS |
| `/checkout` | `/checkout` | Partial | Reserve API | CSRF → JWT/session equivalent | IN PROGRESS |
| `/buynow` | `/buynow` | Partial | Reserve | | IN PROGRESS |
| `/pay` | (checkout) | TODO | Razorpay | Dedicated pay page in PHP | TODO |
| `/success` | TODO | TODO | | Clear cart | TODO |
| `/wishlist` | `/wishlist` | Partial | local | Feature flag | IN PROGRESS |
| `/account` | `/account` | Partial | Yes | Login/register + order history via API | IN PROGRESS |
| `/about` | `/about` | Partial | Static | | IN PROGRESS |
| `/contact` | `/contact` | Partial | Inquiry API | Map + WhatsApp | IN PROGRESS |
| `/blog` | `/blog` | Partial | Hardcoded/API | PHP is 4 static posts | IN PROGRESS |
| `/blog-post` | `/blog/:slug` | Partial | | | IN PROGRESS |
| `/follow-us` | `/follow-us` | Partial | Static | QR/PDF in PHP | IN PROGRESS |
| `/gold-rate-khedbrahma` | `/gold-rate-khedbrahma` | Partial | Rates | SEO page | IN PROGRESS |
| `/jewellers-in-khedbrahma` | `/jewellers-in-khedbrahma` | Partial | Static | SEO page | IN PROGRESS |
| `/welcome` | TODO | TODO | | PWA welcome | TODO |
| `/secret` | admin_app login | Partial | Admin login | Separate app today | IN PROGRESS |

## B. Admin

| PHP | Target | Status |
|-----|--------|--------|
| Secret gate + fake 404 | `/secret` or hidden admin | IN PROGRESS |
| Store admin rates + inventory CRUD | `/admin` | IN PROGRESS (`admin_app`) |
| Orders confirm/cancel/refund | `/admin/orders` | IN PROGRESS |
| Notifications / push | `/admin` notifications | IN PROGRESS |
| Superadmin tabs | role-gated dashboard | IN PROGRESS |
| Feature flags / integrations | settings | TODO |
| Activity log | | TODO |
| Visitor analytics | | TODO |
| Remember-me admin | JWT 30d (not cookie selector) | IN PROGRESS |
| Deven as store admin | Flutter special dashboard | Clarify vs PHP |

## C. Features

| Feature | In PHP? | Migrated? |
|---------|---------|-----------|
| Live rates | Yes | IN PROGRESS |
| Rate formula | Yes | TODO (must copy PHP ops) |
| Manual rates per item | Yes | IN PROGRESS |
| Shop search + synonyms | Yes | TODO |
| Filters + sort + pagination | Yes | IN PROGRESS |
| Extra product images | Yes | TODO |
| Reviews + moderation | Yes | IN PROGRESS |
| Related / recs | Yes | TODO |
| Compare | Yes | TODO |
| Wishlist | Yes | IN PROGRESS |
| Optional customer auth | Yes | IN PROGRESS |
| Order history | Yes | IN PROGRESS |
| 24h reservation | Yes | IN PROGRESS |
| WhatsApp checkout | Yes | IN PROGRESS |
| Razorpay | Yes | IN PROGRESS |
| Cash / pay later | Yes | TODO (`cash` vs `cod` naming) |
| GST (off by default) | Yes | TODO |
| Coupons | **No** | N/A |
| Product variant matrix | **No** | N/A |
| PWA + web push | Yes | TODO |
| Theme light/dark/system | Yes | IN PROGRESS |
| Abandoned cart banner | Yes | TODO |
| Email invoices | Yes (off) | TODO |

## D. APIs

| Capability | PHP | Node | Status |
|------------|-----|------|--------|
| Rates latest | SQL on page | `GET /api/rates` | IN PROGRESS |
| Inventory list/detail | SQL on page | `/api/inventory` | IN PROGRESS (seed fallback) |
| Catalogue | SQL | `/api/catalogue` | IN PROGRESS |
| Place order | `POST /place_order` | `POST /api/orders/reserve` | IN PROGRESS |
| Verify payment | `verify-payment.php` | `/api/orders/verify-payment` | IN PROGRESS |
| Customer login/register | session forms | `/api/auth/*` | IN PROGRESS (unused by Flutter account) |
| Cart hydrate | `get_cart_items.php` | `/api/cart/items` | IN PROGRESS |
| Reviews | `review-submit.php` | `/api/reviews` | IN PROGRESS |
| Admin CRUD | `admin/index.php` POST | `/api/admin/*` | IN PROGRESS |
| Push subscribe | `/api/push-subscribe` | missing | TODO |
| Visitor track | `/api/track` | missing | TODO |
| Image resize | `img.php` | CDN original files | TODO |

## E. Database

| Task | Status |
|------|--------|
| Document MySQL tables | DONE |
| Postgres core schema | DONE (partial) |
| Postgres parity (push, visitors, tokens, messages) | IN PROGRESS (`database/migrations/`) |
| ETL from dump without destroying production | TODO (needs approval) |
| Import real jewellery rows (no renamed copy) | TODO |
| Migrate bcrypt hashes | TODO |
| Image files to storage | BLOCKED (files not in workspace) |

## F. Security / deploy

| Task | Status |
|------|--------|
| No `.env` in git | DONE (gitignored) |
| `.env.example` backend | IN PROGRESS |
| JWT from env only | DONE (this session) |
| Parameterized SQL | IN PROGRESS |
| Admin auth on admin APIs | IN PROGRESS |
| CORS not `*` in production | IN PROGRESS |
| Flutter `API_URL` dart-define | Partial |
| SPA rewrites | DONE (`frontend/vercel.json`) |
| Browser visual QA vs PHP | TODO |
| Do not claim Vercel success without verify | OPEN |

## G. Phase 1 deliverables

| Item | Status |
|------|--------|
| Inspect PHP | DONE |
| `MIGRATION_ANALYSIS.md` | DONE |
| `MIGRATION_CHECKLIST.md` | DONE |
| PHP files preserved | DONE |
| Controlled Phase 2 scaffold | IN PROGRESS |
