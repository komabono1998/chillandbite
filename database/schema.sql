-- Chill & Bite MEATINGS schema (MySQL 5.7+/MariaDB 10.4+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','event_manager','box_office','editor','marketing','dj_manager','finance','door','contributor') NOT NULL DEFAULT 'editor',
  must_change TINYINT(1) NOT NULL DEFAULT 0,
  organization VARCHAR(160) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(80) PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  short_desc VARCHAR(300) NOT NULL DEFAULT '',
  description TEXT NOT NULL,
  poster VARCHAR(255) NULL,
  venue VARCHAR(190) NOT NULL DEFAULT 'Revelation Cottages',
  location VARCHAR(190) NOT NULL DEFAULT 'Gulu City, Uganda',
  start_at DATETIME NOT NULL,
  end_at DATETIME NULL,
  status ENUM('draft','published','cancelled','archived') NOT NULL DEFAULT 'draft',
  capacity INT UNSIGNED NULL COMMENT 'NULL = unlimited',
  reservation_opens DATETIME NULL,
  reservation_closes DATETIME NULL,
  booking_mode ENUM('instant','approval') NOT NULL DEFAULT 'instant',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  video_url VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_events_start (start_at),
  KEY idx_events_status (status),
  CONSTRAINT fk_events_cat FOREIGN KEY (category_id) REFERENCES event_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Which reservation types are enabled for an event, and their per-guest price (0 = free)
CREATE TABLE IF NOT EXISTS event_reservation_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  type ENUM('individual','couple','group','corporate','vip','table') NOT NULL,
  price_per_guest DECIMAL(12,0) NOT NULL DEFAULT 0,
  min_guests SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  max_guests SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  UNIQUE KEY uq_event_type (event_id, type),
  CONSTRAINT fk_opt_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(16) NOT NULL UNIQUE,
  event_id INT UNSIGNED NOT NULL,
  type ENUM('individual','couple','group','corporate','vip','table') NOT NULL,
  full_name VARCHAR(140) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  company VARCHAR(190) NULL,
  guests SMALLINT UNSIGNED NOT NULL,
  seating VARCHAR(190) NULL,
  package VARCHAR(80) NULL,
  ticket_token VARCHAR(40) NULL UNIQUE,
  requests TEXT NULL,
  amount_due DECIMAL(12,0) NOT NULL DEFAULT 0,
  status ENUM('pending','confirmed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  checked_in_at DATETIME NULL,
  withdrawn_at DATETIME NULL,
  withdrawn_reason VARCHAR(255) NULL,
  admin_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_res_event (event_id, status),
  KEY idx_res_phone (phone),
  CONSTRAINT fk_res_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Flat-price table packages (e.g. Silver, Gold Pro, Platinum) sold with the "table" reservation type
CREATE TABLE IF NOT EXISTS event_packages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  price DECIMAL(12,0) NOT NULL DEFAULT 0,
  sort SMALLINT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_event_pkg (event_id, name),
  CONSTRAINT fk_pkg_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Administrator-recorded payments (no card data is ever stored)
CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reservation_id INT UNSIGNED NULL,
  tab_id INT UNSIGNED NULL,
  amount DECIMAL(12,0) NOT NULL,
  method ENUM('cash','mobile_money','bank','pesapal','refund') NOT NULL DEFAULT 'cash',
  channel VARCHAR(20) NULL COMMENT 'daraza_mtn, daraza_airtel, daraza_refund, or NULL when recorded by staff',
  note VARCHAR(255) NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mobile money collections and refunds through Daraza (one row per attempt)
CREATE TABLE IF NOT EXISTS pay_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token CHAR(24) NOT NULL UNIQUE,
  reservation_id INT UNSIGNED NULL,
  tab_id INT UNSIGNED NULL,
  payer_name VARCHAR(140) NULL,
  payer_email VARCHAR(190) NULL,
  payer_note VARCHAR(255) NULL,
  direction ENUM('collect','refund') NOT NULL DEFAULT 'collect',
  amount INT UNSIGNED NOT NULL,
  phone VARCHAR(20) NOT NULL,
  method SMALLINT NOT NULL DEFAULT 1,
  status ENUM('pending','success','failed','unconfirmed') NOT NULL DEFAULT 'pending',
  request_id VARCHAR(80) NULL,
  message VARCHAR(255) NULL,
  started_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  KEY idx_pa_res (reservation_id, status), KEY idx_pa_phone (phone, created_at),
  CONSTRAINT fk_pa_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment tabs: a fixed-amount Pay box the admin switches on for pages such as Advertise, DJs and MCs
CREATE TABLE IF NOT EXISTS payment_tabs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL UNIQUE,
  title VARCHAR(120) NOT NULL,
  blurb VARCHAR(255) NOT NULL DEFAULT '',
  amount INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 0,
  pages VARCHAR(255) NOT NULL DEFAULT '',
  sort SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pt_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO payment_tabs (slug, title, blurb, pages, sort) VALUES ('advertising', 'Advertising payment', 'Pay for your advertising or sponsorship package.', 'advertise', 1);
INSERT IGNORE INTO payment_tabs (slug, title, blurb, pages, sort) VALUES ('dj-booking', 'DJ booking payment', 'Pay your DJ booking fee or deposit.', 'djs', 2);
INSERT IGNORE INTO payment_tabs (slug, title, blurb, pages, sort) VALUES ('mc-booking', 'MC and comedy booking payment', 'Pay your MC or comedy show booking fee or deposit.', 'laugh', 3);

-- Money withdrawn from the Daraza wallet to a mobile money number (admin only)
CREATE TABLE IF NOT EXISTS daraza_withdrawals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  amount INT UNSIGNED NOT NULL,
  phone VARCHAR(20) NOT NULL,
  method SMALLINT NOT NULL DEFAULT 1,
  status ENUM('pending','success','failed','unconfirmed') NOT NULL DEFAULT 'pending',
  request_id VARCHAR(80) NULL,
  message VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  KEY idx_dw_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS article_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS articles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  author_id INT UNSIGNED NULL,
  title VARCHAR(220) NOT NULL,
  slug VARCHAR(240) NOT NULL UNIQUE,
  excerpt VARCHAR(300) NOT NULL DEFAULT '',
  body MEDIUMTEXT NOT NULL,
  image VARCHAR(255) NULL,
  label ENUM('news','opinion','gossip','unconfirmed','sponsored') NOT NULL DEFAULT 'news',
  sponsor_name VARCHAR(140) NULL,
  status ENUM('draft','pending','scheduled','published') NOT NULL DEFAULT 'draft',
  source_name VARCHAR(140) NULL,
  source_url VARCHAR(500) NULL,
  published_at DATETIME NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  meta_description VARCHAR(300) NULL,
  correction TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_art_pub (status, published_at),
  CONSTRAINT fk_art_cat FOREIGN KEY (category_id) REFERENCES article_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_art_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS videos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  url VARCHAR(255) NULL,
  file VARCHAR(255) NULL,
  poster VARCHAR(255) NULL,
  kind ENUM('clip','full_show','behind_scenes','interview','highlight') NOT NULL DEFAULT 'clip',
  description VARCHAR(300) NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sponsors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  logo VARCHAR(255) NULL,
  website VARCHAR(255) NULL,
  tier ENUM('title','gold','silver','partner') NOT NULL DEFAULT 'partner',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gallery_albums (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gallery_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_id INT UNSIGNED NULL,
  file VARCHAR(255) NOT NULL,
  caption VARCHAR(190) NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_img_album FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- contact, comedy performance bookings and advertising enquiries
CREATE TABLE IF NOT EXISTS inquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('contact','performance','advertising','dj') NOT NULL,
  name VARCHAR(140) NOT NULL,
  organization VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(190) NULL,
  subject VARCHAR(190) NULL,
  message TEXT NULL,
  details TEXT NULL COMMENT 'JSON of type-specific fields',
  status ENUM('new','in_progress','won','closed') NOT NULL DEFAULT 'new',
  follow_up VARCHAR(255) NULL,
  agreed_package VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inq_type (type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  k VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rl (k, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  detail VARCHAR(500) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Visitor statistics (anonymous: no IP addresses or cookies are stored)
CREATE TABLE IF NOT EXISTS page_views (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  path VARCHAR(190) NOT NULL,
  visitor CHAR(16) NOT NULL,
  country CHAR(2) NULL,
  device ENUM('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
  browser VARCHAR(30) NULL,
  source VARCHAR(12) NOT NULL DEFAULT 'direct',
  ref_host VARCHAR(120) NULL,
  KEY idx_pv_time (viewed_at), KEY idx_pv_path (path, viewed_at), KEY idx_pv_country (country, viewed_at), KEY idx_pv_visitor (visitor, viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS geo_cache (
  ip_hash CHAR(40) PRIMARY KEY,
  country CHAR(2) NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Editable page content (Admin > Pages). A missing/empty row falls back to the built-in default text.
CREATE TABLE IF NOT EXISTS site_content (
  `key` VARCHAR(120) PRIMARY KEY,
  `value` MEDIUMTEXT NOT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bloggers and media houses applying to be accredited to publish on The Gulu Chronicles.
-- Approval creates a 'contributor' user who can submit articles for editorial review.
CREATE TABLE IF NOT EXISTS accreditation_applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  applicant_type ENUM('blogger','media_house','journalist','vlogger') NOT NULL,
  full_name VARCHAR(140) NOT NULL,
  organization VARCHAR(160) NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  location VARCHAR(140) NULL,
  website VARCHAR(255) NULL,
  socials VARCHAR(500) NULL,
  coverage VARCHAR(255) NULL,
  samples TEXT NOT NULL,
  about TEXT NOT NULL,
  document VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_acc_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Partnership applications from the public; approval creates a sponsors row (shown under Partners)
CREATE TABLE IF NOT EXISTS partner_applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company VARCHAR(160) NOT NULL,
  contact_name VARCHAR(140) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(190) NOT NULL,
  website VARCHAR(255) NULL,
  industry VARCHAR(120) NULL,
  partnership_type VARCHAR(80) NOT NULL,
  about TEXT NOT NULL,
  proposal TEXT NULL,
  logo VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(255) NULL,
  sponsor_id INT UNSIGNED NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pa_status (status, created_at),
  CONSTRAINT fk_pa_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Podium DJs roster
CREATE TABLE IF NOT EXISTS djs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  role ENUM('dj','host') NOT NULL DEFAULT 'dj',
  tagline VARCHAR(190) NULL,
  genres VARCHAR(190) NULL,
  bio TEXT NULL,
  photo VARCHAR(255) NULL,
  photo_pos TINYINT UNSIGNED NOT NULL DEFAULT 15,
  instagram VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DJ mixes: an uploaded audio file or an external link (SoundCloud, Mixcloud, YouTube)
CREATE TABLE IF NOT EXISTS dj_mixes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dj_id INT UNSIGNED NULL,
  dj_name VARCHAR(120) NOT NULL,
  source ENUM('podium','chillbite') NOT NULL DEFAULT 'podium',
  title VARCHAR(190) NOT NULL,
  genre VARCHAR(80) NULL,
  description VARCHAR(300) NULL,
  audio VARCHAR(255) NULL,
  external_url VARCHAR(500) NULL,
  cover VARCHAR(255) NULL,
  allow_download TINYINT(1) NOT NULL DEFAULT 0,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mix_status (status, source),
  CONSTRAINT fk_mix_dj FOREIGN KEY (dj_id) REFERENCES djs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
