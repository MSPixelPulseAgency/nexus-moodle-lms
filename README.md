# Nexus Education Private School LMS — Local Development

This repository provides the complete free, local Docker Desktop environment for the Nexus Education Private School Moodle LMS. It runs Moodle 4.5 LTS with PHP 8.3 + Apache, MariaDB, Redis, Moodle cron, Mailpit, and phpMyAdmin on an Apple Silicon Mac.

No public-facing school content, student counts, or sample school details are created by this environment.

## Local URLs

| Service | URL |
| --- | --- |
| Nexus Learning Management System | http://localhost:8080 |
| Mailpit email inbox | http://localhost:8025 |
| phpMyAdmin | http://localhost:8081 |

The installed Moodle identity is:

- Full site name: `Nexus Education Private School`
- Short name: `Nexus EPS`
- Portal reference: `Nexus Learning Management System`
- Future production URL: `https://lms.nexuseps.com`
- Public website: `https://nexuseps.com`
- Administrator username: `admin`
- Administrator email: `mspixelpulse@gmail.com`
- Administrator password: the private `MOODLE_ADMIN_PASSWORD` value in `.env`

## Prerequisites

- Docker Desktop for Mac, running and configured for Apple Silicon.
- At least 6 GB of memory available to Docker Desktop.
- Ports `8080`, `8081`, and `8025` free on the Mac.

Verify Docker Desktop before continuing:

```bash
docker version
docker compose version
```

## First start

The current working copy includes a locally generated, Git-ignored `.env`. A fresh clone must create its own secrets before first start:

```bash
cp .env.example .env
./scripts/generate-secrets.sh
./scripts/start.sh
```

`generate-secrets.sh` intentionally exits if `.env` already exists. This protects a working installation from an accidental password change.

The first build downloads the official Moodle `MOODLE_405_STABLE` source branch, builds PHP 8.3 extensions, then installs Moodle automatically. Subsequent starts reuse the persistent named volumes and are much faster.

Check the result:

```bash
docker compose ps
curl -I http://localhost:8080/login/index.php
```

For the normal service containers, the `STATUS` column should become `healthy`. `moodle-init` is a one-time job and correctly finishes as `Exited (0)`.

Open the LMS at http://localhost:8080 and log in as `admin`. To read the local-only password without printing unrelated configuration:

```bash
grep '^MOODLE_ADMIN_PASSWORD=' .env
```

## Day-to-day commands

```bash
# Start or rebuild after Docker/PHP configuration changes.
./scripts/start.sh

# Stop containers while preserving Moodle, database, and Redis data.
./scripts/stop.sh

# Stream all services (or pass a line count, e.g. ./scripts/logs.sh 500).
./scripts/logs.sh

# Show current service state.
docker compose ps

# Open a Moodle CLI shell.
docker compose exec moodle bash

# Run Moodle cron once manually (the cron service also runs it every minute).
docker compose exec moodle php admin/cli/cron.php

# Purge caches after adding or changing a plugin/theme.
docker compose exec moodle php admin/cli/purge_caches.php
```

`./scripts/reset.sh` is intentionally destructive. It requires typing `RESET` (or passing `--yes`) before removing only the named Nexus Moodle volumes. Create a backup first.

## Services and persistence

| Service | Purpose | Persistence |
| --- | --- | --- |
| `moodle` | Moodle 4.5 LTS, PHP 8.3, Apache | `nexus-eps-lms_moodle_app`, `nexus-eps-lms_moodledata` |
| `moodle-init` | One-time, idempotent Moodle install and Redis cache configuration | completes successfully after install |
| `moodle-cron` | Runs `admin/cli/cron.php` every minute | Moodle application/data volumes |
| `db` | MariaDB 11.4 | `nexus-eps-lms_mariadb_data` |
| `redis` | Redis 7.4 sessions and Moodle application cache | `nexus-eps-lms_redis_data` |
| `mailpit` | Captures all development email | in-container test inbox |
| `phpmyadmin` | Local database browser | none |

Moodle source, installed plugins, and themes are seeded into the `moodle_app` named volume, rather than being hidden behind a container filesystem. They therefore survive image/container rebuilds. `moodledata`, MariaDB, and Redis are also named volumes. Docker Desktop manages these volumes locally; MariaDB and Redis are not published to the Mac network.

The stack is multi-architecture: all images used are arm64-compatible and Docker Desktop pulls the correct Apple Silicon variant.

## Configuration details

- PHP upload and request limits are `512M`; execution/input timeouts are `300` seconds.
- MariaDB uses `utf8mb4` and a `64M` maximum packet size.
- Redis is password-protected, persists with AOF, and is configured for Moodle sessions plus Moodle application/MUC caches.
- Mail is sent only to Mailpit (`mailpit:1025`); no external mail delivery is configured.
- `docker/config.php` reads secrets only from container environment variables. It is safe to commit because it contains no live password.
- `.env`, backups, logs, secrets, and optional local bind-mount directories are Git-ignored. Keep `.env` permissioned to the local user (`chmod 600 .env`).

