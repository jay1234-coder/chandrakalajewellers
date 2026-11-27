const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const publicDir = path.join(rootDir, 'public');

if (!fs.existsSync(publicDir)) {
  fs.mkdirSync(publicDir, { recursive: true });
}

function copyRecursive(src, dest) {
  if (!fs.existsSync(src)) return;
  const stat = fs.statSync(src);
  if (stat.isDirectory()) {
    if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });
    for (const item of fs.readdirSync(src)) {
      copyRecursive(path.join(src, item), path.join(dest, item));
    }
  } else {
    fs.copyFileSync(src, dest);
  }
}

// Copy static directories
const staticDirs = ['css', 'js', 'images', 'uploads'];
for (const dir of staticDirs) {
  const src = path.join(rootDir, dir);
  const dest = path.join(publicDir, dir);
  copyRecursive(src, dest);
}

// Copy root static files
const staticFiles = [
  'favicon.ico',
  'favicon.png',
  'favicon.svg',
  'apple-touch-icon.png',
  'sw.js',
  'manifest.webmanifest',
  'site.webmanifest',
  'robots.txt',
  'sitemap.xml',
  'sitemap-videos.xml',
  'llms.txt',
  'llms-full.txt',
  'ai.txt',
  'agents.txt',
  'b7e4c91a2f8d4e6ab3c5d0e9f1a2b3c4.txt'
];

for (const file of staticFiles) {
  const src = path.join(rootDir, file);
  const dest = path.join(publicDir, file);
  if (fs.existsSync(src)) {
    fs.copyFileSync(src, dest);
  }
}

fs.writeFileSync(path.join(publicDir, '.gitkeep'), '# public static output directory\n');
console.log('Successfully synced static assets into public directory.');
