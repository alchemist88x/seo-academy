# Operational Guide — Notifications

SEO Academy provides a centralized in-app and email notification system.

## 1. Notification Center
- Available at `/notifications` to all authenticated users.
- Scoped to the current user's direct notifications and their assigned role audience.
- Unread badge displayed on top navigation bell.
- Actions: Mark individual notification as read, Mark all as read, Category filter (`Enrollments`, `Support`, `Courses`, `System`), Full-text search.

## 2. Notification Preferences
- Available at `/notifications/preferences`.
- Users can customize delivery channels (`In-App` and `Email`) across categories:
  - Enrollments & Access
  - Support & Help Desk
  - Courses & Training
- Security-critical account alerts are permanently locked on to preserve account security.

## 3. Deduplication Policy
- Automatic deduplication prevents duplicate notifications within a 300-second window for identical titles and categories sent to the same recipient.

## 4. SMTP Outbound Email Delivery
- Emails are dispatched using the configured SMTP settings in `/admin/system/settings`.
- If SMTP connection fails, errors are safely logged without leaking passwords and without interrupting the user's primary transaction.

