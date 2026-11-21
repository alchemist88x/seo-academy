# Phase 12.6 — Complete Course & Bundle Commerce, Pricing, Coupons, Manual Enrollment and Installment Payments

## 1. Executive Summary

Phase 12.6 transforms SEO Academy into a complete, enterprise-grade digital education commerce platform. The implementation delivers robust end-to-end capabilities across course and bundle pricing, automated currency resolution, coupon promotions, online payment gateways, direct offline/scholarship enrollments, automated installment payment milestones (including the landmark 60% course completion plan), student payment tracking, and executive financial reporting.

### Key Architectural Tenets Delivered:
- **Tenant & Role Alignment:** Full compliance with single-academy multi-tenant architecture and strictly 4 roles (`admin`, `trainer`, `support_staff`, `student`).
- **Revenue & Financial Integrity:** Strict isolation between academic progression and financial status. Complimentary and scholarship enrollments grant full access while strictly contributing \$0.00 to verified cash revenue tallies.
- **Milestone Determinism:** Authoritative 60% completion milestone triggering leveraging `LearningProgressService` summary calculations, with exact mathematical threshold evaluations (`<60%` = upcoming, `>=60%` = due) and idempotent event dispatch.
- **Payment Gateway Security:** Razorpay HMAC-SHA256 signature verification for client checkout and webhook payloads (`/api/webhooks/razorpay`) with idempotency protections.
- **Concurrency & Anti-Fraud Controls:** Concurrency-safe coupon usage counts with database row locking (`lockForUpdate`), along with CSV formula injection mitigation (`=`, `+`, `-`, `@`).

---

## 2. Database Schema & Data Models

A total of 8 dedicated database tables were engineered and integrated into the platform via migration `2026_11_21_040000_create_commerce_and_installment_tables_phase12_6.php`:

| Table Name | Description | Key Columns / Constraints |
|---|---|---|
| `course_bundles` | Bundled collections of courses with custom pricing and ownership policies | `id`, `academy_id`, `title`, `slug`, `price`, `currency`, `original_total_price`, `discount_price`, `discount_percentage`, `regional_prices`, `ownership_policy`, `status` |
| `course_bundle_courses` | Pivot table mapping courses to bundles with explicit sequence order | `id`, `bundle_id`, `course_id`, `display_order` |
| `commerce_coupons` | Promotion and discount vouchers | `id`, `academy_id`, `code`, `discount_type`, `discount_value`, `max_discount_amount`, `global_usage_limit`, `per_student_usage_limit`, `usage_count`, `is_active` |
| `commerce_installment_plans` | Installment policies, deposit rules, and completion milestones | `id`, `academy_id`, `title`, `plan_type`, `initial_deposit_type`, `initial_deposit_value`, `number_of_installments`, `completion_milestone_percentage`, `access_policy`, `grace_period_days` |
| `commerce_orders` | Master orders capturing immutable pre-order pricing snapshots | `id`, `academy_id`, `user_id`, `order_number`, `orderable_type`, `orderable_id`, `currency`, `subtotal_amount`, `discount_amount`, `total_amount`, `paid_amount`, `outstanding_balance`, `payment_status` |
| `commerce_order_installments` | Granular installment payment schedules per order | `id`, `order_id`, `installment_number`, `amount`, `due_condition`, `milestone_percentage`, `status`, `milestone_triggered_at`, `due_date`, `paid_at` |
| `commerce_transactions` | Immutable transaction logs for payments and refunds | `id`, `order_id`, `installment_id`, `transaction_type`, `payment_gateway`, `amount`, `currency`, `gateway_order_id`, `gateway_payment_id`, `status` |
| `commerce_coupon_redemptions` | Audit logs of redeemed coupons linked to user and order | `id`, `coupon_id`, `user_id`, `order_id`, `discount_applied`, `redeemed_at` |

Additionally, additive non-destructive columns were provisioned:
- `courses`: `discount_price`, `discount_percentage`, `discount_starts_at`, `discount_expires_at`, `regional_prices`, `is_coupon_eligible`, `installment_plan_id`.
- `enrollments`: `order_id`, `bundle_id`, `access_status`, `financial_status`.

---

## 3. Architecture & Domain Services

### 3.1 Currency & Pricing Resolution Engine (`CurrencyAndPricingService`)
- **ISO 4217 Currency Support:** Defaults to USD with multi-currency options: INR, EUR, GBP, AED, AUD, CAD, SGD, SAR.
- **Geo-IP / Cloudflare Header Detection:** Resolves visitor country via `CF-IPCountry` or `X-Country-Code` headers, automatically selecting the appropriate default currency.
- **Regional Price Overrides:** Allows administrators to define region-specific pricing matrices per course or bundle.
- **Immutable Pricing Snapshots:** Freezes base prices, applied regional currencies, discounts, and tax deductions into the order row at moment of creation.

