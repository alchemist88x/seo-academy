# SEO Academy v5.6.0 — Database Engine Selection & Validation Fix Report

**Date:** 2026-11-16  
**Project:** `C:\laragon\www\seo-academy`  
**Milestone:** v5.6.0 Patch Release  
**Status:** Resolved, Automated & Real-Browser Verified  

---

## 1. Executive Summary & Root-Cause Analysis

### Reported Problem
During Step 4 of the SEO Academy Installation Wizard (`/install`):
1. Both SQLite and MySQL database selection cards were displayed.
2. Clicking either card failed to register the selection or update the active engine state.
3. The installer displayed the validation error: `The driver field is required.`
4. The SQLite configuration panel appeared by default, but the underlying driver property was empty or unset.
5. Clicking `"Save & Proceed to Migrations"` failed validation, completely blocking the installation flow.

### Root-Cause Investigation
A comprehensive trace across `InstallationWizard.php`, `InstallController.php`, `resources/views/install/steps/database.blade.php`, and `resources/views/livewire/installer/steps/database_selection.blade.php` revealed three compounding root causes:

1. **Card Elements Outside the HTML Form in Staged Views:**  
   In `resources/views/install/steps/database.blade.php`, the engine selection cards were rendered as separate `<button type="button" @click="selectDriver(...)">` elements positioned **outside** the `<form method="POST">` element. The form only contained `<input type="hidden" name="driver" :value="driver">` without a hard default HTML `value="sqlite"`. If JavaScript/Alpine had an initialization delay or DOM disconnect, native form submission sent `driver=""`. `InstallController::storeDatabase` subsequently rejected the empty payload with `'driver' => ['required']`, returning the exact error: `"The driver field is required."`
2. **Missing Validation & Reactive Property Hooks in Livewire Component:**  
   In `InstallationWizard.php`, `proceedFromDatabaseSelection()` lacked explicit `$this->validate(['driver' => ...])` logic. Furthermore, there was no `updatedDriver()` lifecycle hook, and `selectDriver()` did not clear error bags or immediately synchronize with `InstallationStateService`.
3. **Lack of Semantic Native Inputs in Wizard Views:**  
   Both the Livewire Step 4 view (`database_selection.blade.php`) and the controller view lacked genuine semantic radio inputs (`<input type="radio" name="driver" ...>`). Relying exclusively on JavaScript click handlers caused browser accessibility and input-synchronization failures whenever events failed to propagate.

---

## 2. Key Architecture & Code Fixes

### A. Livewire Wizard Component (`app/Livewire/Installer/InstallationWizard.php`)
- **Strict Driver Clamping:** Guaranteed that `$this->driver` is always initialized to `'sqlite'` or `'mysql'`, never `null` or empty string.
- **`updatedDriver` Lifecycle Hook:** Added Livewire property hook to catch both `wire:model.live="driver"` changes and direct method invocations.
- **Enhanced `selectDriver`:**
  - Validates driver against allowed engines (`in:sqlite,mysql`).
  - Clears previous error bags and resets `$connectionVerified` and `$testResult`.
  - Immediately persists selection to `InstallationStateService`.
- **Strict Step 4 Validation:** Added explicit validation in `proceedFromDatabaseSelection()`:
  ```php
  $this->validate([
      'driver' => ['required', 'string', 'in:sqlite,mysql'],
  ], [
      'driver.required' => 'The driver field is required.',
      'driver.in' => 'The selected database driver must be either SQLite or MySQL.',
  ]);
  ```
- **Step 5 Driver Validation:** Enforced driver validation before checking engine-specific configuration fields in `saveDatabaseConfiguration()`.

### B. Step 4 Database Selection View (`resources/views/livewire/installer/steps/database_selection.blade.php`)
- **Accessible Radio Controls:** Replaced unbacked buttons with accessible `<label>` wrappers enclosing `<input type="radio" name="driver" value="sqlite|mysql" wire:model.live="driver">`.
- **Prominent Error Banner:** Added `@error('driver')` banner alerting the user if an invalid engine is submitted.
- **Dynamic Action Button:** Button label dynamically reflects the selected engine (`"Configure SQLite →"` vs `"Configure MySQL →"`).

