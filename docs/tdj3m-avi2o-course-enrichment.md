# TDJ3M and AVI2O enrichment

The idempotent plan and provisioning tools in `scripts/tdj3m-avi2o-course-plan.php` and `scripts/provision-tdj3m-avi2o.php` provision Moodle courses 170 (TDJ3M) and 127 (AVI2O).

- TDJ3M: 5 unit Books, 20 lesson chapters, 30 teacher-supplied MP4 videos, and 4 teacher-supplied PPTX decks.
- AVI2O: 5 unit Books, 20 lesson chapters, no video imports.
- Both courses include overview/instructions/assessment/submission/portfolio pages, formative quizzes, practical activities, major projects, and culminating portfolio submissions.
- Media is stored through Moodle's permanent `mod_book` chapter File API area; no local paths or fake players are used.
- Curriculum mappings use the Ontario Ministry of Education course documents for TDJ3M and AVI2O. No CanSTEM site was accessed.
- Separate supported Moodle 2 backups were made before changes in `/home/master/nexus-tdj3m-avi2o-backups-20260917`.
- No enrolment mapping was provided; no additional enrolments were performed.
