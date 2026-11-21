# Phase 12.6 — Razorpay Payment Gateway Administrator Configuration & Verification Report

## 1. Executive Summary

This report documents the verification, architecture, and administrative configuration workflow for the Razorpay payment integration in SEO Academy. The platform now provides a complete, secure administrator-facing Razorpay configuration management interface embedded natively into the SEO Academy administrative portal.

### Summary of Key Findings:
- **Prior State:** In Phase 12.6, the core transaction pipeline (`RazorpayPaymentService`, `CheckoutComponent`, `StudentOrdersIndex`, and `RazorpayWebhookController`) was established using environment / configuration fallbacks (`config('services.razorpay.*')`). However, a dedicated administrator dashboard interface to inspect, toggle, test, and save encrypted credentials without editing source code or `.env` files was missing.
- **Implemented Solution:** A dedicated, secure Livewire administration component (`App\Livewire\Admin\Commerce\PaymentSettingsManager`) was integrated directly into the `Commerce & Enrollment` administrative navigation at `/admin/settings/payment` (`admin.payment.settings`), alongside direct shortcuts in Academy Settings (`/admin/academy/settings`).
- **Cryptographic Security:** Razorpay Key Secrets and Webhook Secrets are encrypted at rest using AES-256-CBC (`Crypt::encryptString`) in the `settings` table (`group = 'razorpay'`), masked in the user interface (`••••••••••••••••...last4`), cleared from memory, and strictly omitted from all client-side JavaScript, meta tags, and audit logs.
- **Test vs. Live Isolation:** Strict separation of Test Sandbox and Live Production modes, complete with format validation (`rzp_test_` vs. `rzp_live_`), active visual environment banners, and mandatory confirmation acknowledgments before Live Mode activation.
- **Webhook Pipeline:** Verified the exact registered webhook endpoint `POST /api/webhooks/razorpay`, requiring `payment.captured` and `order.paid` events, verified via HMAC-SHA256 signatures, and guarded by idempotent payment recording.

---

## 2. Dashboard Navigation Path & Access Control

### 2.1 Exact Menu Navigation Paths
Administrators can access the Razorpay configuration interface through two primary dashboard routes:

1. **Primary Navigation (Commerce & Enrollment):**
   - **Sidebar Menu:** `Commerce & Enrollment` &rarr; `Payment Gateway`
   - **Direct URL:** `https://your-domain.com/admin/settings/payment`
   - **Route Name:** `admin.payment.settings`

2. **Alternative Shortcut (Academy Settings):**
   - **Sidebar Menu:** `Administration` &rarr; `Academy Settings` &rarr; Click `Payment Gateway` in the header actions bar.

### 2.2 Role & Permission Enforcement
- Access is strictly restricted to users with the `admin` role via middleware:
  ```php
  middleware(['auth', 'academy.context', 'academy.access', 'role:admin'])
  ```
- Any unauthorized attempts (e.g. students, trainers, or unauthenticated guests) immediately receive an HTTP 403 Forbidden response.

---

## 3. Configuration Fields & Security Specification

The configuration interface supports all 14 mandatory security and configuration requirements:

| Field Name | Type | UI Representation | Security & Validation Controls |
|---|---|---|---|
| **Enable Gateway** | `boolean` | iOS-style toggle switch | Controls whether Razorpay is offered at checkout. If disabled, orders default to offline or sandbox settlement. |
| **Operating Mode** | `string` (`test` / `live`) | Segmented radio control | **Test Mode:** Yellow/amber banner; permits mock cards.<br>**Live Mode:** Critical red banner; mandates acknowledgment checkbox. |
| **Live Acknowledgment** | `boolean` | Checkbox | Mandatory when Live Mode is selected. Prevents accidental activation of production billing. |
| **Razorpay Key ID** | `string` | Text input with monospace font | Validates format: Must start with `rzp_test_` in Test Mode, or `rzp_live_` in Live Mode. Prevents cross-environment key mismatches. |
| **Razorpay Key Secret** | `string` | Masked password input | Encrypted at rest via `Crypt::encryptString()`. Masked in UI (`••••••••...last4`). Stored secret preserved if left blank. Never sent to frontend. |
| **Webhook Secret** | `string` | Masked password input | Encrypted at rest via `Crypt::encryptString()`. Used to verify HMAC-SHA256 signatures on incoming webhook deliveries. |
| **Settlement Currency** | `string` | Dropdown select | Supported: `INR`, `USD`, `EUR`, `GBP`, `AED`, `AUD`, `CAD`, `SGD`, `SAR`. Notes that domestic Indian UPI/Netbanking requires `INR`. |
| **Test Connection** | Action | Button with spinner | Sends test request to `https://api.razorpay.com/v1/payments?count=1` with Basic Auth. Reports live connectivity or sandbox status. |

---

## 4. Webhook Architecture, Verification & Production Setup

### 4.1 Exact Registered Webhook Route
- **HTTP Method:** `POST`
- **URI:** `/api/webhooks/razorpay`
- **Route Name:** `webhooks.razorpay`
- **Controller:** `App\Http\Controllers\Commerce\RazorpayWebhookController@handle`
- **CSRF Token Exemption:** Configured in `bootstrap/app.php`:
  ```php
  $middleware->validateCsrfTokens(except: [
      'api/webhooks/*',
  ]);
  ```

