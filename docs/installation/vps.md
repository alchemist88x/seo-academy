# VPS & Dedicated Server Deployment Profile — SEO Academy

While SEO Academy runs cleanly on shared hosting, a VPS (Ubuntu/Debian with Nginx or Apache, PHP 8.3-FPM, and MySQL 8.0+) unlocks dedicated queue workers, optional Redis caching, and optional self-hosted FOSS services.

---

## 1. Recommended VPS Baseline

- **OS:** Ubuntu 24.04 LTS / Debian 12
- **Web + PHP:** Nginx + PHP 8.3-FPM (`pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `xml`, `ctype`, `json`, `fileinfo`, `opcache`)
- **Database:** MySQL 8.0+ (InnoDB, `utf8mb4_unicode_ci`)
- **Process Manager:** `systemd` or `supervisor` for persistent `php artisan queue:work`

### Example `systemd` Queue Worker (`/etc/systemd/system/seo-academy-worker.service`)

```ini
[Unit]
Description=SEO Academy Laravel Queue Worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/seo-academy/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Keep the `schedule:run` 1-minute cron entry enabled for scheduled tasks.

---

## 2. Optional Redis Acceleration

On a VPS, you may optionally switch from database drivers to Redis for lower-latency cache, sessions, and queues:

```env
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

## 3. Optional Self-Hosted FOSS Companion Services

All core LMS features run without external services. On a VPS, you can optionally host companion FOSS tools alongside SEO Academy:

| Service | Purpose | Phase / Status |
|---|---|---|
| **Ollama** | Local, zero-token-cost LLM inference via `LMS_AI_DRIVER=ollama` (`http://127.0.0.1:11434`). | Phase 1.1 provider driver implemented; Phase 5 UI planned. |
| **PeerTube** | Self-hosted federated video streaming & transcoding for large course libraries. | Optional external video host (planned for Phase 2+ media workflows). |
| **Jitsi Meet / BigBlueButton** | Self-hosted live video classes & webinars. | Planned for Phase 4 (Live Classes). |

---

## 4. Security & Backups on VPS

- Terminate TLS (Let's Encrypt / Certbot) at Nginx and set `APP_URL=https://...` in `.env`.
- Point Nginx `root` strictly to `/var/www/seo-academy/public`.
- Automate daily `mysqldump --single-transaction` and `storage/app/private/media/` backups to off-site object storage (see [../backup-restore.md](../backup-restore.md)).
