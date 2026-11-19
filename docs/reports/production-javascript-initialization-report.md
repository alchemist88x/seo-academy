# SEO Academy — Production JavaScript Initialization Audit & Remediation Report

**Date:** November 19, 2026  
**Target Environment:** `https://learn.varvy.in` (Hostinger CloudPanel / Nginx reverse proxy + Apache backend / PHP 8.3 / MySQL 8.4)  
**Local Environment:** Windows / Laragon / PHP 8.3 / Apache / MySQL 8.4 / Node.js 22 / Vite 8.3.8  
**Framework Stack:** Laravel 13, Livewire 4.4.8, Alpine.js (bundled with Livewire 4), Tailwind CSS v4  

---

## 1. Problem Statement

On the production deployment `https://learn.varvy.in`:
- The browser console reported:
  - `typeof window.Livewire === 'undefined'`
  - `typeof window.Alpine === 'undefined'`
- Interactive buttons across the administrative dashboard (such as **+ Add Student**, **Edit Student**, and modal controls) did not respond to click events.
- The Vite application script (`https://learn.varvy.in/build/assets/app-CMN891wD.js`) and Livewire script tag were present in the HTML source.
- The identical functionality operated correctly in local development under Laragon / Apache.

---

## 2. Root Cause Analysis

### A. Livewire 4 Asset Delivery Architecture
Livewire 4 serves its front-end JavaScript runtime (`livewire.js`) via one of two mechanisms:
1. **Published Static Assets (Preferred):** If physical files exist in `public/vendor/livewire/manifest.json`, Livewire injects a direct static link:
   ```html
   <script src="https://learn.varvy.in/vendor/livewire/livewire.min.js?id=a964a3c8"></script>
   ```
2. **Dynamic Route Fallback:** If `public/vendor/livewire/manifest.json` is missing from the disk, Livewire falls back to generating a dynamic route with an internal hash:
   ```html
   <script src="https://learn.varvy.in/livewire-1ecbbf70/livewire.js?id=a964a3c8"></script>
   ```
   This route is intended to be caught by Laravel's router and streamed dynamically by PHP.

### B. Hostinger CloudPanel Nginx Static Interception
In Hostinger CloudPanel, **Nginx** serves as the front-end reverse proxy in front of Apache/PHP-FPM. CloudPanel's default Nginx vHost configuration contains an aggressive static asset caching and rewrite block:
```nginx
location ~* \.(css|js|jpg|jpeg|gif|png|ico|svg|woff|woff2|ttf|eot)$ {
    try_files $uri =404;
    expires 30d;
    access_log off;
}
```

When the production deployment was uploaded without running `php artisan vendor:publish --tag=livewire:assets`:
1. `public/vendor/livewire/manifest.json` did **not** exist on the server.
2. Livewire generated the dynamic script tag: `<script src="/livewire-1ecbbf70/livewire.js?id=a964a3c8"></script>`.
3. Because the URL path ended with `.js`, **Nginx intercepted the request** before it ever reached Apache or Laravel.
4. Because no physical file existed at `public/livewire-1ecbbf70/livewire.js`, Nginx's `try_files $uri =404;` rule triggered and returned an **HTML 404 Not Found error page** with `Content-Type: text/html`.

### C. Downstream Initialization Failure
- Because the browser received an HTML 404 page instead of JavaScript, the script tag failed to execute.
- In Livewire 4, Alpine.js is bundled directly inside the Livewire JavaScript payload and assigned to `window.Alpine` and `window.Livewire`.
- With `livewire.js` failing to load, both `window.Livewire` and `window.Alpine` remained `undefined`.
- When `resources/js/app.js` executed, all attempts to register Alpine stores (`Alpine.store('theme', ...)`) failed with uncaught exceptions.
- Why this worked locally: In local Laragon / Apache, `.htaccess` redirects all non-existent files to `index.php`, allowing the dynamic route to execute via PHP without an Nginx interception.

---

## 3. Remediation & Code Changes

### A. Automatic Asset Publishing in Composer (`composer.json`)
Configured Composer hooks so that any installation or deployment automatically publishes Livewire assets to `public/vendor/livewire` without manual intervention:
```json
"scripts": {
    "setup": [
        "@php artisan vendor:publish --tag=livewire:assets --ansi --force",
        ...
    ],
    "post-autoload-dump": [
        "@php artisan vendor:publish --tag=livewire:assets --ansi --force"
    ],
    "post-update-cmd": [
        "@php artisan vendor:publish --tag=livewire:assets --ansi --force"
    ]
}
```

### B. Standalone Production Release Packaging Pipeline (`scripts/build-release-package.ps1`)
To honor the strict requirement that **no Node.js or npm runtime is required on the production server**, created an automated packaging script that:
1. Executes `php artisan vendor:publish --tag=livewire:assets --ansi --force` to populate `public/vendor/livewire/` with all static assets (`livewire.min.js`, `manifest.json`, etc.).
2. Executes `npm run build` to compile Tailwind CSS v4 and application JavaScript into `public/build/`.
3. Validates that `public/build/manifest.json` and `public/vendor/livewire/manifest.json` exist.
4. Cleans sensitive development files (`.env`, `.git`, `tests/`, `node_modules/`, scratch caches).
5. Compresses the production tree into `dist/seo-academy-v5.6.0-candidate.zip` and generates `dist/release-manifest-v5.6.0.json`.

