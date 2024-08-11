-- Queries executed on database brc (/Users/user/Developer/bible-reading-challenge/brc.db)
-- Date and time of execution: 2024-08-08 18:10:24
PRAGMA foreign_keys = 0;
CREATE TABLE sqlitestudio_temp_table AS SELECT * FROM sites;
DROP TABLE sites;
CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT, enabled INTEGER DEFAULT (1), site_name TEXT, domain_www TEXT, domain_www_test TEXT, domain_socket TEXT, domain_socket_test TEXT, short_name TEXT, contact_name TEXT, contact_email TEXT, contact_phone TEXT, email_from_address TEXT, email_from_name TEXT, favico_image_id INTEGER, logo_image_id INTEGER, login_image_id INTEGER, progress_image_id INTEGER, progress_image_coordinates TEXT DEFAULT ('[50,0,50,88]'), color_primary TEXT DEFAULT ('rgb(0, 0, 0)'), color_secondary TEXT DEFAULT ('rgb(0, 0, 0)'), color_fade TEXT DEFAULT ('rgb(0, 0, 0)'), default_emoji TEXT, reading_timer_wpm INTEGER DEFAULT (0), start_of_week INTEGER DEFAULT (1), time_zone_id TEXT DEFAULT ('America/Chicago'), env TEXT, allow_personal_schedules INTEGER DEFAULT (0), translations TEXT DEFAULT ["rcv","kjv","esv","asv","niv","nlt"], web_push_pubkey TEXT, web_push_privkey TEXT);
INSERT INTO sites (id, enabled, site_name, domain_www, domain_www_test, domain_socket, domain_socket_test, short_name, contact_name, contact_email, contact_phone, email_from_address, email_from_name, favico_image_id, logo_image_id, login_image_id, progress_image_id, progress_image_coordinates, color_primary, color_secondary, color_fade, default_emoji, reading_timer_wpm, start_of_week, time_zone_id, env, allow_personal_schedules, translations) SELECT id, enabled, site_name, domain_www, domain_www_test, domain_socket, domain_socket_test, short_name, contact_name, contact_email, contact_phone, email_from_address, email_from_name, favico_image_id, logo_image_id, login_image_id, progress_image_id, progress_image_coordinates, color_primary, color_secondary, color_fade, default_emoji, reading_timer_wpm, start_of_week, time_zone_id, env, allow_personal_schedules, translations FROM sqlitestudio_temp_table;
DROP TABLE sqlitestudio_temp_table;
PRAGMA foreign_keys = 1;

-- Queries executed on database brc (/Users/user/Developer/bible-reading-challenge/brc.db)
-- Date and time of execution: 2024-08-08 18:12:38
PRAGMA foreign_keys = 0;
CREATE TABLE sqlitestudio_temp_table AS SELECT * FROM sites;
DROP TABLE sites;
CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT, enabled INTEGER DEFAULT (1), site_name TEXT, domain_www TEXT, domain_www_test TEXT, domain_socket TEXT, domain_socket_test TEXT, short_name TEXT, contact_name TEXT, contact_email TEXT, contact_phone TEXT, email_from_address TEXT, email_from_name TEXT, favico_image_id INTEGER, logo_image_id INTEGER, login_image_id INTEGER, progress_image_id INTEGER, progress_image_coordinates TEXT DEFAULT ('[50,0,50,88]'), color_primary TEXT DEFAULT ('rgb(0, 0, 0)'), color_secondary TEXT DEFAULT ('rgb(0, 0, 0)'), color_fade TEXT DEFAULT ('rgb(0, 0, 0)'), default_emoji TEXT, reading_timer_wpm INTEGER DEFAULT (0), start_of_week INTEGER DEFAULT (1), time_zone_id TEXT DEFAULT ('America/Chicago'), env TEXT, allow_personal_schedules INTEGER DEFAULT (0), translations TEXT DEFAULT ["rcv","kjv","esv","asv","niv","nlt"], vapid_pubkey TEXT, vapid_privkey TEXT);
INSERT INTO sites (id, enabled, site_name, domain_www, domain_www_test, domain_socket, domain_socket_test, short_name, contact_name, contact_email, contact_phone, email_from_address, email_from_name, favico_image_id, logo_image_id, login_image_id, progress_image_id, progress_image_coordinates, color_primary, color_secondary, color_fade, default_emoji, reading_timer_wpm, start_of_week, time_zone_id, env, allow_personal_schedules, translations) SELECT id, enabled, site_name, domain_www, domain_www_test, domain_socket, domain_socket_test, short_name, contact_name, contact_email, contact_phone, email_from_address, email_from_name, favico_image_id, logo_image_id, login_image_id, progress_image_id, progress_image_coordinates, color_primary, color_secondary, color_fade, default_emoji, reading_timer_wpm, start_of_week, time_zone_id, env, allow_personal_schedules, translations FROM sqlitestudio_temp_table;
DROP TABLE sqlitestudio_temp_table;
PRAGMA foreign_keys = 1;

-- Queries executed on database brc (/Users/user/Developer/bible-reading-challenge/brc.db)
-- Date and time of execution: 2024-08-08 18:26:41
CREATE TABLE push_subscriptions (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER,
    subscription TEXT,
    last_sent    TEXT
);
CREATE INDEX idx_push_subscriptions_user_id ON push_subscriptions (
    user_id
);
