# Guest Welcome & Check-In System

A Laravel 12 + MySQL-ready guest welcome and check-in web application for short-term rental, serviced residence, and hotel-style properties.

## Features

- Public guest URL protected by booking ID and secure token.
- Date-aware guest flow: pre-arrival ID upload, check-in day GPS verification, checked-in welcome guide, and checkout-day instructions.
- Admin dashboard with property, guest, category, content, amenity, and settings management.
- Secure private storage for uploaded photo IDs.
- Public storage for property, guide, and amenity images.
- Responsive Blade and Tailwind CSS interface for mobile, tablet, and desktop.
- Seeded demo property: Lumina Hotel & Residences.

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure MySQL in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=welcome_guide
DB_USERNAME=your_mysql_user
DB_PASSWORD=your_mysql_password
```

Then run:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

## Demo Login

- Admin URL: `/admin`
- Email: `admin@example.com`
- Password: `password`

Seeded guest URL:

```text
/guest/LUMINA-DEMO/lumina-demo-secure-token
```

## Client Preview Screenshots & PDF

Generate a client-ready screenshot package and PDF presentation:

```bash
npm run build
npm run preview:screenshots
npm run preview:pdf
```

Outputs:

- Screenshots: `public/client-preview/screenshots`
- HTML preview: `public/client-preview/index.html`
- PDF presentation: `public/client-preview/client-preview-laravel-guest-portal.pdf`

The screenshot script runs `php artisan migrate --seed --force`, starts Laravel locally on `http://127.0.0.1:8003` if needed, logs in as the demo admin, captures guest/admin/responsive screens, and writes a manifest used by the PDF generator. If Chrome is not found automatically, set `CHROME_PATH` to your Chrome or Edge executable.

## Deployment Notes for AlmaLinux / GoDaddy VPS

- Use PHP 8.2+ with required extensions: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML.
- Point the web server document root to the Laravel `public` directory.
- Set file permissions so the web server can write to `storage` and `bootstrap/cache`.
- Run `composer install --no-dev --optimize-autoloader`.
- Run `npm install && npm run build` locally or on the VPS.
- Set production `.env` values: `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL`, and MySQL credentials.
- Run `php artisan migrate --force`, `php artisan storage:link`, `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
- Ensure HTTPS is enabled; browser geolocation requires a secure context on production domains.

## Testing Checklist

- Guest URL opens and rejects invalid booking/token combinations.
- Guest email and photo ID upload validate and save correctly.
- Date-based guest page switching works before check-in, on check-in day, after check-in, and on checkout day.
- GPS verification succeeds within the configured radius and shows a manual approval message when it fails.
- Admin override marks the guest checked in.
- Parking question saves yes/no and reveals parking details when needed.
- Category dashboard and category detail pages work.
- Admin CRUD pages save properties, guests, categories, settings, content, and amenities.
- Uploaded property and guide images render from `storage/public`.
- Uploaded photo IDs are only downloadable through authenticated admin routes.
- All links work on mobile, tablet, and desktop layouts.
