# SEO Academy — Shared Hosting Deployment Guide

This guide provides instructions for deploying **SEO Academy v5.4.0** on standard cPanel, Plesk, DirectAdmin, or shared hosting accounts.

---

## 1. Shared Hosting Architecture Overview

SEO Academy is engineered to function out-of-the-box on shared hosting:
- **Zero Daemon Workers Required:** Operates cleanly without Redis, Memcached, Docker, or permanent background supervisor processes.
- **Database-Backed Queues & Sessions:** By default, sessions, caches, and queues are stored in the database.
- **SQLite Support:** Eliminates the need to create complex MySQL databases, users, or privileges if preferred.

---

## 2. Directory Structure on cPanel / Shared Hosting

Most shared hosts place public web files in `public_html` or `www`. To keep application source code and private databases safe from public web access, follow the recommended folder structure:

```text
/home/username/
├── seo-academy/           <-- Main application folder (private)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── storage/
│   ├── vendor/
│   └── .env
└── public_html/           <-- Document Root (points to public files only)
    ├── build/
    ├── index.php
    ├── .htaccess
    └── robots.txt
```

### Procedure:
1. Upload and extract `seo-academy-v5.4.0.zip` into `/home/username/seo-academy`.
2. In cPanel, navigate to **Domains** and edit the **Document Root** for your domain to point to:
   ```text
   /home/username/seo-academy/public
   ```
   *(If your hosting provider does not permit changing the Document Root, move the contents of `seo-academy/public` into `public_html`, and update `index.php` to point to `../seo-academy/vendor/autoload.php` and `../seo-academy/bootstrap/app.php`)*.

---

## 3. Configuring the Scheduled Task (Cron Job)

SEO Academy handles background processing (assessment timeouts, scheduled certificate PDF generation, enrollment expiration notices) through a single periodic task.

In cPanel:
1. Navigate to **Cron Jobs**.
2. Select interval: **Once Per Minute (`* * * * *`)**.
3. In the command field, enter:
   ```bash
   /usr/local/bin/php /home/username/seo-academy/artisan schedule:run >> /dev/null 2>&1
   ```
   *(Replace `/usr/local/bin/php` with your hosting provider's PHP 8.3 CLI binary path, and `/home/username/seo-academy` with your actual installation directory)*.

---

## 4. Setting Up Apache (`.htaccess`)

SEO Academy includes a pre-configured `.htaccess` file in `/public`. Ensure `mod_rewrite` is enabled on your host.

Verify that the `.htaccess` contains the standard Laravel rewrite rules:
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

---

## 5. Shared Hosting Performance & Security Tips

1. **Disable Debug Mode:** In your `.env` file, ensure `APP_DEBUG=false` and `APP_ENV=production`.
2. **Enable Secure Cookies:** If your site has an SSL certificate (e.g. Let's Encrypt), set `SESSION_SECURE_COOKIE=true` in `.env`.
3. **Optimized Caching:** After completing setup, run the following commands via terminal or hosting SSH:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
