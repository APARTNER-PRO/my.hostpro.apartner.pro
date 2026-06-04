# Billing Portal — Frontend

Статичний SPA. Деплоїться на **Vercel** безкоштовно.
Бекенд URL прихований — клієнти бачать тільки Vercel домен.

---

## Як це працює

```
Браузер → billing.vercel.app/api/auth/login
              ↓ (Vercel proxy, прихований)
         programist.com.ua/api/auth/login → PHP backend
```

Клієнт ніколи не знає реальну адресу бекенду.

---

## Деплой на Vercel

### 1. GitHub репозиторій

```bash
git init
git add .
git commit -m "billing frontend"
git remote add origin https://github.com/YOUR_USER/billing-frontend.git
git push -u origin main
```

### 2. Vercel проект

1. [vercel.com](https://vercel.com) → **Add New Project**
2. Імпортуй репозиторій
3. Framework Preset: **Other**
4. Build Command: `node build.js`
5. Output Directory: `dist`

### 3. Environment Variables у Vercel

Vercel Dashboard → Project → **Settings → Environment Variables**:

| Змінна | Значення | Опис |
|--------|----------|------|
| `BACKEND_URL` | `https://programist.com.ua` | URL твого PHP сервера |
| `FRONTEND_URL` | `https://billing.vercel.app` | URL цього Vercel проекту |

> ⚠️ `BACKEND_URL` — єдине місце де зберігається реальна адреса бекенду.
> В код фронтенду він **не потрапляє**.

### 4. CORS на бекенді

В `.env` бекенду встанови:
```env
FRONTEND_URL=https://billing.vercel.app
DEPLOY_MODE=split
```

---

## Варіант B: фронт і бек на одному домені

Якщо хостиш обидва на `programist.com.ua`:

```
public_html/
├── index.html          ← фронтенд (з billing-frontend/)
└── api/                ← або backend/ з .htaccess rewrite
    └── index.php
```

`.env` бекенду:
```env
DEPLOY_MODE=same-domain
FRONTEND_URL=https://programist.com.ua
BACKEND_URL=https://programist.com.ua
```

`vercel.json` в цьому випадку не потрібен.

---

## Локальна розробка

```bash
# Vercel CLI (емулює proxy)
npm i -g vercel
BACKEND_URL=http://localhost:8000 FRONTEND_URL=http://localhost:3000 vercel dev

# Або простий сервер (потрібен запущений бекенд на :8000)
npx serve dist -p 3000
```

---

## Альтернативи Vercel

### Netlify
Файл `netlify.toml`:
```toml
[build]
  command = "BACKEND_URL=https://programist.com.ua node build.js"
  publish = "dist"

[[redirects]]
  from = "/api/*"
  to = "https://programist.com.ua/api/:splat"
  status = 200
  force = true
```

### Cloudflare Pages
`public/_redirects`:
```
/api/*  https://programist.com.ua/api/:splat  200
```
Build command: `node build.js`