### 3.2 Concurrency-Safe Coupon System (`CouponService`)
- Supports `percentage` discounts (with optional `max_discount_amount` caps) and `fixed` deductions.
- Enforces strict criteria: validity date ranges, minimum order subtotal requirements, global usage limits, per-student limits, and first-purchase-only checks.
- Uses `DB::transaction()` and pessimistic row-locking (`lockForUpdate()`) to prevent race conditions during high-volume flash promotions.

### 3.3 Course Bundles & Ownership Deduction (`OrderAndEnrollmentService`)
- Supports two bundle ownership policies:
  1. `full_bundle_price`: Student pays the full bundle fee regardless of existing course enrollments.
  2. `exclude_owned_courses`: Inspects student profile enrollments and automatically deducts the individual list price of already-owned courses from the checkout total.
- **Duplicate Protection:** On purchase confirmation, newly acquired bundle courses are enrolled without duplicating or resetting existing course progress.

### 3.4 Razorpay Online Gateway Integration (`RazorpayPaymentService`)
- Standard server-side Razorpay order creation in native paise / subunit amounts.
- Strict HMAC-SHA256 signature verification for client checkout callbacks (`hash_hmac('sha256', order_id + '|' + payment_id, secret)`).
- Webhook endpoint at `/api/webhooks/razorpay` with signature verification (`X-Razorpay-Signature`) and built-in transaction idempotency checking.
- Mock sandbox auto-settlement mode available when test credentials are used.

### 3.5 Direct Admin Assignment & Revenue Integrity
- Administrators can assign courses or bundles directly via `/admin/orders` through payment methods: `cash`, `bank_transfer`, `complimentary`, or `scholarship`.
- **Revenue Separation Principle:** `cash` and `bank_transfer` update `paid_amount` and append a transaction record to collected cash revenue. `complimentary` and `scholarship` set `payment_status = 'complimentary'`, zeroing outstanding balances while recording \$0.00 in cash collected.

### 3.6 Special 60% Completion Installment Plan (`InstallmentMilestoneService`)
- Implements milestone-conditioned payment schedules.
- **Access Rule:** Under `immediate_on_deposit`, paying the initial deposit (e.g. 50%) immediately activates the student's enrollment.
- **Authoritative Progress:** Queries `LearningProgressService::getCourseProgressSummary()` for authoritative completion percentages.
- **Boundary Precision:**
  - `<60.00%` (e.g. 50% or 59%): Installment remains `upcoming` and inactive.
  - `>=60.00%` (e.g. 60.00% or 61%): Installment transitions to `due`, sets `milestone_triggered_at`, calculates `due_date` using configured grace period days, and dispatches in-app notification.
  - Subsequent evaluations are idempotent and will not re-trigger.

### 3.7 Installment Scheduler Command (`commerce:process-installments`)
- Artisan console command: `php artisan commerce:process-installments`.
- Evaluates pending student progress across active milestone installment orders.
- Flags overdue installments past their grace period (`status = 'overdue'`).

---

## 4. UI & Livewire Component Architecture

| Component | Route | Audience | Key Capabilities |
|---|---|---|---|
| `PublicBundlesIndex` | `/bundles` | Public | Udemy-inspired course bundle cards, savings badges, course inclusion pill list, currency switcher |
| `PublicBundleDetail` | `/bundles/{slug}` | Public | Syllabus breakdown of all included courses, trainer bios, savings calculator, checkout CTA |
| `CheckoutComponent` | `/checkout/{type}/{id}` | Public/Student | Order summary, coupon code validation, full vs installment radio toggles, Razorpay modal bridge |
| `StudentOrdersIndex` | `/student/orders` | Student | Payment history, active installment schedules, milestone progress visualizers, installment payment button |
| `StudentOrderDetail` | `/student/orders/{order}` | Student | Printable receipt / invoice with tax breakdown and transaction reference log |
| `CourseBundlesManager` | `/admin/course-bundles` | Admin | Create/edit bundles, attach courses with ordering, set ownership policy, toggle coupons |
| `CouponManager` | `/admin/coupons` | Admin | Coupon code generation, percentage/fixed rates, redemption caps, student usage auditing |
| `OrdersManager` | `/admin/orders` | Admin | Complete order ledger, search/filter, manual assignment modal (`cash`, `scholarship`, etc.) |
| `CommerceReportsManager` | `/admin/reports/commerce` | Admin | Net revenue, pending installments, gateway breakdown, secure CSV export with formula escaping |

---

## 5. Multi-Engine Test Suite Results

A dedicated multi-engine test suite (`tests/Feature/Commerce/CompleteCommerceAndInstallmentsTest.php`) was executed across both **SQLite** and **MySQL 8.4 InnoDB**.

