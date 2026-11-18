/* ChandraKala PWA — offline pages + persistent image cache */
const PAGE_CACHE = 'cj-pages-v15';
const ASSET_CACHE = 'cj-assets-v15';
const IMAGE_CACHE = 'cj-images-v15';
const ALL_CACHES = [PAGE_CACHE, ASSET_CACHE, IMAGE_CACHE];

const PRECACHE_PAGES = [
  './',
  './index1',
  './gallery',
  './Catalogue.php',
  './about',
  './contact',
  './blog',
  './css/style.css',
  './css/tailwind.min.css',
  './js/main.js',
  './js/pwa.js',
  './js/theme.js',
  './images/icons/icon-192.png',
  './images/icons/icon-512.png',
  './images/logo.png',
  './manifest.webmanifest'
];

function isImageRequest(url) {
  if (/\/img\.php/i.test(url.pathname) || /\/uploads\//i.test(url.pathname)) return true;
  return /\.(jpe?g|png|gif|webp|svg|ico)(\?|$)/i.test(url.pathname);
}

function isAssetRequest(url) {
  return /\.(css|js|webmanifest|woff2?)(\?|$)/i.test(url.pathname)
    || /\/manifest(\.php)?$/i.test(url.pathname);
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(ASSET_CACHE)
      .then(async (cache) => {
        for (const u of PRECACHE_PAGES) {
          try {
            const res = await fetch(u, { credentials: 'same-origin', cache: 'no-cache' });
            if (res && res.ok) await cache.put(u, res);
          } catch (err) {}
        }
      })
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => !ALL_CACHES.includes(k)).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type === 'PRECACHE_URLS' && Array.isArray(data.urls)) {
    event.waitUntil(
      caches.open(IMAGE_CACHE).then(async (cache) => {
        const unique = [...new Set(data.urls.filter(Boolean))].slice(0, 120);
        for (const raw of unique) {
          try {
            const parsed = new URL(raw, self.location.origin);
            const isSameOrigin = (parsed.origin === self.location.origin);
            // Never precache video streams into image cache
            if (parsed.pathname.includes('/video/upload/') || /\.(mp4|webm|mov)(\?|$)/i.test(parsed.pathname)) continue;
            // Use CORS mode for Cloudinary so cached responses are never opaque
            const req = new Request(parsed.href, {
              mode: isSameOrigin ? 'same-origin' : 'cors',
              credentials: isSameOrigin ? 'same-origin' : 'omit'
            });
            const hit = await cache.match(req);
            if (hit && hit.type !== 'opaque') continue;
            const res = await fetch(req);
            if (res && res.ok && (res.type === 'basic' || res.type === 'cors')) {
              await cache.put(req, res.clone());
            }
          } catch (e) {}
        }
        if (event.source && event.source.postMessage) {
          event.source.postMessage({ type: 'PRECACHE_DONE', count: unique.length });
        }
      })
    );
  }
  if (data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  const isCloudinary = url.hostname.includes('cloudinary.com');
  const isSameOrigin = (url.origin === self.location.origin);

  if (url.pathname.includes('/api/') || url.pathname.includes('admin') || url.pathname.includes('superadmin') || url.pathname.includes('/cron/')) {
    return;
  }

  if (!isSameOrigin && !isCloudinary) {
    return;
  }

  // Never intercept video/audio Range streams so Reels stream & seek natively
  if (
    req.destination === 'video'
    || req.destination === 'audio'
    || req.headers.has('range')
    || url.pathname.includes('/video/upload/')
    || /\.(mp4|webm|mov|m3u8)(\?|$)/i.test(url.pathname)
  ) {
    return;
  }

  // Images: cache-first (stay offline; never serve opaque response to non-no-cors request)
  if (isImageRequest(url) || (isCloudinary && url.pathname.includes('/image/upload/'))) {
    event.respondWith(
      caches.open(IMAGE_CACHE).then(async (cache) => {
        const cached = await cache.match(req);
        if (cached && !(cached.type === 'opaque' && req.mode !== 'no-cors')) {
          return cached;
        }
        try {
          const res = await fetch(req);
          if (res && res.ok && (res.type === 'basic' || res.type === 'cors')) {
            cache.put(req, res.clone()).catch(() => {});
          }
          return res;
        } catch (e) {
          if (cached && !(cached.type === 'opaque' && req.mode !== 'no-cors')) return cached;
          return new Response('', { status: 504, statusText: 'Offline' });
        }
      })
    );
    return;
  }

  // CSS/JS/icons: stale-while-revalidate (instant from cache if available, background refresh)
  if (isAssetRequest(url)) {
    event.respondWith(
      caches.open(ASSET_CACHE).then(async (cache) => {
        const cached = await cache.match(req);
        const networkFetch = fetch(req)
          .then((res) => {
            if (res && res.ok && (res.type === 'basic' || res.type === 'cors')) {
              cache.put(req, res.clone()).catch(() => {});
            }
            return res;
          })
          .catch(() => null);

        if (cached) {
          event.waitUntil(networkFetch);
          return cached;
        }

        const networkRes = await networkFetch;
        if (networkRes) return networkRes;
        return cached || fetch(req);
      })
    );
    return;
  }

  const isNavigation = req.mode === 'navigate' || url.pathname.endsWith('.php') || !url.pathname.includes('.');
  if (isNavigation && isSameOrigin) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          if (res && res.ok && res.type === 'basic') {
            const copy = res.clone();
            caches.open(PAGE_CACHE).then((cache) => cache.put(req, copy)).catch(() => {});
          }
          return res;
        })
        .catch(async () => {
          const cached = await caches.match(req);
          if (cached) return cached;
          const home = (await caches.match('./index1')) || (await caches.match('./'));
          if (home) return home;
          return new Response(`<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Offline — ChandraKala Jewellers</title>
<style>
body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; text-align: center; }
.card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 32px 24px; max-width: 420px; width: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
h1 { color: #d97706; margin: 12px 0 8px; font-size: 20px; }
p { color: #94a3b8; font-size: 14px; line-height: 1.5; margin-bottom: 20px; }
button { background: #d97706; color: #fff; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 14px; }
button:hover { background: #b45309; }
</style>
</head>
<body>
<div class="card">
  <div style="font-size: 40px;">📶</div>
  <h1>Connection Issue Detected</h1>
  <p>Your browser could not reach ChandraKala Jewellers. If you are connected to Wi-Fi, your network DNS may still be updating.</p>
  <button onclick="window.location.reload()">Retry Now</button>
</div>
</body>
</html>`, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
        })
    );
    return;
  }
});

