# Cutover policy — PHP stays live until Vercel is 100%

**Confirmed 18 September 2026.**

## Production today

- **Live customer site and live admin:** `https://chandrakalajewellers.in` (PHP + MySQL on InfinityFree).
- Do **not** point the custom domain at Vercel until the new stack is complete and signed off.
- Do **not** stop, overwrite, or “replace” the PHP site during development.

## Parallel run (now → cutover)

| Surface | Host | Role |
|---------|------|------|
| Customer website (live) | `chandrakalajewellers.in` | PHP — production |
| Customer website (preview) | Vercel project **frontend/** | Flutter Web — not public DNS yet |
| API (preview) | Vercel project **backend/** | Node serverless + PostgreSQL |
| Admin (preview) | Vercel project **admin_app/** | Flutter Web — **second Vercel project**, not `/admin` on the shop |
| Product images | `chandrakalajewellers.in/uploads` and `/catalogue` | Keep serving from PHP until files are copied |

Customers continue to shop on PHP. Vercel URLs are for testing only.

## Three Vercel projects (do not deploy the repo root)

1. **frontend** — Root Directory `frontend`. Flutter Web shop. Env: `API_URL`, `CDN_BASE_URL`.
2. **backend** — Root Directory `backend`. Env: `POSTGRES_URL`, `JWT_SECRET`, `CORS_ORIGINS`, Razorpay keys.
3. **admin_app** — Root Directory `admin_app`. Flutter Web admin. Env: `API_URL`.

The file `vercel.json` at the **repository root must not** be used to ship the whole folder as one site.

`CORS_ORIGINS` should list the frontend and admin Vercel hostnames plus `http://localhost:8080` for local Flutter.

## Database

- Production data stays in InfinityFree MySQL (`if0_38635607_cjdb`) until cutover.
- A **new dump** of `if0_38635607_cjdb.sql` will be provided before Postgres is filled with real stock.
- Do not treat the older dump in this repo as the final catalogue.
- Do not invent jewellery names, reviews, or SKUs while waiting for that dump.
- Import steps: [DATABASE_MIGRATION.md](./DATABASE_MIGRATION.md).

## When (and only when) migration is 100%

1. Checklist in `MIGRATION_CHECKLIST.md` is green for pages, admin, payments, pricing, images.
2. Fresh MySQL dump imported into Postgres and counts match.
3. Frontend, backend, and admin Vercel projects are verified on their `*.vercel.app` URLs.
4. Then — and only then — point `chandrakalajewellers.in` (and www) at the **frontend** Vercel project.
5. Keep PHP reachable as a rollback until the first days of DNS cutover look stable.
6. Update Razorpay webhook from PHP `webhook-razorpay.php` to the Node webhook after DNS, not before.

Until that day, PHP is the shop the public uses.
