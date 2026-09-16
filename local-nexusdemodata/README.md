# Nexus demo learning data

This local plugin packages the approved Nexus course banners, MHF4U assignment handouts, and fictional student submission files. Its CLI provisioner is deliberately explicit and idempotent.

```bash
NEXUS_MOODLE_ROOT=/path/to/moodle php local/nexusdemodata/cli/provision.php --dry-run
NEXUS_MOODLE_ROOT=/path/to/moodle php local/nexusdemodata/cli/provision.php --apply
NEXUS_MOODLE_ROOT=/path/to/moodle php local/nexusdemodata/cli/verify.php
```

The tool resolves target courses by exact shortname, refuses ambiguous records, uses Moodle APIs for users, enrolments, activities, files, and submissions, and never logs generated passwords.