For phpMyAdmin, the server is preselected as `db`. Log in with the local Moodle database username from `MOODLE_DB_USER` and its matching private password from `MOODLE_DB_PASSWORD` in `.env`; use the database named by `MOODLE_DB_NAME`.

## Plugin and theme development

Use the persistent Moodle application volume from the running service. For example:

```bash
# Inspect installed themes and plugins.
docker compose exec moodle ls -la theme local mod

# Copy a local development plugin into the persistent application volume.
docker compose cp /absolute/path/to/plugin moodle:/var/www/moodle/local/pluginname

# Run the Moodle upgrade flow after installing or updating a plugin.
docker compose exec moodle php admin/cli/upgrade.php --non-interactive
docker compose exec moodle php admin/cli/purge_caches.php
```

Do not overwrite Moodle core or run a version upgrade without creating a backup. The backup includes the exact application volume, so it captures installed themes/plugins alongside core code.

## MHF4U starter provisioning

`scripts/provision-mhf4u-starter.php` idempotently configures the existing `MHF4U` course, its original starter lessons and activities, the requested staff/student accounts, role assignments, site profile, and Nexus brand files. It refuses to run unless exactly one course uses the `MHF4U` short name and it never contains or logs passwords.

Run a dry check in the local container first:

```bash
docker compose cp scripts/provision-mhf4u-starter.php moodle:/tmp/provision-mhf4u-starter.php
docker compose cp scripts/mhf4u-starter-content.php moodle:/tmp/mhf4u-starter-content.php
docker compose exec -T \
  -e NEXUS_MHF4U_CONTENT=/tmp/mhf4u-starter-content.php \
  moodle php /tmp/provision-mhf4u-starter.php --dry-run
```

The live run requires `NEXUS_PASSWORD_RUSHAL`, `NEXUS_PASSWORD_RADHIKA`, `NEXUS_PASSWORD_JAINAM`, `NEXUS_PASSWORD_ADLER`, `NEXUS_PASSWORD_TEACHER`, and `NEXUS_PASSWORD_STUDENT` in the process environment. Supply them from an approved private source only. Do not save those values in this repository, command history, deployment logs, or public hosting directories. Create and verify a complete live snapshot before provisioning.

## Backups and local restore

Create a complete timestamped export while the stack is running:

```bash
./scripts/backup.sh
```

Each `backups/nexus-moodle-YYYYMMDD-HHMMSS/` directory contains:

- `moodle.sql` — MariaDB dump with routines, events, and triggers
- `moodle-application.tar.gz` — Moodle core, themes, and plugins
- `moodledata.tar.gz` — Moodle file storage and generated data
- `config/` — Docker/PHP/Moodle configuration reference and a redacted `.env`
- `manifest.txt` and `SHA256SUMS`

Backups are intentionally Git-ignored. Store them privately. The script redacts passwords; retain the original `.env` privately if a complete credential recovery is required.

Restore a backup into this local stack only after reviewing its target:

```bash
./scripts/restore.sh /absolute/path/to/backups/nexus-moodle-YYYYMMDD-HHMMSS --yes
```

The restore command replaces the current Nexus named volumes. It never runs automatically.

## Cloudways transfer preparation

The local stack does not deploy to Cloudways. When production infrastructure is authorized and ready, follow [the Cloudways migration runbook](docs/cloudways-migration.md). It uses the same three portable assets from `backup.sh`:

1. Moodle application code, plugins, and themes
2. `moodledata`
3. MariaDB SQL dump

Before a production cutover, replace local URLs and Mailpit settings with production values, put the site into maintenance mode, complete a final backup, and test a non-production restore. Do not point this local environment at `lms.nexuseps.com`.

## Troubleshooting

```bash
# Validate the Compose file and resolved environment without starting anything.
docker compose config -q

# See the installer failure, if one occurred.
docker compose logs --no-color moodle-init

# Confirm Redis authentication and its connection from Moodle.
docker compose exec redis redis-cli -a "$REDIS_PASSWORD" ping
docker compose exec moodle php -r 'echo class_exists("Redis") ? "Redis PHP extension loaded\n" : "Redis extension missing\n";'

# If the first install failed before completion, inspect logs before resetting.
./scripts/reset.sh
./scripts/start.sh
```

The Redis example above is intended for an interactive shell with `.env` values exported. Alternatively, run `docker compose exec redis redis-cli -a "$(grep '^REDIS_PASSWORD=' .env | cut -d= -f2-)" ping`.
