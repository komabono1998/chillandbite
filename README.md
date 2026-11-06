# Chill & Bite MEATINGS

PHP 8 + MySQL event, booking, tickets, DJs and advertising platform (no framework, no Composer).

## Local setup (XAMPP)
1. Start Apache and MySQL, keep the project in `htdocs/chillandbite`.
2. `php database/install.php --sample` creates `.env`, the database, tables, categories and a first admin (password printed once). Omit `--sample` for an empty site.
3. Public site: http://localhost/chillandbite/ · Admin: http://localhost/chillandbite/admin

## Deploying to a live server
1. **Server**: Apache with `mod_rewrite` and `.htaccess` enabled, PHP 8.1+ with `pdo_mysql`, `fileinfo`, `mbstring`, and a MySQL/MariaDB database.
2. **Upload** the project folder. Copy `.env.example` to `.env` and set `DB_*`, `APP_DEBUG=0`, `MAIL_ENABLED=1` (if the server can send email), `MAIL_FROM`, and optionally `ADMIN_EMAIL` / `ADMIN_PASSWORD` for the first admin.
3. **Install** with `php database/install.php` (no `--sample`). It creates the tables and the first admin. Running it again is safe and applies upgrades.
4. **Sign in** at `/admin`, choose a new password, then create your team under **Users & roles** (role cards, generated passwords, or add several people at once).
5. **Fill in** Settings (phone, WhatsApp, venue), then edit text under **Pages & content** and pictures under **Pictures**. Add events, DJs and videos.
6. **Back up** the database and the `uploads/` folder, plus `storage/app.key` (it signs ticket seals; losing it changes every ticket seal and serial). Back up `.env` too.
7. Upload limits for mixes and MP4 videos are set in `.htaccess` (300MB). Some hosts cap uploads lower; raise `upload_max_filesize` and `post_max_size` in the hosting panel if needed.
8. Serve the site over HTTPS. Folders `app/`, `database/`, `storage/` and files like `.env` are blocked by `.htaccess`; keep it in place.

## Structure
- `index.php` front controller and route table
- `app/bootstrap.php` config, DB, CSRF, auth/roles, rate limiting, uploads, helpers
- `app/controllers/` site, booking, tickets, admin, admin_content, djs, pictures
- `app/views/` public (`site/`) and admin (`admin/`) templates
- `database/schema.sql`, `database/install.php`

## Security notes
Prepared statements everywhere, escaped output, CSRF on every POST, bcrypt passwords, login/form/booking rate limits, server-side role checks (`PERMS` in bootstrap), capacity re-checked in a DB transaction at confirmation, upload MIME validation, ticket seals signed with a server key, audit log. New accounts must choose their own password at first sign-in. Set `APP_DEBUG=0` in production.

## Admin dashboard
Sign in at `/admin`. The sidebar is grouped (Overview, Website, Operations, Insights, Administration) and only shows what your role may use.

- **Pages & content** (`/admin/pages`) and **Pictures** (`/admin/pictures`): edit every public page's text and replace any picture by upload or from the gallery. Signed-in staff also see a quick-edit bar and picture pencils on the public site.
- **Reservations, Tickets, Check-in**: approve bookings, email or WhatsApp tickets, update, withdraw and reinstate them; the door screen shows the seal, serial and live code.
- **DJs & mixes**, **Comedy videos** (MP4 or YouTube), **Gallery**, **Sponsors & ads**, **Partner applications**, **Inquiries**.
- **Reports & exports** (`/admin/export/{dataset}?format=pdf|docx|xlsx|csv`): reservations, payments, guest list, events, inquiries and partner applications.
- **Roles** (`app/bootstrap.php`, `ROLES` / `PERMS`): System Administrator, Event Manager, Ticketing & Reservations Officer, Finance Officer, Door / Gate Staff, Content Editor, Marketing & Advertising Manager, DJ & Music Manager. The matrix is shown under Admin > Users & roles.

After pulling changes, run `php database/install.php` once to apply new tables and columns.
