# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

RAGEFILL — Telegram Mini App for a catalog of handmade super hot sauces. PHP backend + vanilla JS frontend + SQLite database. Deployed on Apache at `ragefill.glosstechn.by`.

Detailed technical documentation is in `agent.md` — always keep it in sync with code changes.

## Commands

```bash
# Install dependencies
composer install

# Run local dev server (PHP built-in)
php -S localhost:8000 -t public

# Set up Telegram bot (one-time, after configuring bot_token in config.php)
# Open in browser: http://localhost:8000/../bot.php?setup=1
```

No tests, linters, or build steps are configured.

## Architecture

**Entry points:**
- `public/index.php` — Slim 4 app: API routes, SSR catalog page, SEO pages (sitemap, robots.txt), individual product pages (`/sauce/{slug}`)
- `bot.php` — Telegram Bot webhook handler (standalone, not routed through Slim)

**Backend (src/):**
- `Database.php` — SQLite wrapper with auto-migrations (checks and adds missing columns on init). Single table `sauces`. Cyrillic-to-latin slug generation with uniqueness guarantee
- `AuthMiddleware.php` — PSR-15 middleware; HMAC-SHA256 Bearer tokens (24h expiry), password from config
- `SeoHelper.php` — generates meta tags, JSON-LD (Product, BreadcrumbList, Organization), sitemap.xml, robots.txt, and SSR product cards for the catalog

**Frontend (public/):**
- `catalog.html` + `js/catalog.js` — Telegram Mini App catalog with filters, search, modal (bottom sheet on mobile). Uses `telegram-web-app.js` SDK
- `admin.html` + `js/admin.js` — Admin panel with Quill.js WYSIWYG editor for sauce CRUD
- `css/style.css` — single stylesheet for both catalog and admin; admin styles scoped via `body:has(.admin-header)`

**Key patterns:**
- Config is in `config.php` (returns array). Contains bot_token, admin_password, db_path, upload settings
- Images are uploaded to `public/uploads/`, auto-converted to WebP via GD, center-cropped to square (800x800)
- Admin API group (`/api/admin/*`) is protected by AuthMiddleware; public API (`/api/sauces`, `/api/settings`) is open
- Update endpoint uses POST (not PUT) with multipart/form-data to support file uploads
- Catalog main page (`/`) does SSR: injects product cards HTML and SEO meta into `catalog.html` template via placeholder replacement (`{{SSR_PRODUCTS}}`, `{{SEO_META}}`, etc.)
- Product pages (`/sauce/{slug}`) are fully server-rendered in PHP (no template file)
- Categories: `sauce`, `gift_set`, `pickled_pepper`, `spicy_peanut`, `spice`
- All Cloudflare-hosted scripts use `data-cfasync="false"` to disable Rocket Loader

**Database:**
- SQLite in `database/ragefill.db`, WAL mode
- Migrations run automatically on every request via `Database::__construct()`. New columns are added via ALTER TABLE if missing
- `is_active=0` hides from catalog entirely; `in_stock=0` shows as greyed out "out of stock"