### C. Step 5 Database Configuration View (`resources/views/livewire/installer/steps/database_configuration.blade.php`)
- **Instant Reactive Engine Switcher:** Introduced a top engine switcher bar (`[SQLite 3] [MySQL 8.4+]`) allowing customers to switch engines instantly on Step 5 without page reload.
- **Conditional Rendering:**
  - When `$driver === 'sqlite'`, renders SQLite file location (`storage/app/private/database.sqlite`).
  - When `$driver === 'mysql'`, renders MySQL Host, Port, Database, Username, and Password fields.

### D. Staged Controller Step (`resources/views/install/steps/database.blade.php` & `InstallController.php`)
- **Cards Placed Inside Form:** Relocated selection cards inside the `<form method="POST">` element.
- **Native Radio Inputs:** Enclosed `<input type="radio" name="driver" value="sqlite" ...>` and `value="mysql"` with explicit checked attributes.
- **Custom Validation Messages:** Added user-friendly validation messages to `InstallController::storeDatabase`.

---

## 3. Automated Test Coverage & Verification

### Test Suites Executed
All PHPUnit tests passed cleanly under PHP 8.3:

| Test Suite | Total Tests | Passed | Assertions | Status |
|---|---|---|---|---|
| `LivewireInstallationWizardTest` | 8 | 8 | 70 | **PASS** |
| `StagedInstallationTest` | 6 | 6 | 77 | **PASS** |
| `InstallSafetyTest` | 9 | 9 | 49 | **PASS** |
| `InstallWizardTest` | 12 | 12 | 91 | **PASS** |
| **Total** | **35** | **35** | **287** | **100% PASS** |

### Targeted Feature Tests Added
1. `test_database_engine_selection_and_persistence`: Validates toggling between SQLite and MySQL and verifies state persistence in `InstallationStateService`.
2. `test_invalid_driver_selection_rejected_with_validation_error`: Proves invalid drivers (e.g. `'oracle'`) are rejected and produce `'driver'` validation errors.
3. `test_step_5_immediate_engine_toggle_without_page_reload`: Proves switching between engines on Step 5 toggles inputs dynamically without page reload.
4. `test_database_store_validates_driver_and_mysql_fields`: Proves missing driver, invalid driver, and incomplete MySQL payloads are caught by validator.

---

## 4. Real Chrome Browser Automation Verification

Using Google Chrome automation via the dedicated browser subagent, live browser tests were executed against `http://127.0.0.1:8099/install?step=4`:

1. **Step 4 Initial Rendering:**
   - Heading `"Step 4 — Database Selection"` confirmed.
   - SQLite and MySQL cards rendered. SQLite selected by default (radio checked, green border, `"Selected"` badge, Active Choice: `"SQLITE"`).
2. **Test Case A (MySQL Selection & Advancement):**
   - Clicked MySQL card.
   - Livewire immediately applied primary border and `"Selected"` badge to MySQL card; Active Choice updated to `"MYSQL"`.
   - Primary action button updated to `"Configure MySQL →"`.
   - Advanced to Step 5: MySQL fields (Host, Port, Database, Username, Password) displayed cleanly.
   - **Zero validation errors displayed.**
3. **Test Case B (Step 5 Reactive Switcher):**
   - Clicked `[SQLite 3]` on the Step 5 engine switcher bar.
   - View instantly switched to SQLite Database Configuration and hid MySQL fields without page reload.
   - Clicked `[MySQL 8.4+]`.
   - View instantly restored MySQL connection credentials without page reload.
4. **Test Case C (Navigation & SQLite Connection Verification):**
   - Clicked `"← Change Database Engine"`. Returned to Step 4 with MySQL retained as active.
   - Clicked SQLite card. Advanced to Step 5 and then Step 6 (`"Step 6 — Database Connection Verification"`).
   - Clicked `"Test Database Connection"`.
   - Verified animated loading state followed by green verified alert:
     - `"Connection Verified"`
     - `"SQLite connection successful and private storage directory is writable."`
     - Badge: `"VERIFIED"`.
5. **Test Case D (Refresh Persistence):**
   - Refreshed browser on Step 6.
   - URL remained `/install?step=6`, target engine remained `SQLITE`, connection summary remained intact.

---

## 5. Development Database Integrity & Safety Verification

All test sandboxes were cleaned up and development environment state was verified:
- Active development database restored at `storage/app/private/database.sqlite`.
- Account verification: `App\Models\User::count()` confirmed **5 demo accounts intact**.
- Production lockfile restored at `storage/app/private/installed.lock`.
- Code formatted with Laravel Pint: **0 style violations**.
- Git tree: **Zero commits created, working copy preserved**.
