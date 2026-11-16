# SEO Academy — VPS & Dedicated Server Deployment Guide

This guide is designed for system administrators deploying **SEO Academy v5.4.0** on an independent Virtual Private Server (VPS), cloud instance (AWS EC2, DigitalOcean, Hetzner, Linode), or dedicated server running Ubuntu 22.04 / 24.04 LTS.

---

## 1. System Preparation

Update package indexes and install required dependencies:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip nginx
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common php8.3-sqlite3 \
    php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-bcmath \
    php8.3-gd php8.3-zip php8.3-intl php8.3-opcache
```

---

## 2. Directory Setup & Permissions

Extract the application to `/var/www/seo-academy`:

```bash
sudo mkdir -p /var/www/seo-academy
sudo unzip seo-academy-v5.4.0.zip -d /var/www/seo-academy
sudo chown -R www-data:www-data /var/www/seo-academy
sudo chmod -R 775 /var/www/seo-academy/storage /var/www/seo-academy/bootstrap/cache
```

---

## 3. Nginx Virtual Host Configuration

Create `/etc/nginx/sites-available/seo-academy.conf`:

```nginx
server {
    listen 80;
    server_name your-academy-domain.com;
    root /var/www/seo-academy/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Hostinger CloudPanel & Nginx Livewire Endpoint Routing
    # Required when Nginx static file rules (try_files $uri =404) are active:
    location ~* ^/livewire.*\.js$ {
        try_files $uri /index.php?$query_string;
    }

    client_max_body_size 50M;
}
```

### Hostinger CloudPanel Configuration Note

In Hostinger CloudPanel, static asset caching is enabled by default with `try_files $uri =404`. 
- **Pre-Published Assets:** Livewire static assets are pre-published under `public/vendor/livewire/` and compiled Vite assets under `public/build/`. These are served directly by Nginx as static files without requiring a Node.js runtime.
- **Vhost Fallback Rule:** If deploying via git without pre-published assets or if using dynamic Livewire route resolution, add the `location ~* ^/livewire.*\.js$` block shown above in the CloudPanel Site Vhost settings to route `.js` Livewire endpoints to `/index.php?$query_string`.
- **Artisan Asset Publish:** If deploying manually from source, run:
  ```bash
  php artisan vendor:publish --tag=livewire:assets --force
  ```

Enable the configuration and reload Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/seo-academy.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Install SSL via Let's Encrypt Certbot
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-academy-domain.com
```

---

## 4. Crontab (Task Scheduling)

Open the crontab for `www-data`:
```bash
sudo crontab -u www-data -e
```
Add the schedule worker entry:
```bash
* * * * * php /var/www/seo-academy/artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Optional: Supervisord for Background Queue Processing

For institutions with high student volumes (e.g. hundreds of concurrent assignment evaluations or PDF certificates), configure Supervisord to manage background queue workers.

Install Supervisord:
```bash
sudo apt install -y supervisor
```

Create `/etc/supervisor/conf.d/seo-academy-worker.conf`:
```ini
[program:seo-academy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/seo-academy/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/seo-academy/storage/logs/worker.log
stopwaitsecs=3600
```

Start the workers:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start seo-academy-worker:*
```
