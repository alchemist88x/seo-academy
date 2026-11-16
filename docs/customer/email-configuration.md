# SEO Academy — Email & SMTP Configuration Guide

SEO Academy dispatches transactional emails for user account invitations, student enrollment confirmations, assignment grading feedback, password resets, and certificate notifications.

---

## 1. Configuring Email in the Admin UI

The easiest way to configure email is through the administrative portal:
1. Log in as an Administrator.
2. Navigate to **Admin Dashboard -> Academy Settings** (`/admin/academy/settings`).
3. Scroll to **Outgoing Mail (SMTP) Settings**.
4. Enter your host, port, username, password, and encryption protocol.
5. Click **Send Test Email** to verify connectivity before saving.

Credentials configured in the UI are securely encrypted using your application's AES-256 key (`APP_KEY`) before being saved to the database.

---

## 2. Configuring Email in `.env`

Alternatively, you can configure email globally in your `.env` file:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@your-domain.com
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@your-academy.com"
MAIL_FROM_NAME="SEO Academy"
```

---

## 3. Configuration Profiles for Popular Providers

### SendGrid
```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.your_api_key_here
MAIL_ENCRYPTION=tls
```

### Mailgun
```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@mg.yourdomain.com
MAIL_PASSWORD=your_mailgun_smtp_password
MAIL_ENCRYPTION=tls
```

### Amazon SES
```dotenv
MAIL_MAILER=smtp
MAIL_HOST=email-smtp.us-east-1.amazonaws.com
MAIL_PORT=587
MAIL_USERNAME=your_ses_smtp_username
MAIL_PASSWORD=your_ses_smtp_password
MAIL_ENCRYPTION=tls
```

### Google Workspace / Gmail Relay
```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-account@yourdomain.com
MAIL_PASSWORD=your-google-app-password # Use 16-character App Password, not main password
MAIL_ENCRYPTION=tls
```

---

## 4. Troubleshooting Mail Delivery

1. **Connection Timeout (Error 110):** Often caused by hosting providers blocking outbound port 25. Always use port `587` with TLS or port `465` with SSL.
2. **Authentication Failure (Error 535):** Verify username and password. When using Google Workspace, an **App Password** must be generated if Two-Factor Authentication is enabled.
3. **Log Mailer for Testing:** In local or staging environments, set `MAIL_MAILER=log`. All outgoing emails will be written to `storage/logs/laravel.log` rather than attempting external SMTP delivery.
