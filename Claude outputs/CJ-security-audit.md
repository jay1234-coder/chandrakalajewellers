# ChandraKala Jewellers (CJ): Security Audit

**Scope:** the whole `C:\xampp\htdocs\CJ` folder: the PHP site (Apache/XAMPP plus the Vercel PHP runtime), the `backend/` Node API, the `admin_app/` and `frontend/` Flutter apps, the Next.js showroom, and config/deploy files.
**Source ref:** `94bc27f` (main), with uncommitted Next.js/Tailwind changes in the working tree.
**Method:** I read the source myself, tracing each data and access path. I did not touch the live site or any deployed endpoint. Nothing in this report was tested against production.
**Note on secrets:** I have not copied any password, key or token into this report. It gives only the file and line where each one is.

---

## Summary: fix these first

| # | Severity | Where it leaks | Fix in one line |
|---|----------|----------------|-----------------|
| 1 | **Critical** | Real admin, superadmin and co-admin passwords are hard-coded in plaintext in git, and also built into the Flutter admin web app | Change every staff password now and remove them from code |
| 2 | **High** | Anyone can open `/success?ref=CKJ…` and see a customer's name, phone, address and items. Order numbers can be guessed | Use long random order tokens and require ownership to view |
| 3 | **High** | Node backend `/api/orders/verify-payment` can mark *any* order as paid using *any* valid Razorpay payment | Tie the payment to that order's Razorpay ID and amount |
| 4 | Medium | Admin "signed cookie" logins can't be revoked. Deleted staff and changed passwords keep working for up to 7 days | Check the user against the DB on restore and add a token version |
| 5 | Medium | A second hidden admin login in `manual_rate_update.php` has its own password and no rate limit | Delete it and use the normal staff login |
| 6 | Medium | Registering with someone else's phone number shows you their past orders and addresses | Verify phone ownership (OTP) before linking guest orders |
| 7 | Medium | No brute-force protection on customer login (PHP and Node) or on the Node admin login | Reuse the existing IP rate limiter |
| 8 | Medium | The Vercel PHP router runs *any* bundled file by path and shows PHP errors publicly | Allowlist routes and turn off `display_errors` |
| 9 | Medium | Web Push VAPID private key committed to git (`config/vapid.php`) | Generate a new key and load it from env |
| 10 | Low | Unauthenticated `/api/admin/db/setup` on the Node backend | Require a superadmin token |
| 11 | Low | Push subscription endpoints are not validated, so the server POSTs to any URL an attacker registers (blind SSRF) | Only allow known push-service hosts |
| 12 | Low | Reel comments are published instantly with no CSRF or rate limit. Reel enquiries can spam admin push | Moderate comments first and rate-limit by IP |
| 13 | Low | The DB TLS certificate isn't verified (`MYSQLI_OPT_SSL_VERIFY_SERVER_CERT=false`) | Turn verification on with the TiDB CA |
| 14 | Low | The cron key falls back to a public default when `config/cron-secret.php` is missing | Fail closed when the secret is missing |

**Things that are already done well:** `.env` files are gitignored and not tracked. SQL uses prepared statements or proper escaping almost everywhere (I found no injectable query). Output is HTML-escaped consistently, and JS uses `textContent`/`escapeHtml`. Admin pages check role and CSRF. Admin login has IP lockout on `REMOTE_ADDR`. Remember-me tokens use selector/validator with hashing and rotation. Uploads are checked by extension and MIME type and are blocked from running scripts. PHP Razorpay verification checks signature, amount and order ID.

---

## 1. Critical: staff passwords hard-coded in source and shipped in the admin web app

**Where**
- `includes/pwa-schema.php` lines **161–162** (seed admin and superadmin passwords as plaintext literals inside `password_hash('…')`) and line **183** (a "one-time" reset of the `admin` password to a hard-coded literal; the comment on the line above also states it in plain text).
- `admin_app/lib/features/auth/bloc/auth_bloc.dart` lines **127, 142, 157**: an "offline fallback" that accepts hard-coded passwords for `superadmin`, `admin` and `deven`.
- `admin_app/lib/features/auth/views/secret_login_view.dart` lines **241–253**: "quick select" chips on the login screen that **fill in** those same passwords.

