# Chill & Bite MEATINGS

PHP 8 + MySQL event, booking, news and advertising platform (no framework, no Composer).

## Local setup (XAMPP)
1. Start Apache and MySQL, keep the project in `htdocs/chillandbite`.
2. `php database/install.php --sample` creates `.env`, the database, tables, categories and a first admin (password printed once). Omit `--sample` for an empty site.
3. Public site: http://localhost/chillandbite/ · Admin: http://localhost/chillandbite/admin

## Structure
- `index.php` front controller and route table
- `app/bootstrap.php` config, DB, CSRF, auth/roles, rate limiting, uploads, helpers
- `app/controllers/` site, booking, admin, admin_content
- `app/views/` public (`site/`) and admin (`admin/`) templates
- `database/schema.sql`, `database/install.php`

## Security notes
Prepared statements everywhere, escaped output, CSRF on every POST, bcrypt passwords, login/form/booking rate limits, server-side role checks (`PERMS` in bootstrap), capacity re-checked in a DB transaction at confirmation, upload MIME validation, audit log. Set `APP_DEBUG=0` in production.

## Roadmap
Phase 3: Pesapal payments (keys go in `.env`), WhatsApp notifications, ad campaign management, reports. Phase 4: backups, analytics, deployment.

## Admin dashboard
Sign in at `/admin`. The sidebar is grouped (Overview, Website, Operations, Insights, Administration) and only shows what your role may use.

- **Pages & content** (`/admin/pages`): every public page, split into sections and fields (text, buttons, lists, photos). Defaults live in `app/content.php`; saved edits are stored in `site_content`. Empty or "reset" fields fall back to the default text. Per-page role access is set in the registry (`roles`).
- **Reports & exports** (`/admin/reports`, `/admin/export/{dataset}?format=pdf|docx|xlsx|csv`): reservations, payments ledger, guest list, event summary, inquiries and partner applications. Files are generated in pure PHP (`app/export.php`, no extra libraries).
- **Check-in desk** (`/admin/checkin`): search guests or scan QR tickets at the gate.
- **Roles** (`app/bootstrap.php`, `ROLES` / `PERMS`): System Administrator, Event Manager, Content Editor, Marketing & Advertising Manager, Finance Officer, Door / Gate Staff. Every request is checked on the server; the matrix is shown under Admin > Users & roles.

After pulling these changes, run `php database/install.php` once to add the new tables and roles.
