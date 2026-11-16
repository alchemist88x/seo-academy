# SEO Academy — Security & Hardening Recommendations

This document outlines industry best practices for securing your self-hosted **SEO Academy** production installation.

---

## 1. Environment & Configuration Hardening

### Disable Debug Mode
In production, ensure `APP_DEBUG=false` in your `.env` file:
```dotenv
APP_ENV=production
APP_DEBUG=false
```
When debug mode is enabled, uncaught exceptions expose sensitive environment variables, database passwords, and internal code paths in the browser.

### Application Encryption Key (`APP_KEY`)
The `APP_KEY` is a 256-bit AES cryptographic key used to encrypt student verification tokens, session cookies, and stored SMTP passwords.
- Never share your `APP_KEY`.
- Never commit `.env` into version control.
- Keep an encrypted offline backup of your `APP_KEY` alongside your regular database backups.

---

## 2. Web Server & File System Isolation

### Isolate Document Root
The web server's document root must point exclusively to the `/public` directory:
- **Nginx:** `root /var/www/seo-academy/public;`
- **Apache:** `DocumentRoot /var/www/seo-academy/public`

Pointing to the application root directory exposes your private `.env` file and SQLite databases directly to public internet download.

### Block Hidden Files
Ensure your web server configuration rejects any requests to hidden files or dotfiles:
```nginx
location ~ /\.(?!well-known).* {
    deny all;
}
```

---

## 3. SSL / HTTPS & Cookie Security

### Enforce HTTPS
Always install an SSL/TLS certificate (e.g. Let's Encrypt, Cloudflare Origin Certificate, or Commercial SSL).

In your `.env` file, enforce secure cookie transmission:
```dotenv
SESSION_SECURE_COOKIE=true
```
This flag instructs browsers to only transmit session identifiers over encrypted TLS connections, preventing session hijacking on public Wi-Fi networks.

---

## 4. Content Security Policy & Security Headers

SEO Academy automatically transmits defense-in-depth security headers via `SecurityHeadersMiddleware`:
- `X-Content-Type-Options: nosniff` (prevents MIME-type sniffing attacks)
- `X-Frame-Options: SAMEORIGIN` (prevents clickjacking by blocking iframe embedding on foreign domains)
- `Referrer-Policy: strict-origin-when-cross-origin` (prevents URL leakage)
- `Permissions-Policy: camera=(), microphone=(), geolocation=()` (disables browser sensor hardware)

---

## 5. Administrative Account Hygiene

- **Use Unique Passwords:** Administrative and Trainer accounts must utilize high-entropy passwords (minimum 12 characters combining letters, numbers, and symbols).
- **Principle of Least Privilege:** Assign the `trainer` or `support_staff` role to instructors and customer service personnel. Never grant full `admin` permissions to staff members who do not require academy configuration or backup access.
- **Role Invariant:** SEO Academy strictly maintains four roles (`admin`, `trainer`, `support_staff`, `student`). There are no hidden or backdoored superuser accounts.