### C. Hostinger CloudPanel Nginx vHost Configuration (`docs/customer/vps-deployment.md`)
Added explicit guidance for Hostinger CloudPanel deployments to ensure any dynamic Livewire endpoints are routed properly:
```nginx
# Allow dynamic Livewire JavaScript routes to fall through to PHP
location ~* ^/livewire.*\.js$ {
    try_files $uri /index.php?$query_string;
}

# Standard static asset rule
location ~* \.(css|js|jpg|jpeg|gif|png|ico|svg|woff|woff2|ttf|eot)$ {
    try_files $uri =404;
    expires 30d;
    access_log off;
}
```

### D. Modal Layout & Viewport Overflow Fixes
Addressed visual layout defects on the Add/Edit Student modal:
1. `resources/css/app.css`: Removed hardcoded `max-width: 32rem` from `.modal-panel` so modals respect the utility sizing classes (`maxWidth="xl"` = `max-w-2xl` / 672px).
2. `resources/views/components/modal.blade.php`: Added scroll position reset (`this.$refs.modalBody.scrollTop = 0`) on modal open, ensuring top form fields ("First Name", "Last Name") are immediately visible. Pinned header and footer with `sticky`.
3. `resources/views/livewire/admin/students-index.blade.php`: Cleaned duplicate asterisks on required fields and set `maxWidth="xl"` for comfortable two-column field alignment.

---

## 4. Release Package & Build Verification

### Package Details
- **Archive:** `dist/seo-academy-v5.6.0-candidate.zip`
- **Archive Size:** 28,436,801 bytes (27.1 MB)
- **SHA256 Checksum:** `a90c5bab9e587f66fe0542cd1e59a73c28e3453d5b650e49a3945e265ef3840d`
- **Release Manifest:** `dist/release-manifest-v5.6.0.json`

### Asset Verification Inside Staging / Package
| Directory / File | Status | Notes |
| :--- | :--- | :--- |
| `public/build/manifest.json` | **VERIFIED** | Contains mappings for CSS, JS, and all DM Sans fonts |
| `public/build/assets/app-CtX6R-Qy.css` | **VERIFIED** | 149.75 kB (Tailwind CSS v4 compiled bundle) |
| `public/build/assets/app-CMN891wD.js` | **VERIFIED** | 1.81 kB (Application runtime script) |
| `public/vendor/livewire/manifest.json` | **VERIFIED** | Livewire 4 asset manifest |
| `public/vendor/livewire/livewire.min.js` | **VERIFIED** | Pre-bundled Livewire + Alpine.js production runtime |
| `node_modules/` | **EXCLUDED** | Zero Node.js runtime required in production |

---

## 5. Verification & Test Evidence

### A. Clean Browser Session Verification
Automated browser testing was conducted against the application running under production build assets:
1. **Livewire & Alpine Availability:**
   - `typeof window.Livewire`: `"object"` (**PASS**)
   - `typeof window.Alpine`: `"object"` (**PASS**)
2. **Interactive Controls:**
   - **Theme Toggle:** Toggles light/dark modes smoothly with persisted state.
   - **Add Student Modal:** Button click opens the dialog instantly.
   - **Header Visibility:** Pinned at top with title, subtitle, and `X` close icon (**PASS**).
   - **Footer Visibility:** Pinned at bottom with `Cancel` and `Create Student Account` buttons (**PASS**).
   - **Form Submission:** Created QA student record, validated input, and persisted to database.
   - **Search & Filtering:** Dynamic table updates without page reload.
   - **Console Errors:** 0 JavaScript errors or unhandled exceptions.

### B. Automated Regression Test Suite
Executed the targeted administrative feature test suite:
- `tests/Feature/Admin/StudentManagementTest.php`
- `tests/Feature/StagedInstallationTest.php`
- **Result:** **20 passed tests, 198 assertions (0 failures, 0 regressions)**.

---

## 6. Step-by-Step Production Deployment Instructions

### Prerequisites
- Hostinger CloudPanel with PHP 8.3 / 8.4 and MySQL 8.4+.
- Zero Node.js / npm requirement.

### Step 1: Upload and Extract Package
Upload `dist/seo-academy-v5.6.0-candidate.zip` to the CloudPanel site root (e.g. `/home/<user>/htdocs/learn.varvy.in`) and extract:
```bash
unzip -o seo-academy-v5.6.0-candidate.zip -d /home/<user>/htdocs/learn.varvy.in
```

### Step 2: Confirm Published Assets Exist
Ensure the static assets are in place:
```bash
ls -la public/build/manifest.json
ls -la public/vendor/livewire/manifest.json
```
Because `public/vendor/livewire/manifest.json` is present, Livewire will now automatically serve `/vendor/livewire/livewire.min.js` directly as a static file.

### Step 3: Run Database Migrations and Cache Optimization
```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 4 (Optional / Best Practice): CloudPanel Nginx vHost Update
In the CloudPanel site settings under **Vhost**, verify or add the Livewire route exception above the static caching rule:
```nginx
location ~* ^/livewire.*\.js$ {
    try_files $uri /index.php?$query_string;
}
```
Reload Nginx.

### Step 5: Verification in Browser
1. Open `https://learn.varvy.in/admin/students` in an incognito window.
2. Open Developer Tools Console and verify:
   ```javascript
   typeof window.Livewire !== 'undefined' // true
   typeof window.Alpine !== 'undefined'   // true
   ```
3. Click **+ Add Student** to confirm the dialog opens, fields are properly aligned, and submission works.
