# Dependencies

## Language
Written with php 8.2.20 and apache 2.4.61

## Configuration
Set the system time zone and php time zone (`date.timezone` in `php.ini`) to the same thing.

In `php.ini`, set the following:
```
max_input_vars = 3000;
upload_max_filesize = 10M;
```

This is sufficient for editing a schedule that is up to 4 years long and typical picture uploads.

Install [composer](https://getcomposer.org/) dependencies in root of project with `composer install`

In apache and nginx configuration, be sure file uploads are also set to be at least 10MB.

## Database

#### SQLite
Requires minimum version 3.46 (support for GROUP_CONCAT(..ORDER BY..) and ->> syntax)

Create an SQLite database file named "brc.db" in root of project from schema.sql

Initialize it with the data from `migrations/bible-import.sql`

Enable WAL mode: `sqlite3 brc.db "PRAGMA journal_mode=WAL;"`

#### Redis
Redis is used for session management.

Run a Redis (or compatible) server, default ports.
```sh
# Server
docker run -v /home/bible-reading-challenge/:/data --restart unless-stopped -d -it -p 6379:6379 redis:7-alpine
# locally
docker run -it --rm -v ./:/data -p 6379:6379 redis:7-alpine
```

### Schema
to export the schema after an update, run `sqlite3 brc.db ".schema --indent" > migrations/schema.sql`

## Realtime updates
This website supports readers' seeing each other on the page reading together

### Setup
From the `socket` directory, run `npm i` and then keep it alive with `forever start server.js`

It defaults to port `8085`, customizable with the environment variable `SOCKET_PORT`

## API Keys
Each site created in the database requires the following values in the 'env' column

### Emails
Be sure to set up the email templates in the sendgrid console
- SENDGRID_API_KEY_ID
- SENDGRID_API_KEY
- SENDGRID_REGISTER_EMAIL_TEMPLATE
- SENDGRID_DAILY_EMAIL_TEMPLATE
- SENDGRID_FORGOT_PASSWORD_TEMPLATE

### Google Sign-in button
also requires configuring OAuth consent screen in Google Cloud Console
- GOOGLE_CLIENT_ID
- GOOGLE_CLIENT_SECRET

## Crons
files in the `cron` directory should be installed according to the comments at the top of each file

## Queue Processing
Install the `system.d` service `task-queue/brc-task-queue.service`. This is important for updating user statistics whenever something changes:
1. Copy to `/etc/systemd/system/brc-task-queue.service`
2. Reload the systemd daemon: `sudo systemctl daemon-reload`
3. Enable it: `sudo systemctl enable brc-task-queue.service`
4. Start it: `sudo systemctl start brc-task-queue.service`
5. Check the status: `sudo systemctl status brc-task-queue.service`

## Migrations
Any scripts in the `migration` directory are meant to be run-once for a particular purpose (e.g., initiating streaks mid-challenge). See comments in each file.

Numbers indicate the order in which scripts were created and the data changed