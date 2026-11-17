# ChandraKala PWA + Push + Super Admin

## What was added
- Progressive Web App (installable app): `manifest.webmanifest`, `sw.js`, icons, install popup after ~45s
- Web Push notification manager: `notifications.php`
- Auto notifies customers when rates update / product added / product updated
- Daily cron reminders for admin (update rates) + customers (open app)
- Super Admin control center: `superadmin.php`, `superadmin-data.php`

## Default logins (3 family accounts)
| Portal | URL | Username | Role |
|--------|-----|----------|------|
| Store Admin | `/admin` | `admin` | admin (Hasmukh Hiralal Soni) |
| Store Admin (Co-Admin) | `/admin` | `deven` | admin (Deven Hasmukhbhai Soni) — same permissions; separate activity logs |
| Super Admin | `/superadmin` | `superadmin` | superadmin (Soni Jaykumar Hasmukh) |

Passwords are seeded/hashed in `includes/pwa-schema.php`. Existing admin/superadmin passwords are not reset by the family-accounts migration. Co-Admin password is set once when `deven` is first inserted.

Password-only login on `/admin` (no username) still maps to the `admin` account.

## SQL
Run (or let PHP auto-create on first open of admin/notifications/superadmin):

- `sql/pwa_push_superadmin.sql`
- Also related: `sql/visitor_analytics.sql`, `sql/add_is_sold_out.sql`

## Cron (required for daily pushes)
Call once daily (hosting cron):

```
https://YOUR-DOMAIN/cron/daily-push.php?key=cj-change-this-cron-secret
```

Change secret in `config/cron-secret.php`.

## VAPID keys
Auto-generated into `config/vapid.php` when OpenSSL works on the server.
If empty, open `notifications.php` once on live hosting, or run:

```
php tools/generate-vapid.php
```

## HTTPS
PWA install + push require HTTPS (InfinityFree already provides this).

## Deploy checklist
- `manifest.webmanifest`, `sw.js`
- `images/icons/icon-192.png`, `icon-512.png`
- `js/pwa.js`
- `api/push-subscribe.php`, `api/push-latest.php`
- `cron/daily-push.php`, `config/cron-secret.php`
- `includes/pwa-schema.php`, `vapid.php`, `push.php`, `auth.php`
- `notifications.php`, `superadmin.php`, `superadmin-data.php`
- Updated: `admin.php`, `includes/footer.php`, `includes/seo-head.php`, `css/style.css`
- `sql/pwa_push_superadmin.sql`