**Confirmed:** I compared the strings in code (without printing them). All three Flutter passwords match the PHP seed passwords. The bcrypt hash in `admin/manual_rate_update.php` also matches one of them.

**Why it matters**
- Anyone who can see the repo (`github.com/jay1234-coder/chandrakalajewellers`) has the live admin and superadmin passwords for `/secret`, unless they were changed after seeding. I couldn't check whether the repo is public from here. **Please check.**
- `admin_app` is set up to deploy to Vercel (`admin_app/vercel.json`, `build.sh`). Flutter web compiles these strings into `main.dart.js`. So **anyone who opens the admin web app can read the passwords, or just click the chip.** At that point, an unauthenticated visitor gets full superadmin control: orders, customers' PII, prices, staff accounts and payment settings.
- The offline fallback also issues a fake token (`mock_superadmin_token_2026`) and shows a superadmin UI with no server check.

**Fix**
1. **Today:** change the `admin`, `superadmin` and `deven` passwords in the live database (Super Admin → Staff). Also change any other account that used the same passwords anywhere.
2. Delete the plaintext seeds. Seed the first admin from an env var only, as `backend/src/db/migrate.ts` already does with `ADMIN_BOOTSTRAP_*`. Delete the `admin_pw_rev` forced-reset block.
3. Remove the offline fallback and the quick-select chips from `admin_app`.
4. The passwords stay in git history. Rotating them (step 1) is the real fix. Rewriting history is optional.

---

## 2. High: order receipts and pay pages show customer PII to anyone who guesses the order number

**Where:** `success.php:7–21, 93–99` and `pay.php:10–54` load the order using only `?ref=`. `includes/orders.php:366` builds order numbers as `CKJ` + `yymmdd` + **4 hex chars**.

**Why it matters:** there are only 65,536 possible order numbers per day, and the date part is known. Nothing checks who is asking and nothing limits request rate. One script can walk through each day's numbers and collect every order's **customer name, phone, full address, PIN code, items and total**. `pay.php` also lets an outsider create Razorpay orders on someone else's reservation.

**Fix**
- Add an unguessable `view_token` column (e.g. `bin2hex(random_bytes(16))`) and link to `success?ref=<order_no>&t=<token>`. Compare it with `hash_equals`.
- Or show full details only when the session placed the order, or the logged-in customer owns it. Otherwise show only status.
- Also make `order_no` itself longer (at least 8 random bytes).

---

## 3. High: Node backend accepts any Razorpay payment for any order

**Where:** `backend/handlers/orders/verify-payment.ts:10–45`

**What happens:** it checks only that `HMAC(razorpay_order_id|payment_id)` is valid. Then it runs `UPDATE orders SET payment_status='paid' … WHERE id = $4` with the **client-supplied `order_id`**. It never checks that the Razorpay order belongs to that shop order, or that the amount matches.

**Exploit path:** pay ₹100 for a cheap item through any checkout on the same Razorpay account. Keep the three values Razorpay returns to the browser. Post them with the `order_id` of an expensive reserved order, and that order is marked paid and its items sold. The PHP `verify-payment.php` checks all of this correctly. Only the Node copy is missing it.

