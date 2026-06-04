#!/usr/bin/env node
/**
 * Build script для Vercel.
 * Читає BACKEND_URL з env і підставляє в vercel.json.
 * Копіює index.html в dist/.
 *
 * Змінні середовища (Vercel Dashboard → Settings → Environment Variables):
 *   BACKEND_URL  — URL бекенду БЕЗ слешу в кінці
 *                  Приклад: https://programist.com.ua
 */

const fs   = require('fs');
const path = require('path');

const backendUrl  = (process.env.BACKEND_URL  || '').replace(/\/$/, '');
const frontendUrl = (process.env.FRONTEND_URL || '').replace(/\/$/, '');

if (!backendUrl) {
  console.error('❌  BACKEND_URL is not set in Vercel environment variables.');
  console.error('    Go to Vercel → Project → Settings → Environment Variables');
  console.error('    Add: BACKEND_URL = https://programist.com.ua');
  process.exit(1);
}

console.log(`✓ BACKEND_URL  = ${backendUrl}`);
console.log(`✓ FRONTEND_URL = ${frontendUrl || '(not set)'}`);

// ── 1. Генеруємо vercel.json з реальним бекенд URL ─────────────────────────
const vercelConfig = {
  outputDirectory: 'dist',
  rewrites: [
    {
      source:      '/api/:path*',
      destination: `${backendUrl}/api/:path*`,
    },
  ],
  headers: [
    {
      source: '/(.*)',
      headers: [
        { key: 'X-Content-Type-Options', value: 'nosniff' },
        { key: 'X-Frame-Options',        value: 'SAMEORIGIN' },
        { key: 'Referrer-Policy',        value: 'strict-origin-when-cross-origin' },
      ],
    },
  ],
};

fs.writeFileSync(
  path.join(__dirname, 'vercel.json'),
  JSON.stringify(vercelConfig, null, 2)
);
console.log('✓ vercel.json updated');

// ── 2. Копіюємо index.html в dist/ ─────────────────────────────────────────
if (!fs.existsSync('dist')) fs.mkdirSync('dist');

let html = fs.readFileSync(path.join(__dirname, 'index.html'), 'utf8');
fs.writeFileSync(path.join(__dirname, 'dist', 'index.html'), html);

console.log('✓ dist/index.html ready');
console.log('✓ Build complete');
