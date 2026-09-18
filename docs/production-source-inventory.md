# Production source inventory

This repository records the legitimate source-code state observed on the Nexus Moodle production application on 2026-09-18. Production remains hosted separately on Cloudways and is not connected to this Git repository.

## Moodle core baseline

- Moodle release: `4.5.12+ (Build: 20260728)`
- Branch: `405`
- Upstream commit: `136359a52b388a138abaa3766eb8dbe7ec824962`
- Repository representation: exact commit pinned as `MOODLE_REF` in `Dockerfile`

The production core tree matched that upstream revision. No production-only Moodle core patch is required in Git.

## Nexus source mapped from production

- `moodle/local/nexusbranding` — branding assets, responsive styling, metadata hooks, and event observers
- `local-nexusadminenrol` — automatic administrator enrolment plugin, including reconciliation task, event observers, CLI entry point, and XMLDB schema
- `local-nexusdemodata` — versioned course/demo provisioning source and reusable assets
- `moodle/local/nexusimports` — live import/enrolment utility source
- `moodle/theme/nexus` — custom Boost-derived Nexus theme
- `moodle/theme/moove` — live Moove 4.5.2 source plus the Nexus homepage media asset
- `moodle/theme/adaptable` — live Adaptable 405.2.8 source
- `moodle/configure-nexus-moove-home.php` and `moodle/local/import-ontario-courses-csv.php` — copies at their live application paths; maintained repository variants remain under `scripts/`

The tracked contributed plugins under `moodle/blocks`, `moodle/course/format`, `moodle/local`, and `moodle/mod` matched their production copies. `moodle/mod/pdfannotator` is retained as repo-only development tooling and was not present on production at the time of this inventory.

## Intentionally excluded

- Production `config.php` and all credentials/secrets
- `.env` files and SSH keys
- Database contents or dumps
- `moodledata`, including user uploads and private course files
- Sessions, caches, generated local cache, temporary files, and logs
- Backups and course backup packages
- Server-only scratch/backup copies such as `*.pre-*`

Production-specific configuration is supplied by Cloudways and environment configuration. Safe local configuration examples remain in `.env.example` and `docker/config.php`.
