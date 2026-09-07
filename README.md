# Meter Watch — TNB Electricity Meter Monitoring System

A PHP + MySQL web app for daily TNB meter-reading submission, with two
switchable submission modes and two automatic WhatsApp alerts.

- **Version 1** (`user/submit-v1.php`) — enter the reading, take or upload a
  meter photo, submit.
- **Version 2** (`user/submit-v2.php`) — same, but the photo must be
  captured **live** through the browser camera. There is no file/gallery
  picker anywhere in the flow, the reading field is locked until a live
  photo exists, and the server rejects any submission missing one.

Both automatic alerts are implemented:
1. **Over-usage alert** — compares each new reading to the previous day's
   reading for the same meter; if the difference exceeds Admin's daily
   usage limit, an alert is raised.
2. **Missing-reading reminder** — checked either manually from the Admin
   dashboard, or automatically via the cron script, for any meter with no
   reading recorded past the configured deadline.

## Folder structure

```
tnb-meter-system/
├── admin/            Admin-only pages (dashboard, settings, meters, alerts, readings)
├── assets/            css/ and js/ (includes the Version 2 live-camera capture script)
├── auth/              login.php, logout.php
├── config/            database.php (DB credentials), config.php (timezone, upload paths)
├── cron/              check-missing-readings.php — run on a schedule
├── database/          schema.sql — tables + demo seed data
├── includes/          shared header/footer, auth-check.php guard, functions.php
├── user/              Field User pages (submit-v1, submit-v2, history)
├── uploads/            meter_photos/ — saved meter photos (created automatically)
├── index.php          Entry point — redirects to login or the right dashboard
├── unauthorized.php   Shown when a role tries to open a page it can't access
└── .htaccess          Directory listing off, config/ and database/ blocked from the web
```

## Setup

1. **Create the database.**
   ```bash
   mysql -u root -p -e "CREATE DATABASE meter_watch"
   mysql -u root -p meter_watch < database/schema.sql
   ```
   This also seeds two demo accounts and two demo meters.

2. **Set your DB credentials** in `config/database.php`
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).

3. **Make `uploads/meter_photos/` writable** by the web server user:
   ```bash
   chmod -R 775 uploads/meter_photos
   ```

4. **Point your web server's document root at this folder**, or run the
   PHP built-in server for local testing:
   ```bash
   php -S localhost:8000
   ```
   then open `http://localhost:8000`.

5. **Log in** with a demo account:
   | Username | Password | Role |
   |---|---|---|
   | `admin` | `password123` | Admin |
   | `user1` | `password123` | Field User |

   Change or remove these before any real deployment.

## Trial note

Per the brief, the photo-capture steps accept a photo of **any paper with a
written number** in place of a real TNB meter — useful for testing without
access to an actual meter.

## Scheduling the missing-reading check

`cron/check-missing-readings.php` is a CLI script meant to run once a day,
shortly after the submission deadline set in Admin → Settings:

```cron
5 20 * * * /usr/bin/php /path/to/tnb-meter-system/cron/check-missing-readings.php
```

It's also runnable manually from Admin → Alerts → "Check now" for testing.

## WhatsApp alerts — simulated, not real

Both alert rules are fully implemented and logged to the `alerts` table,
including a "sent" flag — but actual WhatsApp delivery is **simulated**
(logged via `error_log()`), because real delivery requires a server-side
integration such as the Meta WhatsApp Business Cloud API or Twilio, using a
private API credential.

To wire up real sending, open `includes/functions.php` and replace the body
of `send_whatsapp_message()` with an HTTP call to your provider — the
commented example inside that function shows the shape of a Meta Cloud API
request.

## Security notes for production use

- Replace the demo accounts and passwords immediately.
- Serve the app over HTTPS — Version 2's camera capture requires a secure
  context in most browsers anyway.
- `config/` and `database/` are blocked from direct web access via
  `.htaccess`; keep that in place (or the equivalent Nginx rule) in
  production.
- `uploads/meter_photos/.htaccess` prevents PHP execution from inside the
  uploads folder as a defense-in-depth measure against malicious file
  uploads.