**Fix:** load the order and require `order.razorpay_order_id === razorpay_order_id`. Fetch the payment from the Razorpay API and check `status`, `amount === grand_total*100` and `order_id`. Use `crypto.timingSafeEqual` for the signature. Reject a payment ID that has already been used.
*(If the Node backend isn't live yet, fix this before it is.)*

---

## 4. Medium: admin sessions restored from the signed cookie can't be revoked

**Where:** `includes/security.php:181` (key) and `:259–289` (`cj_restore_auth_from_cookie`), called on every request by `cj_security_bootstrap()`.

**Why it matters**
- The `cj_admin_jwt` cookie carries `role` and `user_id` and lasts **7 days**. Restoring from it **never checks the database**. So a deleted staff member, a demoted superadmin, or someone whose password you just changed keeps full access until the cookie expires. Logging out only clears the cookie in *that* browser.
- The HMAC key is `sha256('cj_signed_auth_token_key_' . SESSION_SECRET ?? CJ_DB_PASS)`. `SESSION_SECRET` isn't set in `config/.env` and isn't documented. So the key rests on the DB password. If that env var were ever empty on a host, the key would be a public constant and anyone could forge a superadmin cookie. *(Needs checking: confirm `SESSION_SECRET` is set in Vercel and on the PHP host.)*

**Fix:** set a dedicated random `SESSION_SECRET` (32+ bytes) and fail closed if it's missing. Put a `token_version` (or `password_changed_at`) in `admin_users` and in the cookie. On restore, reload the user and check that it exists and the version matches. Take the role from the DB, not from the cookie.

---

## 5. Medium: extra password-only admin login in `admin/manual_rate_update.php`

**Where:** `admin/manual_rate_update.php:14–27`

A bcrypt hash is hard-coded at line 14 and matches one of the shipped passwords from #1. A correct password sets `$_SESSION['loggedin']=true` and role `admin`, which unlocks **the whole admin panel**, not just rates. This path skips `admin_users`, the IP lockout and activity logging. It is reachable at `/admin/manual_rate_update` and `/manual_rate_update`.

**Fix:** delete this login block and call `cj_require_role(['admin','superadmin'])` like the other admin pages.

---

## 6. Medium: account registration can claim someone else's order history

**Where:** `includes/customers.php:223–270` (register, no phone check) and `:326–340` (`cj_customer_orders` matches `customer_id` **OR `customer_phone`**). The Node version is the same: `backend/handlers/orders/mine.ts:27–37`.

Checkout doesn't need an account, so most orders are guest orders keyed only by phone. Anyone can register an account with a victim's phone number (no OTP), open **Account → My orders**, and see the victim's past orders, including name, delivery address and what they bought.

**Fix:** verify phone ownership by OTP before matching orders by phone. Or link guest orders only by `customer_id`, set when the logged-in customer places the order.

---

## 7. Medium: no brute-force limit on customer login or the Node admin login

**Where:** `account-auth.php:46`, `account.php:45` → `cj_customer_login()`. Also `backend/handlers/auth/login.ts` and `backend/handlers/admin/auth/login.ts`.

The minimum password is 6 characters and there's no lockout, so passwords can be guessed online. The admin portal on PHP is protected, but these logins aren't.

**Fix:** call the existing `cj_login_rate_limit_status/hit/clear` with portal `customer`. Add a similar per-IP and per-username limit on the Node routes (Upstash/Redis or a DB table).

---

## 8. Medium: Vercel PHP router runs any bundled file and shows errors publicly

**Where:** `api/index.php:8–33` (`display_errors=1` plus a fatal-error page showing message, file and line) and `:239–246` (`require` of any existing file at the requested path).

- Every file bundled by `includeFiles` (`*.php`, `config/**`, `includes/**`, `admin/**`) can be requested directly. Library files like `/includes/visitor-analytics-admin.php` run without their expected setup and print fatal errors that reveal server paths.
- `require` on a non-PHP file prints its contents. **If `config/.env` ever gets uploaded**, `/config/.env` would print your TiDB and Cloudinary credentials. A `vercel` CLI deploy from your PC would upload it: `.vercelignore` doesn't exclude `.env`, and Vercel's built-in ignore list covers only `.env.local`/`.env.*.local`. The current GitHub Action deploy uses a clean checkout, so it is **probably not exposed today**. *(Needs checking: confirm how you deploy.)*

**Fix:** in the router, dispatch only to an explicit allowlist of page scripts (or reject `config/`, `includes/`, dotfiles and anything that isn't `.php`). Set `display_errors=0` and log instead. Add `.env`, `*.env` and `config/admin-secret.php` to `.vercelignore`.

---

## 9. Medium: Web Push private key committed to git

**Where:** `config/vapid.php` lines 5–6 (raw key and PEM), tracked since `8a3eb17`. `.gitignore` covers `config/admin-secret.php` but not this file.

With this key plus subscription data (stored in your DB), someone could send notifications to your customers that look like they come from your store, for example phishing "payment" links. On its own the key is not enough, but it shouldn't be in git.

**Fix:** generate new keys (`/api/setup-vapid.php?force=1` while logged in, then customers re-subscribe). Load them from env and add `config/vapid.php` to `.gitignore`.

---

## 10–14. Low

- **10. `/api/admin/db/setup` (Node) has no auth** (`backend/handlers/admin/db/setup.ts:24`). Anyone can trigger migrations and read the table list and Postgres version. Add `requireAdmin(req, ['superadmin'])`.
- **11. Push endpoint SSRF.** `api/push-subscribe.php` stores any `endpoint` URL. `includes/push.php:82` later `curl`s it on every broadcast, with a 3 s timeout each, so junk subscriptions also slow broadcasts. Allow only `https://` hosts on known push services (`fcm.googleapis.com`, `*.push.services.mozilla.com`, `*.notify.windows.com`, `*.push.apple.com`) and cap the count per IP.
- **12. Spam on public reel features.** `includes/api/reel-track.php:316–318` inserts comments with `is_approved = 1` with no CSRF or rate limit. The `order` action sends an admin push with attacker-chosen text. Set `is_approved = 0` (or auto-approve only logged-in customers) and add per-IP throttling.
- **13. DB TLS not verified.** `config/db.php:79` turns off server-certificate checks for TiDB. Point `ssl_set` at the TiDB CA and enable verification.
- **14. Cron default secret.** `cron/*.php` fall back to the literal `cj-change-this-cron-secret` if `config/cron-secret.php` is missing. Exit with 503 instead.

**Informational:** the CSP allows `'unsafe-inline' 'unsafe-eval'`, which weakens XSS protection. "Local mode" (`cj_is_local_host`) trusts the `Host` header. Visitor analytics trusts `X-Forwarded-For`. `admin/gemini-describe.php` / `ocr-analyze.php` let admins point the server at any local file path or URL (admin-only, `CURLOPT_SSL_VERIFYPEER=false`).

---

## Checked and not counted as findings
- SQL injection: every dynamic query I traced was prepared, cast with `(int)`, or `real_escape_string`-quoted.
- Stored and reflected XSS in PHP pages and admin views: values are escaped. Reel comments render via `textContent`.
- Inventory hoarding via unpaid reservations: reserving doesn't set `is_sold_out`, so other buyers aren't blocked.
- Upload RCE: extension and MIME allowlist, random filenames, and `php_flag engine off` in `uploads/` and `catalogue/`.
- Secrets in `config/.env`, `backend/.env`, `config/admin-secret.php`, `config/cron-secret.php`: present on disk but **not tracked in git**.

## Coverage and limits
- This was one pass by one reviewer (me). No separate verifier rechecked it, and the skill's JSON schema and validators weren't available in this session, so there's no `findings.json`.
- I covered every PHP entry point, auth/session/CSRF code, payments, uploads, push, the Vercel router, the Node backend handlers, and the auth code in both Flutter apps. I sampled the 1,000+ line page templates (`admin/index.php`, `reels.php`, `showcase.php`, `Catalogue.php`) for injection and escaping, but didn't read every line.
- Still to check yourself: whether the GitHub repo is public, whether `admin_app` and `backend` are deployed, whether `SESSION_SECRET` is set in production, and how you deploy (Git vs CLI).
