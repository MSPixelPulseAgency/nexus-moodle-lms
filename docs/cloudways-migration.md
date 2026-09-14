# Cloudways migration runbook

This runbook prepares the Nexus Education Private School Moodle installation for a future Cloudways deployment. It does not deploy anything or expose local credentials. Cloudways hosting is outside this free local development environment.

## What moves

Use a private `./scripts/backup.sh` export. The required portable assets are:

1. `moodle-application.tar.gz` — Moodle application code, installed themes, and plugins
2. `moodledata.tar.gz` — uploaded course files, generated data, cache configuration, and Moodle private files
3. `moodle.sql` — the MariaDB database

Also retain the current `.env` in a password manager or another approved secret store. The backup package contains only an intentionally redacted configuration reference.

## Before transfer

1. Confirm the target Cloudways server satisfies Moodle 4.5 LTS requirements and supports PHP 8.3, MariaDB/MySQL with `utf8mb4`, the PHP Redis extension, Apache rewrite rules, cron, and adequate disk space.
2. Prepare the production DNS/SSL plan for `https://lms.nexuseps.com`; keep it separate from the public site `https://nexuseps.com`.
3. Decide the production SMTP provider and Redis endpoint. Do not carry Mailpit to production.
4. Provision a database, a non-root database user, a web-accessible Moodle code directory, and a private `moodledata` directory outside the web root.
5. Test this procedure on a staging server before a production cutover.

## Create the final local export

1. Put the Moodle site into maintenance mode during the final content freeze:

   ```bash
   docker compose exec moodle php admin/cli/maintenance.php --enable
   ```

2. Wait for active authoring/upload activity to finish.
3. Run the backup:

   ```bash
   ./scripts/backup.sh
   ```

4. Validate hashes before copying the backup:

   ```bash
   cd backups/nexus-moodle-YYYYMMDD-HHMMSS
   shasum -a 256 -c SHA256SUMS
   ```

5. Keep the site in maintenance mode until the cutover decision is made, or disable it if the migration is only a rehearsal:

   ```bash
   docker compose exec moodle php admin/cli/maintenance.php --disable
   ```

## Transfer and restore on Cloudways

1. Copy the backup directory privately to the Cloudways server using an approved encrypted method (for example, SFTP/SSH). Do not upload it to a public web directory.
2. Extract `moodle-application.tar.gz` into the Cloudways application web root, preserving file permissions.
3. Extract `moodledata.tar.gz` into the private Moodle data directory outside the web root. The web-server user must be able to read/write it; it must not be reachable over HTTP.
4. Create the production database with `utf8mb4` and import `moodle.sql` using the Cloudways database tooling or CLI.
5. Update production `config.php` for the Cloudways database credentials, private `moodledata` path, Redis session settings, Redis application-cache mapping, production SMTP, and:

   ```php
   $CFG->wwwroot = 'https://lms.nexuseps.com';
   ```

   Keep the generated `wwwroot` value exact: no trailing slash and no local URL.

6. Ensure PHP 8.3 settings meet or exceed the local limits (`512M` upload/post, `512M` memory, suitable execution time) and required extensions are installed (`intl`, `mysqli`, `mbstring`, `xml`, `zip`, `gd`, `soap`, `opcache`, `redis`).
7. Configure the Cloudways scheduler to run Moodle cron every minute as the application/web user:

   ```bash
   * * * * * /usr/bin/php /path/to/moodle/admin/cli/cron.php >/dev/null 2>&1
   ```

   Replace `/path/to/moodle` with the real protected deployment path.
8. Run Moodle's non-interactive upgrade check, then purge caches:

   ```bash
   php admin/cli/upgrade.php --non-interactive
   php admin/cli/purge_caches.php
   ```

9. On Cloudways Hybrid Stack, install the production root rules before the
   public-path security check:

   ```bash
   cp deployment/cloudways-moodle.htaccess /path/to/moodle/.htaccess
   ```

   These rules disable directory listings and return `404` for source-only
   Behat and fixture files. Re-run `admin/cli/checks.php --type=security`
   after installation. If the Cloudways front proxy serves static source files
   before Apache, add equivalent application Web Rules or make the exact files
   named by Moodle's public-path check unreadable by the web-server user. Keep
   `config.php` read-only in production (for example, mode `0440`).
10. Keep the production site in maintenance mode until database access, file uploads, cron, Redis, outgoing mail, SSL, and administrator login are verified.

## Required verification before cutover

- `https://lms.nexuseps.com` resolves over HTTPS and shows the expected Nexus site name.
- Administrator login works without password reset errors.
- A course-file upload can be stored and retrieved.
- Moodle reports Redis session and application cache configuration as ready.
- The one-minute cron task executes successfully in Moodle scheduled-task logs.
- A controlled test email goes to the approved production mailbox (not Mailpit).
- `moodledata` is confirmed outside the public document root.
- The live database has `utf8mb4` collation and no development-only credentials.
- The source, `moodledata`, and database backups are retained privately and have verified checksums.

Only after these checks, disable maintenance mode and complete the DNS/cutover plan. A local successful build or a transferred archive is not proof that the production service is ready.