```
   PASS  Tests\Feature\Commerce\CompleteCommerceAndInstallmentsTest
  ✓ currency and pricing resolution with data set "sqlite"                                           0.08s  
  ✓ currency and pricing resolution with data set "mysql"                                            0.13s  
  ✓ course bundles creation and ownership deduction policy with data set "sqlite"                   0.12s  
  ✓ course bundles creation and ownership deduction policy with data set "mysql"                    0.16s  
  ✓ discount coupons validation and concurrency safe redemption with data set "sqlite"              0.09s  
  ✓ discount coupons validation and concurrency safe redemption with data set "mysql"               0.14s  
  ✓ direct admin manual assignment offline and complimentary with data set "sqlite"                 0.11s  
  ✓ direct admin manual assignment offline and complimentary with data set "mysql"                  0.17s  
  ✓ razorpay signature verification and webhooks with data set "sqlite"                             0.10s  
  ✓ razorpay signature verification and webhooks with data set "mysql"                              0.15s  
  ✓ special 60 percent completion milestone installment plan with data set "sqlite"                 0.16s  
  ✓ special 60 percent completion milestone installment plan with data set "mysql"                  0.22s  
  ✓ livewire ui components rendering and actions with data set "sqlite"                             0.24s  
  ✓ livewire ui components rendering and actions with data set "mysql"                              0.35s  

  Tests:    14 passed (158 assertions)
  Duration: 4.90s
```

---

## 6. Headless Browser QA Verification Matrix

The test harness executed end-to-end browser walkthroughs capturing 12 comprehensive screenshots across all device viewports:

| # | Screenshot Artifact Name | Viewport | Target Interface | Verification Outcome |
|---|---|---|---|---|
| 01 | `phase12_6_01_desktop_1440_public_bundles_index.png` | 1440 × 900 (Desktop) | `/bundles` | Verified bundle grid, pricing cards, savings chips, and currency switcher |
| 02 | `phase12_6_02_desktop_1440_public_bundle_detail.png` | 1440 × 900 (Desktop) | `/bundles/ultimate-seo-bootcamp` | Verified curriculum listing of all attached courses and enroll CTA |
| 03 | `phase12_6_03_desktop_1440_checkout_page.png` | 1440 × 900 (Desktop) | `/checkout/bundle/1` | Verified price breakdown, coupon form, and installment selection radio |
| 04 | `phase12_6_04_desktop_1440_admin_course_bundles.png` | 1440 × 900 (Desktop) | `/admin/course-bundles` | Verified bundle creation form, course selector, and active bundles table |
| 05 | `phase12_6_05_desktop_1440_admin_discount_coupons.png` | 1440 × 900 (Desktop) | `/admin/coupons` | Verified coupon creator, percentage/fixed fields, and usage count auditing |
| 06 | `phase12_6_06_desktop_1440_admin_orders_manager.png` | 1440 × 900 (Desktop) | `/admin/orders` | Verified orders ledger with payment status, balances, and filter buttons |
| 07 | `phase12_6_07_desktop_1440_admin_direct_assignment_modal.png` | 1440 × 900 (Desktop) | `/admin/orders` (Modal) | Verified offline cash and scholarship assignment dialog with reason notes |
| 08 | `phase12_6_08_desktop_1440_admin_commerce_reports.png` | 1440 × 900 (Desktop) | `/admin/reports/commerce` | Verified revenue KPI cards, installment status charts, and CSV download |
| 09 | `phase12_6_09_laptop_1024_student_orders_dashboard.png` | 1024 × 768 (Laptop) | `/student/orders` | Verified student orders list, installment progress bars, and pay buttons |
| 10 | `phase12_6_10_tablet_768_student_order_receipt.png` | 768 × 1024 (Tablet) | `/student/orders/1` | Verified order receipt layout with transaction ID and installment breakdowns |
| 11 | `phase12_6_11_mobile_390_public_bundle_catalogue.png` | 390 × 844 (Mobile) | `/bundles` | Verified stacked responsive cards and mobile layout consistency |
| 12 | `phase12_6_12_mobile_390_checkout_responsive.png` | 390 × 844 (Mobile) | `/checkout/bundle/1` | Verified responsive mobile checkout form and touch-friendly CTA buttons |

---

## 7. Compliance Checklist

- [x] Multi-tenant academy scoping maintained across all 8 tables.
- [x] Strict preservation of 4 user roles (`admin`, `trainer`, `support_staff`, `student`).
- [x] Financial status (`paid`, `partially_paid`, `complimentary`) strictly isolated from academic completion (`enrolled`, `in_progress`, `completed`).
- [x] Complimentary and scholarship enrollments contribute \$0.00 to verified cash revenue.
- [x] Concurrency-safe coupon usage updates with database row locking.
- [x] Razorpay HMAC-SHA256 signature verification implemented for checkout and webhooks.
- [x] CSV export protected against spreadsheet formula injection (`=`, `+`, `-`, `@`).
- [x] Multi-engine test suite passing 100% on SQLite and MySQL 8.4 InnoDB.
- [x] Headless browser QA completed with all 12 responsive screenshots captured.
- [x] Strict adherence to zero-commit / zero-release archive constraints.
