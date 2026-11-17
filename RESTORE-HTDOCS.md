# LOCAL HTDOCS WIPED — RESTORE REQUIRED

Your `CJ/htdocs` folder was found **empty** (0 site files) during follow-up after the splash/geo/SEO check.

Only these repair files were recreated locally so you can still upload CSP/PWA fixes:

- `sw.js` (same-origin fetch only; no Google Fonts interception; safe offline Response)
- `manifest.webmanifest` + `site.webmanifest` (valid JSON; icons purpose split)
- `includes/security.php` (`connect-src` allows `fonts.googleapis.com` + `fonts.gstatic.com`)
- `.htaccess` (same CSP + `AddType application/manifest+json .webmanifest`)

## What you must do now

1. **Restore the full PHP site** into `CJ/htdocs` from InfinityFree File Manager / FTP backup (download `htdocs` zip), or from any local backup / USB copy.
2. After restore, **overwrite** the files listed above with these fixed versions (or merge CSP if you already have newer local edits).
3. On the live site, hard-refresh and unregister the old service worker once:
   DevTools → Application → Service Workers → Unregister, then reload.
4. Confirm icons exist at `images/icons/icon-192.png` and `icon-512.png` (manifest 404 HTML = “Syntax error”).

## Console errors these fixes target

- CSP `connect-src` blocking Google Fonts via `sw.js`
- `Failed to convert value to 'Response'` when SW catch returned undefined
- `manifest.webmanifest` Syntax error (often HTML 404 / wrong MIME)
- InfinityFree `errors/404` for missing assets under CSP
