# SEO Academy — Apache Web Server Configuration Guide

SEO Academy is designed primarily for Apache web servers (shared hosting, cPanel, Plesk, and Apache on Ubuntu/Debian/RHEL).

---

## 1. Web Root Configuration (Critical Requirement)

The web server document root **must point to the project's `public/` subdirectory**, not the project root directory.

### Why is this mandatory?
Pointing to `public/`:
- Protects your `.env` file, database files, and private student uploads from being downloaded directly over HTTP.
- Enables Laravel's front-controller (`public/index.php`) to route incoming requests cleanly.

---

## 2. Shared Hosting / cPanel Setup

On shared hosting (cPanel, DirectAdmin, Plesk):

### Scenario A: Your Host Allows Custom Document Roots
1. In cPanel, navigate to **Domains** &gt; **Document Root**.
2. Set the document root for your domain to:
   ```text
   /home/username/public_html/seo-academy/public
   ```
   (Or if installed at the root of `public_html`, point the domain to `/home/username/public_html/public`).

### Scenario B: Document Root is Fixed to `public_html`
If your shared hosting provider does not permit changing the document root:
1. Upload the entire project **one level above** `public_html` (e.g. `/home/username/seo-academy`).
2. Copy the contents of `/home/username/seo-academy/public/` directly into `/home/username/public_html/`.
3. In `/home/username/public_html/index.php`, adjust the bootstrap path:
   ```php
   require __DIR__.'/../seo-academy/bootstrap/preflight.php';
   require __DIR__.'/../seo-academy/vendor/autoload.php';
   $app = require_once __DIR__.'/../seo-academy/bootstrap/app.php';
   ```

---

## 3. Apache VirtualHost Configuration (VPS / Dedicated)

If you manage your own Apache virtual host (e.g. `/etc/apache2/sites-available/seo-academy.conf`):

```apache
<VirtualHost *:80>
    ServerName your-academy.com
    ServerAlias www.your-academy.com
    DocumentRoot /var/www/seo-academy/public

    <Directory /var/www/seo-academy/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/seo-academy_error.log
    CustomLog ${APACHE_LOG_DIR}/seo-academy_access.log combined
</VirtualHost>
```

Enable `mod_rewrite` and reload Apache:
```bash
sudo a2enmod rewrite
sudo systemctl reload apache2
```

---

## 4. Default `.htaccess` in `public/`

SEO Academy includes a pre-configured `.htaccess` file inside `public/`:

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

## 5. Security Verification

After configuring Apache, test that non-public files are inaccessible:

1. Open `https://your-domain.com/.env` in your browser.
   - **Expected Result:** `403 Forbidden` or `404 Not Found`. It must **never** download or display your configuration.
2. Open `https://your-domain.com/storage/app/private/` in your browser.
   - **Expected Result:** `403 Forbidden` or `404 Not Found`. Private files must never be publicly accessible.
