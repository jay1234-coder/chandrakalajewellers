/* ChandraKala PWA — offline pages + persistent image cache */
const PAGE_CACHE = 'cj-pages-v10';
const ASSET_CACHE = 'cj-assets-v10';
const IMAGE_CACHE = 'cj-images-v10';
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
            const req = new Request(raw, { mode: 'same-origin', credentials: 'same-origin' });
            const hit = await cache.match(req);
            if (hit) continue;
            const res = await fetch(req);
            if (res && res.ok) await cache.put(req, res.clone());
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
  if (url.origin !== self.location.origin) return;

  if (url.pathname.includes('/api/') || url.pathname.includes('admin') || url.pathname.includes('superadmin') || url.pathname.includes('/cron/')) {
    return;
  }

  // Images: cache-first (stay offline; no re-fetch when cached)
  if (isImageRequest(url)) {
    event.respondWith(
      caches.open(IMAGE_CACHE).then(async (cache) => {
        const cached = await cache.match(req);
        if (cached) return cached;
        try {
          const res = await fetch(req);
          if (res && res.ok && (res.type === 'basic' || res.type === 'cors')) {
            cache.put(req, res.clone()).catch(() => {});
          }
          return res;
        } catch (e) {
          return cached || new Response('', { status: 503, statusText: 'Offline image' });
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
  if (isNavigation) {
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
          const home = await caches.match('./index1');
          return home || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
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