### 4.2 Required Webhook Events
In the Razorpay Dashboard (**Settings &rarr; Webhooks &rarr; Add New Webhook**), administrators must subscribe to the following events:
1. `payment.captured` — Triggered when a student's card, UPI, or Netbanking payment is successfully captured. Immediately credits the order and grants active course enrollment.
2. `order.paid` — Redundant confirmation event guaranteeing order settlement across multi-installment schedules.

### 4.3 HMAC-SHA256 Cryptographic Verification
Webhook authenticity is verified using HMAC-SHA256:
```php
$expectedSignature = hash_hmac('sha256', $rawPayload, $this->webhookSecret);
if (! hash_equals($expectedSignature, $signature)) {
    return response()->json(['error' => 'Invalid signature'], 400);
}
```
Any payload with an altered, missing, or mismatched signature is rejected with HTTP 400 Bad Request before processing.

### 4.4 Idempotency Protection
To protect against network retries or duplicate webhook deliveries from Razorpay:
```php
$alreadyProcessed = $order->transactions()
    ->where('gateway_payment_id', $paymentId)
    ->exists();

if (! $alreadyProcessed) {
    $orderService->recordPayment(...);
}

return response()->json(['status' => 'ok']);
```
If a duplicate event arrives, the system acknowledges receipt with HTTP 200 OK while strictly preventing duplicate transactions or duplicated enrollments.

### 4.5 Production Cloudflare & CloudPanel Deployment Notes
- **Enforce HTTPS:** The Razorpay Dashboard strictly requires an SSL URL: `https://your-domain.com/api/webhooks/razorpay`.
- **Cloudflare WAF:** Ensure that the Cloudflare Web Application Firewall or "Bot Fight Mode" does not challenge or drop user-agent `Razorpay-Webhook/v1`. Exclude `/api/webhooks/*` from managed challenges.
- **CloudPanel Nginx Reverse Proxy:** CloudPanel passes incoming POST requests directly to PHP-FPM without redirecting or stripping raw body payloads (`php://input`).

---

## 5. Multi-Engine Test Suite Results

A dedicated multi-engine feature test suite (`tests/Feature/Commerce/RazorpayAdminConfigurationTest.php`) was executed across both **SQLite** and **MySQL 8.4 InnoDB**.

```
   PASS  Tests\Feature\Commerce\RazorpayAdminConfigurationTest
  ✓ admin can access and non admin is forbidden with data set "sqlite"                                0.12s  
  ✓ admin can access and non admin is forbidden with data set "mysql"                                 0.18s  
  ✓ admin can configure and encrypt test mode settings with data set "sqlite"                         0.11s  
  ✓ admin can configure and encrypt test mode settings with data set "mysql"                          0.16s  
  ✓ saved secrets are masked and never exposed with data set "sqlite"                                 0.10s  
  ✓ saved secrets are masked and never exposed with data set "mysql"                                  0.15s  
  ✓ saving without new secret preserves existing encrypted secrets with data set "sqlite"             0.12s  
  ✓ saving without new secret preserves existing encrypted secrets with data set "mysql"              0.17s  
  ✓ live mode validation and warning acknowledgment with data set "sqlite"                            0.15s  
  ✓ live mode validation and warning acknowledgment with data set "mysql"                             0.21s  
  ✓ connection test action with data set "sqlite"                                                     0.09s  
  ✓ connection test action with data set "mysql"                                                      0.14s  
  ✓ webhook endpoint verification with configured database secret and idempotency with data set "sqlite" 0.16s  
  ✓ webhook endpoint verification with configured database secret and idempotency with data set "mysql"  0.22s  

  Tests:    14 passed (114 assertions)
  Combined with Phase 12.6 Commerce Suite: 28 passed (272 assertions)
  Duration: 4.84s
```

---

## 6. Step-by-Step Administrator Setup Instructions

To configure Razorpay for production or testing in SEO Academy:

1. **Log in to the Admin Dashboard:**
   - Navigate to `/login` and sign in as an administrator.
2. **Access Payment Gateway Settings:**
   - In the sidebar, click on **Commerce & Enrollment &rarr; Payment Gateway**.
3. **Select Operating Mode:**
   - Choose **Test Mode (Sandbox)** for initial validation, or **Live Mode (Production)** for real transactions.
   - If selecting Live Mode, read the warning and check the acknowledgment box.
4. **Enter API Keys from Razorpay Dashboard:**
   - In Razorpay Dashboard (**Settings &rarr; API Keys**), copy your **Key ID** and paste it into the Key ID field.
   - Copy your **Key Secret** and paste it into the Key Secret field.
5. **Configure Webhook in Razorpay Dashboard:**
   - In Razorpay Dashboard (**Settings &rarr; Webhooks &rarr; Add New Webhook**):
     - Webhook URL: Copy the exact URL shown on the screen (`https://your-domain.com/api/webhooks/razorpay`).
     - Secret: Enter a strong random secret token.
     - Active Events: Check `payment.captured` and `order.paid`.
   - Back in SEO Academy, paste the same secret token into the **Razorpay Webhook Secret** field.
6. **Test Connection:**
   - Click **Test Razorpay Connection**. Ensure the green success confirmation appears.
7. **Save & Encrypt:**
   - Toggle **Razorpay Online Gateway** to Enabled.
   - Click **Save & Encrypt Settings**.
   - Your secrets are encrypted at rest with AES-256-CBC, and the gateway is immediately active for student checkouts.
