# Operational Guide — Centralized Audit Logging

SEO Academy maintains an immutable audit log of critical operational actions.

## 1. Audit Viewer
- Accessible to administrators at `/admin/audit-logs`.
- Displays:
  - Timestamp (ISO 8601 & Local)
  - Actor (Name, Email, Role)
  - Action (e.g. `enrollment.status_changed`, `ticket.assigned`, `backup.created`)
  - Target entity type and ID
  - Client IP address and User Agent
  - State delta properties
- Filterable by Action, Module, Actor, and Date Range.

## 2. Redaction & Privacy
- Sensitive keys are automatically redacted recursively during recording:
  - `password`, `password_confirmation`, `secret`, `token`, `api_key`, `smtp_password`, `app_key`, `remember_token`, `authorization`.
  - Redacted values are stored as `[REDACTED]`.

## 3. Account Deletion Durability
- If a staff member or administrator account is deleted, foreign keys trigger `SET NULL` on `audit_log.user_id`.
- The historical actor name and email remain permanently preserved in the `_actor` snapshot within the audit log properties.
- Regular users, trainers, and support staff cannot view, edit, or delete audit records.

