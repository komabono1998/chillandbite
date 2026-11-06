# Deploying Chill & Bite MEATINGS on Hostinger

This guide takes the whole site (public website, admin, tickets, DJs, videos, emails) from your computer to a live Hostinger website. Menu names in hPanel change from time to time, so look for the closest match if a name differs.

**Time needed:** about 45 minutes the first time.

---

## 0. What you need

- A Hostinger **web hosting** plan (Premium, Business or Cloud are best; they include SSH, which makes step 6 easier). Plain "Single" plans also work using the cron method in step 6.
- A **domain** connected to the hosting (buy one from Hostinger or point your existing domain's nameservers to Hostinger).
- Your code on GitHub (`komabono1998/chillandbite`) or as a ZIP of the project folder.
- Your **WhatsApp number, venue and contact details** ready for the Settings page.

The app needs PHP 8.1 or newer (8.2 recommended) with the `pdo_mysql`, `mbstring` and `fileinfo` extensions. These are on by default at Hostinger.

---

## 1. Create the database

1. hPanel → **Websites → Manage → Databases → Management**.
2. Under **Create a new MySQL database and database user**, enter a name, a username and a strong password. Click **Create**.
3. Write down these four values. Hostinger adds a prefix such as `u123456789_`:
   - Database name: `u123456789_chill`
   - Database user: `u123456789_chilluser`
   - Password: the one you chose
   - Host: `localhost`

---

## 2. Set the PHP version and limits

1. hPanel → **Advanced → PHP Configuration**.
2. **PHP version:** choose **8.2** (or 8.1+).
3. **PHP extensions:** make sure `pdo_mysql`, `mbstring` and `fileinfo` are ticked.
4. **PHP options** (Hostinger overrides `.htaccess` here, so set them in the panel too):
   - `upload_max_filesize` = `300M`
   - `post_max_size` = `310M`
   - `max_execution_time` = `600`
   - `memory_limit` = `256M`

   These allow DJ mixes (up to 110MB) and MP4 videos (up to 300MB). Some plans cap these numbers lower. Use the highest value allowed.

---

## 3. Put the code on the server

Pick **one** method.

### Method A: Git (recommended, easy updates)

1. hPanel → **Advanced → Git**.
2. Repository: `https://github.com/komabono1998/chillandbite.git` (for a private repo, add the SSH key Hostinger shows to GitHub under *Settings → Deploy keys*, and use the SSH address `git@github.com:komabono1998/chillandbite.git`).
3. Branch: `main`. Install path: **leave empty** so it deploys into `public_html`. (The folder must be empty first. Delete Hostinger's default placeholder file `default.php` if it is there.)
4. Click **Create**, then **Deploy**.
5. For automatic deploys on every push, copy the **webhook URL** into GitHub → *Settings → Webhooks*.

### Method B: Upload a ZIP

1. On your computer, zip the **contents** of the project folder (not the folder itself). Include the hidden `.htaccess` file. Leave out `.env`, `.git`, `storage/app.key` and the `uploads/` folder.
2. hPanel → **Files → File Manager → public_html**. Delete the default placeholder files.
3. Click **Upload**, choose the ZIP, then right-click it and **Extract** into `public_html`.
4. Check that `public_html/.htaccess` and `public_html/index.php` exist. In File Manager, open **Settings** and tick **Show hidden files** to see `.htaccess`.

**Result:** `public_html/index.php`, `public_html/app/`, `public_html/assets/`, `public_html/database/`, and so on.

---

## 4. Create the `.env` file

The `.env` file holds your secrets and is **never** uploaded to GitHub.

1. In File Manager, inside `public_html`, create a new file called `.env`.
2. Paste this and fill in your values:

```
APP_DEBUG=0
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u123456789_chill
DB_USER=u123456789_chilluser
DB_PASS=your-database-password

MAIL_ENABLED=1
MAIL_FROM=no-reply@yourdomain.com

ADMIN_EMAIL=you@yourdomain.com
ADMIN_PASSWORD=choose-a-long-password-of-10-plus-characters
```

- `MAIL_FROM` must be an address on **your domain**, otherwise emails land in spam. Create it in hPanel → **Emails → Email Accounts** (a free mailbox comes with most plans).
- `ADMIN_EMAIL` and `ADMIN_PASSWORD` become the first administrator. Leave `ADMIN_PASSWORD` empty if you prefer a generated one (it is printed once, see step 5).
- Right-click `.env` → **Permissions** → set to `640` or `600`.

---

## 5. Create the tables and your first admin

The installer builds all tables, settings, categories and your administrator. It is safe to run again later.

### If you have SSH (Premium, Business, Cloud)

hPanel → **Advanced → SSH Access** shows the command to connect. Then:

```bash
cd ~/domains/yourdomain.com/public_html
php database/install.php
```

(Run it **without** `--sample`. Sample mode adds demo events.)

### If you have no SSH (cron method)

1. hPanel → **Advanced → Cron Jobs → Create**.
2. Choose **Custom**, set it to run **every minute**, and enter the command (adjust the path to match yours; File Manager shows it at the top):

```
php /home/u123456789/domains/yourdomain.com/public_html/database/install.php
```

3. Wait two minutes, then **delete the cron job** so it does not run again.
4. Cron output goes to your Hostinger email. Because you set `ADMIN_PASSWORD` in `.env`, no password is ever printed.

You should see `Schema applied` and `FIRST ADMIN ACCOUNT` in the output. The tables now exist.

**After installing:** edit `.env` and delete the `ADMIN_PASSWORD=` line.

---

## 6. Turn on HTTPS

1. hPanel → **Security → SSL**. Install the free SSL certificate for your domain.
2. Switch on **Force HTTPS** there.
3. Visit `https://yourdomain.com`. You should see the padlock. HTTPS is required so ticket QR codes, cookies and the phone camera work correctly.

---

## 7. First sign-in and setup

1. Open `https://yourdomain.com/admin` and sign in with `ADMIN_EMAIL` and your password.
2. **Admin → Settings:** enter your phone, WhatsApp, venue, tagline and announcement text.
3. **Admin → Users & roles:** create your team. Pick a role card, press create, and share the temporary password shown (it is shown once and they must change it at first sign-in). You can add several people at once.
4. **Pages & content / Pictures:** adjust any text and replace pictures. As an admin you also see an **Admin** bar on the live site with quick edit links.
5. **Events:** create your real events (Chill & Bite, Laugh with Owakabi). Set reservation options and table packages.
6. **DJs & mixes, Comedy videos, Gallery, Sponsors:** add your real content. Upload mixes and MP4 videos with the PHP limits from step 2.

---

## 8. Test everything on the live site

Do these on your phone, with real data, **before** announcing the site:

- [ ] Home page, About, Events, Podium DJs, Laugh with Owakabi, Gallery, Contact load with no errors.
- [ ] Reserve a place for an event. You should get a **booking code**, and an email if you entered one.
- [ ] In the admin, **approve** it. The guest's booking page should switch to **Get my ticket**.
- [ ] Open the ticket on the phone. Check the QR code, gold seal and live code.
- [ ] Scan the QR code with a second phone signed in as door staff. You should see **VALID TICKET**, then **ALREADY CHECKED IN** on a second scan.
- [ ] Email and WhatsApp buttons in **Admin → Tickets** work.
- [ ] Book a DJ and send a performance request. Both should arrive under **Inquiries**.
- [ ] Upload a small MP3 and a small MP4, play them, and test the **Download** buttons.
- [ ] Delete the test bookings afterwards (Admin → Reservations).

### Security check

Visit these addresses. Each must show **Forbidden (403)** or **Not Found**, never a file or code:

- `https://yourdomain.com/.env`
- `https://yourdomain.com/app/bootstrap.php`
- `https://yourdomain.com/database/schema.sql`
- `https://yourdomain.com/storage/app.key`

If any of them shows content, your `.htaccess` is missing. Re-upload it.

---

## 9. Moving your local data (optional)

Starting fresh on the live site is cleanest. If you want the DJ profiles, host profile and pictures you set up locally:

1. **Database:** open local phpMyAdmin → select `chillandbite` → **Export** the tables you want (for example `djs`, `dj_mixes`, `site_content`, `events`, `event_reservation_options`, `event_packages`, `settings`). Import them into the Hostinger database through hPanel → **Databases → phpMyAdmin → Import**. Do **not** import `users` unless you want your local admin and passwords.
2. **Files:** copy the local `uploads/` folder contents (`djs`, `site`, `posters`, `mixes`, `videos`, `gallery`) into `public_html/uploads/` on the server with File Manager.
3. Everything in the database uses relative paths, so it works on the new domain without edits.

---

## 10. Updating the site later

1. Push your changes to GitHub (`git push`). With the webhook the server updates itself. Otherwise open **Advanced → Git → Deploy**.
2. If the update changed the database (new tables or columns), sign in as admin and press **Settings → Update database** once (or run the installer again by SSH / cron).
3. Uploaded pictures, mixes, videos, `.env` and `storage/app.key` are not in Git, so deploys never overwrite them.

## 11. Backups (do this on a schedule)

Back up these four things regularly:

1. **The database:** hPanel → Databases → phpMyAdmin → **Export**.
2. **The `uploads/` folder** (DJ photos, posters, mixes, videos, ticket pictures).
3. **`storage/app.key`.** It signs the ticket seals. If it is lost, every ticket's gold seal and serial changes.
4. **`.env`.**

Hostinger's automatic **Backups** page (hPanel → Files → Backups) can also restore the whole site.

---

## 12. Troubleshooting

| Problem | What to do |
|---|---|
| **500 error right after upload** | Open `.htaccess` and delete the `<IfModule LiteSpeed>` block (use the panel's PHP options from step 2 instead). Then check `storage/logs/php-error.log`. |
| **Blank page or "Error" text** | Set `APP_DEBUG=1` in `.env` briefly to see the message, then set it back to `0`. Read `storage/logs/php-error.log`. |
| **"Access denied" or database error** | Re-check `DB_NAME`, `DB_USER`, `DB_PASS` (with the Hostinger prefix) and use `DB_HOST=localhost`. |
| **Inner pages show 404** | `.htaccess` is missing or hidden files were not uploaded. Re-upload it and confirm mod_rewrite is on (it is by default). |
| **Uploads fail or the page reloads empty** | The file is bigger than the PHP limit. Raise `upload_max_filesize` and `post_max_size` in PHP Configuration, or use a shorter file, or paste a SoundCloud/YouTube link. |
| **Emails not arriving** | Make sure `MAIL_ENABLED=1`, `MAIL_FROM` is a mailbox on your domain, and check spam. The last emails are also recorded in `storage/logs/mail.log`. |
| **Wrong times** | The site is fixed to East Africa Time (Uganda). Nothing to change. |
| **Ticket QR opens the wrong address** | The QR uses the address you visit. Always use your final HTTPS domain. |
| **Locked out of admin** | Use **Reset password** from another admin (Users & roles). If you are the only admin, set a new `ADMIN_PASSWORD` in `.env` and run the installer on an empty `users` table, or reset the hash in phpMyAdmin. |

---

## Quick checklist

- [ ] Database created, details noted
- [ ] PHP 8.2 and upload limits set
- [ ] Code deployed to `public_html` (with `.htaccess`)
- [ ] `.env` created (with `APP_DEBUG=0`)
- [ ] Installer run, `ADMIN_PASSWORD` line removed afterwards
- [ ] SSL on and Force HTTPS on
- [ ] Signed in, Settings filled, team created
- [ ] Full test on a phone, test bookings deleted
- [ ] Security URLs return 403
- [ ] Backup routine in place
