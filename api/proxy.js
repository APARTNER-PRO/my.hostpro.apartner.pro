/**
 * Vercel Serverless Function Proxy (Node.js runtime)
 * =====================================================
 * Цей файл виконується на Vercel як serverless function.
 * Локально (OSPanel) — ігнорується, там працює index.php.
 *
 * Маршрутизує всі /api/* запити → http://lemons.developer.pp.ua/api/*
 *
 * Чому HTTP а не HTTPS:
 *   Без Cloudflare проксі SSL сертифікат хостингу не покриває lemons.developer.pp.ua,
 *   тому з'єднання Vercel→бекенд йде через HTTP (зв'язок між двома серверами).
 *   Клієнт→Vercel завжди HTTPS.
 */

const http = require('http');
const https = require('https');

const BACKEND_HOST = 'lemons.developer.pp.ua';
// HTTP тому що без CF проксі немає валідного SSL на хостингу для цього субдомену
const BACKEND_PROTOCOL = 'http';
const BACKEND_PORT = 80;

module.exports = async function handler(req, res) {
  // OPTIONS preflight — відповідаємо одразу без звернення до бекенду
  if (req.method === 'OPTIONS') {
    setCorsHeaders(req, res);
    res.status(204).end();
    return;
  }

  // req.url = /auth/login?foo=bar (Vercel strips /api prefix via rewrites)
  // Нам потрібен повний шлях для бекенду: /api/auth/login?foo=bar
  const reqPath = req.url || '/';
  const backendPath = reqPath.startsWith('/api') ? reqPath : `/api${reqPath}`;

  const targetUrl = `${BACKEND_PROTOCOL}://${BACKEND_HOST}:${BACKEND_PORT}${backendPath}`;
  console.log(`[proxy] ${req.method} ${req.url} → ${targetUrl}`);

  // Зчитуємо body
  let bodyBuffer = null;
  if (!['GET', 'HEAD'].includes(req.method)) {
    bodyBuffer = await readBody(req);
  }

  // Формуємо заголовки
  const forwardHeaders = {};
  for (const [key, value] of Object.entries(req.headers)) {
    const lower = key.toLowerCase();
    if (['host', 'x-forwarded-host', 'connection'].includes(lower)) continue;
    forwardHeaders[lower] = value;
  }
  forwardHeaders['host'] = BACKEND_HOST;
  forwardHeaders['x-forwarded-host'] = req.headers['host'] || 'my.hostpro.apartner.pro';
  forwardHeaders['x-forwarded-proto'] = 'https';
  if (bodyBuffer) {
    forwardHeaders['content-length'] = String(bodyBuffer.length);
  }

  try {
    const { statusCode, headers: respHeaders, body } = await doRequest(
      BACKEND_HOST, BACKEND_PORT, backendPath,
      req.method, forwardHeaders, bodyBuffer,
      BACKEND_PROTOCOL === 'https'
    );

    setCorsHeaders(req, res);

    // Копіюємо заголовки відповіді (крім тих що ми вже встановили через CORS)
    const skipKeys = new Set([
      'transfer-encoding', 'connection', 'keep-alive',
      'access-control-allow-origin', 'access-control-allow-credentials',
      'access-control-allow-methods', 'access-control-allow-headers',
      'access-control-max-age', 'vary',
    ]);
    for (const [key, value] of Object.entries(respHeaders)) {
      if (!skipKeys.has(key.toLowerCase())) {
        res.setHeader(key, value);
      }
    }

    res.status(statusCode).send(body);
  } catch (err) {
    console.error('[proxy] Error:', err.message);
    setCorsHeaders(req, res);
    res.status(502).json({
      error: 'Backend unavailable',
      message: err.message,
      backend: targetUrl,
    });
  }
};

/** Встановлює CORS заголовки відповіді */
function setCorsHeaders(req, res) {
  const origin = req.headers['origin'] || '';
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  res.setHeader('Access-Control-Max-Age', '86400');

  const isAllowedOrigin =
    origin.includes('hostpro.apartner.pro') ||
    origin.includes('apartner.pro') ||
    origin.includes('localhost') ||
    origin.includes('127.0.0.1');

  if (origin && isAllowedOrigin) {
    res.setHeader('Access-Control-Allow-Origin', origin);
    res.setHeader('Access-Control-Allow-Credentials', 'true');
    res.setHeader('Vary', 'Origin');
  } else {
    res.setHeader('Access-Control-Allow-Origin', '*');
  }
}

/** Зчитує тіло запиту в Buffer */
function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', chunk => chunks.push(Buffer.from(chunk)));
    req.on('end', () => resolve(Buffer.concat(chunks)));
    req.on('error', reject);
  });
}

/** Виконує HTTP(S) запит до бекенду */
function doRequest(hostname, port, path, method, headers, body, useHttps) {
  return new Promise((resolve, reject) => {
    const lib = useHttps ? https : http;
    const options = { hostname, port, path, method, headers, timeout: 30000 };

    const proxyReq = lib.request(options, proxyRes => {
      const chunks = [];
      proxyRes.on('data', chunk => chunks.push(chunk));
      proxyRes.on('end', () => resolve({
        statusCode: proxyRes.statusCode ?? 502,
        headers: proxyRes.headers ?? {},
        body: Buffer.concat(chunks),
      }));
      proxyRes.on('error', reject);
    });

    proxyReq.on('error', reject);
    proxyReq.on('timeout', () => {
      proxyReq.destroy();
      reject(new Error('Backend request timeout (30s)'));
    });

    if (body && body.length > 0) {
      proxyReq.write(body);
    }
    proxyReq.end();
  });
}