self.addEventListener('push', (event) => {
  event.waitUntil((async () => {
    let title = 'ChandraKala Jewellers';
    let body = 'You have a new update. Open the app to view.';
    let url = './index1';
    let image = null;
    
    let fetchUrl = './api/push-latest.php';
    if (self.location.hostname.includes('wuaze.com')) {
      fetchUrl = 'https://chandrakalajewellers.in/api/push-latest.php';
    }

    try {
      const res = await fetch(fetchUrl, { cache: 'no-store' });
      if (res.ok) {
        const data = await res.json();
        if (data && data.title) title = data.title;
        if (data && data.body) body = data.body;
        if (data && data.url) url = data.url;
        if (data && data.image) image = data.image;
      }
    } catch (e) {}

    await self.registration.showNotification(title, {
      body,
      icon: './images/icons/icon-192.png',
      badge: './images/icons/icon-192.png',
      image: image || undefined,
      data: { url },
      vibrate: [80, 40, 80],
      requireInteraction: false
    });
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  let target = (event.notification.data && event.notification.data.url) || './index1';
  
  // Build absolute target URL, routing wuaze.com staging clients to new production domain
  if (target.startsWith('.')) {
    if (self.location.hostname.includes('wuaze.com')) {
      target = new URL(target, 'https://chandrakalajewellers.in/').href;
    } else {
      target = new URL(target, self.location.origin).href;
    }
  } else if (!target.startsWith('http')) {
    if (self.location.hostname.includes('wuaze.com')) {
      target = 'https://chandrakalajewellers.in/' + target;
    } else {
      target = new URL(target, self.location.origin).href;
    }
  } else if (target.includes('wuaze.com')) {
    target = target.replace('chandrakalajewellers.wuaze.com', 'chandrakalajewellers.in');
  }

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if ('focus' in client) {
          client.navigate(target);
          return client.focus();
        }
      }
      if (clients.openWindow) return clients.openWindow(target);
    })
  );
});
