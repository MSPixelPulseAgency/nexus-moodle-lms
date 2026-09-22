<?php

/**
 * Idempotently provision the Nexus visual and demo-learning package.
 */

declare(strict_types=1);

define('CLI_SCRIPT', true);

$options = getopt('', ['dry-run', 'apply', 'help']);
if (isset($options['help'])) {
    echo "Usage: NEXUS_MOODLE_ROOT=/path/to/moodle php provision.php --dry-run|--apply\n";
    exit(0);
}
$dryrun = isset($options['dry-run']);
$apply = isset($options['apply']);
if ($dryrun === $apply) {
    fwrite(STDERR, "Choose exactly one of --dry-run or --apply.\n");
    exit(2);
}

$defaultroot = dirname(__DIR__, 3);
$root = getenv('NEXUS_MOODLE_ROOT') ?: $defaultroot;
if (!is_readable($root . '/config.php')) {
    throw new RuntimeException('Moodle config.php is not readable at the resolved root.');
}

require $root . '/config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/course/modlib.php';
require_once $CFG->dirroot . '/user/lib.php';
require_once $CFG->libdir . '/enrollib.php';
require_once $CFG->libdir . '/filelib.php';
require_once $CFG->dirroot . '/mod/assign/lib.php';
require_once $CFG->dirroot . '/mod/assign/locallib.php';

global $CFG, $DB, $PAGE, $USER;

$admin = get_admin();
\core\session\manager::set_user($admin);
$USER = $admin;

$packageroot = dirname(__DIR__);
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
$managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);
$siteadmins = array_fill_keys(array_map('intval', array_keys(get_admins())), true);

$coursemap = [
    'MHF4U' => 'mhf4u',
    'SBI4U' => 'sbi4u',
    'SCH4U' => 'sch4u',
    'ENG4U' => 'eng4u',
    'ASM4M' => 'asm4m',
    'SPH4U' => 'sph4u',
    'TDJ4M' => 'tdj4m',
    'AVI1O' => 'avi1o',
];

$students = [
    'ethan.campbell' => [
        'firstname' => 'Ethan', 'lastname' => 'Campbell',
        'email' => 'ethan.campbell@example.invalid',
        'courses' => ['MHF4U', 'SBI4U', 'SPH4U'],
    ],
    'olivia.bennett' => [
        'firstname' => 'Olivia', 'lastname' => 'Bennett',
        'email' => 'olivia.bennett@example.invalid',
        'courses' => ['MHF4U', 'SCH4U', 'ENG4U', 'ASM4M'],
    ],
    'liam.foster' => [
        'firstname' => 'Liam', 'lastname' => 'Foster',
        'email' => 'liam.foster@example.invalid',
        'courses' => ['MHF4U', 'SBI4U', 'TDJ4M'],
    ],
    'chloe.martin' => [
        'firstname' => 'Chloe', 'lastname' => 'Martin',
        'email' => 'chloe.martin@example.invalid',
        'courses' => ['MHF4U', 'ENG4U', 'AVI1O'],
    ],
];

$assignments = [
    1 => [
        'idnumber' => 'nexus_demo_assignment_1',
        'name' => 'Assignment 1 - Function Transformations',
        'section' => 1,
        'grade' => 40,
        'open' => '2026-09-14 08:00:00',
        'due' => '2026-09-18 23:59:00',
        'pdf' => 'assignment-1-function-transformations.pdf',
        'summary' => 'Apply transformations to polynomial and rational parent functions, communicate domain and range, and justify the effect of each parameter.',
    ],
    2 => [
        'idnumber' => 'nexus_demo_assignment_2',
        'name' => 'Assignment 2 - Polynomial and Rational Functions',
        'section' => 3,
        'grade' => 50,
        'open' => '2026-09-14 08:00:00',
        'due' => '2026-09-25 23:59:00',
        'pdf' => 'assignment-2-polynomial-rational-functions.pdf',
        'summary' => 'Factor and analyze polynomial and rational functions, identify restrictions and asymptotes, and connect algebraic features to graphs.',
    ],
    3 => [
        'idnumber' => 'nexus_demo_assignment_3',
        'name' => 'Assignment 3 - Exponential, Logarithmic, and Trigonometric Applications',
        'section' => 5,
        'grade' => 45,
        'open' => '2026-09-14 08:00:00',
        'due' => '2026-10-02 23:59:00',
        'pdf' => 'assignment-3-exp-log-trig-applications.pdf',
        'summary' => 'Model authentic situations with exponential, logarithmic, and sinusoidal functions and interpret solutions in context.',
    ],
];

$submissionplan = [
    ['username' => 'ethan.campbell', 'assignment' => 1, 'pdf' => 'ethan-campbell-assignment-1.pdf'],
    ['username' => 'olivia.bennett', 'assignment' => 1, 'pdf' => 'olivia-bennett-assignment-1.pdf'],
    ['username' => 'olivia.bennett', 'assignment' => 2, 'pdf' => 'olivia-bennett-assignment-2.pdf'],
    ['username' => 'liam.foster', 'assignment' => 2, 'pdf' => 'liam-foster-assignment-2.pdf'],
    ['username' => 'chloe.martin', 'assignment' => 1, 'pdf' => 'chloe-martin-assignment-1.pdf'],
    ['username' => 'chloe.martin', 'assignment' => 3, 'pdf' => 'chloe-martin-assignment-3.pdf'],
];

/** Resolve one exact course or stop without changing anything. */
function nexusdemo_course(string $shortname): stdClass {
    global $DB;
    $matches = $DB->get_records('course', ['shortname' => $shortname], 'id ASC');
    if (count($matches) !== 1) {
        throw new RuntimeException("Expected exactly one {$shortname} course; found " . count($matches) . '.');
    }
    return reset($matches);
}

/** Find a course module by stable idnumber. */
function nexusdemo_cm(stdClass $course, string $modname, string $idnumber): ?stdClass {
    global $DB;
    $sql = 'SELECT cm.*
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid AND cm.idnumber = :idnumber
                   AND cm.deletioninprogress = 0 AND m.name = :modname';
    return $DB->get_record_sql($sql, [
        'courseid' => $course->id,
        'idnumber' => $idnumber,
        'modname' => $modname,
    ]) ?: null;
}

/** Assert that an existing account is exactly the intended fictional identity. */
function nexusdemo_validate_student(stdClass $user, array $spec, array $siteadmins, int $managerroleid, int $teacherroleid): void {
    global $DB;
    if ($user->firstname !== $spec['firstname'] || $user->lastname !== $spec['lastname'] || $user->email !== $spec['email']) {
        throw new RuntimeException("Refusing to reuse mismatched account {$user->username}.");
    }
    if ($user->deleted || $user->suspended || isset($siteadmins[(int)$user->id])) {
        throw new RuntimeException("Student account {$user->username} is deleted, suspended, or a site administrator.");
    }
    if ($DB->record_exists_select('role_assignments', 'userid = :userid AND roleid IN (:manager, :teacher)', [
        'userid' => $user->id, 'manager' => $managerroleid, 'teacher' => $teacherroleid,
    ])) {
        throw new RuntimeException("Student account {$user->username} has a privileged role assignment.");
    }
}

/** Resolve the manual enrolment instance, creating one only during apply. */
function nexusdemo_manual_instance(stdClass $course, bool $apply): ?stdClass {
    global $DB;
    $instances = enrol_get_instances($course->id, true);
    foreach ($instances as $instance) {
        if ($instance->enrol === 'manual') {
            return $instance;
        }
    }
    if (!$apply) {
        return null;
    }
    $plugin = enrol_get_plugin('manual');
    if (!$plugin) {
        throw new RuntimeException('The manual enrolment plugin is unavailable.');
    }
    $instanceid = $plugin->add_instance($course);
    return $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
}

/** Enrol a user through the manual enrolment API. */
function nexusdemo_enrol(stdClass $course, stdClass $user, int $roleid): bool {
    $instance = nexusdemo_manual_instance($course, true);
    $plugin = enrol_get_plugin('manual');
    $context = context_course::instance($course->id);
    $already = is_enrolled($context, $user, '', true) && user_has_role_assignment($user->id, $roleid, $context->id);
    if ($already) {
        return false;
    }
    $plugin->enrol_user($instance, $user->id, $roleid, time(), 0, ENROL_USER_ACTIVE);
    return true;
}

/** Convert a Toronto wall-clock timestamp to Unix time. */
function nexusdemo_time(string $value): int {
    return (new DateTimeImmutable($value, new DateTimeZone('America/Toronto')))->getTimestamp();
}

$courses = [];
foreach ($coursemap as $shortname => $slug) {
    $courses[$shortname] = nexusdemo_course($shortname);
    $banner = $packageroot . '/pix/banners/' . $slug . '.webp';
    $fallback = $packageroot . '/pix/banners/' . $slug . '-static.webp';
    if (!is_readable($banner) || !is_readable($fallback)) {
        throw new RuntimeException("Banner assets for {$shortname} are missing.");
    }
}

$teacher = $DB->get_record('user', ['username' => 'teacher', 'deleted' => 0], '*', MUST_EXIST);
if ($teacher->suspended || isset($siteadmins[(int)$teacher->id])) {
    throw new RuntimeException('The reusable teacher is suspended or a site administrator.');
}
if ($DB->record_exists('role_assignments', ['userid' => $teacher->id, 'roleid' => $managerrole->id])) {
    throw new RuntimeException('The reusable teacher has a manager role assignment.');
}

$preflightstudents = [];
foreach ($students as $username => $spec) {
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if ($user) {
        nexusdemo_validate_student($user, $spec, $siteadmins, (int)$managerrole->id, (int)$teacherrole->id);
        $preflightstudents[$username] = $user;
    }
}

$fs = get_file_storage();
$plan = [
    'mode' => $dryrun ? 'dry-run' : 'apply',
    'courses' => [],
    'students' => [],
    'teacher' => ['username' => 'teacher', 'course_count' => count($courses)],
    'assignments' => [],
    'submissions' => [],
    'changes' => [],
];

foreach ($courses as $shortname => $course) {
    $context = context_course::instance($course->id);
    $overview = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'id ASC', false);
    $plan['courses'][$shortname] = [
        'id' => (int)$course->id,
        'current_overview_files' => array_values(array_map(static fn(stored_file $file): string => $file->get_filename(), $overview)),
        'target' => strtolower($shortname) . '.webp',
        'manual_enrolment' => nexusdemo_manual_instance($course, false) !== null,
    ];
}

foreach ($students as $username => $spec) {
    $plan['students'][$username] = [
        'exists' => isset($preflightstudents[$username]),
        'courses' => $spec['courses'],
        'role' => 'student',
    ];
}

$mhf4u = $courses['MHF4U'];
foreach ($assignments as $number => $spec) {
    $source = $packageroot . '/assets/assignments/' . $spec['pdf'];
    if (!is_readable($source)) {
        throw new RuntimeException("Assignment PDF {$spec['pdf']} is missing.");
    }
    $cm = nexusdemo_cm($mhf4u, 'assign', $spec['idnumber']);
    $plan['assignments'][$number] = [
        'idnumber' => $spec['idnumber'],
        'exists' => (bool)$cm,
        'due' => $spec['due'],
        'pdf' => $spec['pdf'],
    ];
}
foreach ($submissionplan as $submission) {
    $source = $packageroot . '/assets/submissions/' . $submission['pdf'];
    if (!is_readable($source)) {
        throw new RuntimeException("Submission PDF {$submission['pdf']} is missing.");
    }
    $plan['submissions'][] = $submission;
}

if ($dryrun) {
    echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

// Create or reuse the four strictly fictional student accounts.
$studentusers = [];
foreach ($students as $username => $spec) {
    $user = $preflightstudents[$username] ?? null;
    if (!$user) {
        $record = (object)[
            'username' => $username,
            'password' => random_string(28) . 'aA1!',
            'firstname' => $spec['firstname'],
            'lastname' => $spec['lastname'],
            'email' => $spec['email'],
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'country' => 'CA',
            'lang' => 'en',
            'maildisplay' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = user_create_user($record, true, false);
        $user = $DB->get_record('user', ['id' => $record->id], '*', MUST_EXIST);
        $plan['changes'][] = "created-user:{$username}";
    }
    nexusdemo_validate_student($user, $spec, $siteadmins, (int)$managerrole->id, (int)$teacherrole->id);
    $studentusers[$username] = $user;
}

// Enrol the teacher in every target and each learner in only the approved courses.
foreach ($courses as $shortname => $course) {
    if (nexusdemo_enrol($course, $teacher, (int)$teacherrole->id)) {
        $plan['changes'][] = "enrolled-teacher:{$shortname}";
    }
}
foreach ($students as $username => $spec) {
    foreach ($spec['courses'] as $shortname) {
        if (nexusdemo_enrol($courses[$shortname], $studentusers[$username], (int)$studentrole->id)) {
            $plan['changes'][] = "enrolled-student:{$username}:{$shortname}";
        }
    }
}

// Replace only the overview image area for the eight approved courses.
foreach ($courses as $shortname => $course) {
    $slug = $coursemap[$shortname];
    $context = context_course::instance($course->id);
    $targetname = $slug . '.webp';
    $existing = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'id ASC', false);
    $currentfile = count($existing) === 1 ? reset($existing) : null;
    $correct = $currentfile && $currentfile->get_filename() === $targetname &&
        $currentfile->get_mimetype() === 'image/webp';
    if (!$correct) {
        $fs->delete_area_files($context->id, 'course', 'overviewfiles', 0);
        $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $targetname,
            'mimetype' => 'image/webp',
        ], $packageroot . '/pix/banners/' . $targetname);
        $plan['changes'][] = "replaced-banner:{$shortname}";
    }
}

// Add three original assessed tasks without changing any existing MHF4U activity.
foreach ($assignments as $number => $spec) {
    $cm = nexusdemo_cm($mhf4u, 'assign', $spec['idnumber']);
    if (!$cm) {
        $intro = '<p>' . s($spec['summary']) . '</p>' .
            '<p><strong>Submission format:</strong> one PDF. Show reasoning, label graphs, state restrictions, and communicate conclusions in context.</p>' .
            '<p><a href="@@PLUGINFILE@@/' . s($spec['pdf']) . '">Download the original Nexus assignment handout (PDF)</a></p>' .
            '<p><em>Demo course content for teaching and quality-assurance purposes.</em></p>';
        $moduleinfo = (object)[
            'modulename' => 'assign',
            'module' => $DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST),
            'name' => $spec['name'],
            'intro' => $intro,
            'introformat' => FORMAT_HTML,
            'alwaysshowdescription' => 1,
            'submissiondrafts' => 1,
            'requiresubmissionstatement' => 0,
            'sendnotifications' => 0,
            'sendstudentnotifications' => 0,
            'sendlatenotifications' => 0,
            'duedate' => nexusdemo_time($spec['due']),
            'allowsubmissionsfromdate' => nexusdemo_time($spec['open']),
            'cutoffdate' => nexusdemo_time($spec['due']) + (7 * DAYSECS),
            'gradingduedate' => nexusdemo_time($spec['due']) + (3 * DAYSECS),
            'grade' => $spec['grade'],
            'teamsubmission' => 0,
            'requireallteammemberssubmit' => 0,
            'teamsubmissiongroupingid' => 0,
            'blindmarking' => 0,
            'attemptreopenmethod' => 'none',
            'maxattempts' => -1,
            'markingworkflow' => 0,
            'markingallocation' => 0,
            'assignsubmission_onlinetext_enabled' => 0,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 1,
            'assignsubmission_file_maxsizebytes' => 0,
            'assignsubmission_file_filetypes' => '.pdf',
            'assignfeedback_comments_enabled' => 1,
            'assignfeedback_editpdf_enabled' => 1,
            'section' => $spec['section'],
            'visible' => 1,
            'cmidnumber' => $spec['idnumber'],
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1,
        ];
        add_moduleinfo($moduleinfo, $mhf4u);
        $cm = nexusdemo_cm($mhf4u, 'assign', $spec['idnumber']);
        if (!$cm) {
            throw new RuntimeException("Assignment {$number} was not created.");
        }
        $plan['changes'][] = "created-assignment:{$number}";
    }

    $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
    $plannedopen = nexusdemo_time($spec['open']);
    if ((int)$assign->allowsubmissionsfromdate !== $plannedopen) {
        $update = clone $assign;
        $update->instance = $assign->id;
        $update->coursemodule = $cm->id;
        $update->allowsubmissionsfromdate = $plannedopen;
        $update->assignsubmission_file_enabled = 1;
        $update->assignsubmission_file_maxfiles = 1;
        $update->assignsubmission_file_maxsizebytes = 0;
        $update->assignsubmission_file_filetypes = '.pdf';
        $update->assignsubmission_onlinetext_enabled = 0;
        $update->assignfeedback_comments_enabled = 1;
        $update->assignfeedback_file_enabled = 0;
        $update->assignfeedback_editpdf_enabled = 1;
        assign_update_instance($update, null);
        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        $plan['changes'][] = "updated-assignment-open-date:{$number}";
    }
    $context = context_module::instance($cm->id);
    if (!$fs->file_exists($context->id, 'mod_assign', 'intro', 0, '/', $spec['pdf'])) {
        $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'mod_assign',
            'filearea' => 'intro',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $spec['pdf'],
        ], $packageroot . '/assets/assignments/' . $spec['pdf']);
        $plan['changes'][] = "attached-assignment-pdf:{$number}";
    }
    $assignments[$number]['cm'] = $cm;
    $assignments[$number]['record'] = $assign;
}

// Allow API-driven submissions during provisioning. The flag is restored after all records are complete.
foreach ($studentusers as $user) {
    unset_user_preference('auth_forcepasswordchange', $user);
}

// Submit the six approved fictional PDF records through the assignment external API.
foreach ($submissionplan as $submission) {
    $user = $studentusers[$submission['username']];
    $assignment = $assignments[$submission['assignment']];
    $record = $DB->get_record('assign_submission', [
        'assignment' => $assignment['record']->id,
        'userid' => $user->id,
        'groupid' => 0,
        'latest' => 1,
    ]);
    if ($record) {
        $context = context_module::instance($assignment['cm']->id);
        $existingfiles = $fs->get_area_files(
            $context->id,
            'assignsubmission_file',
            'submission_files',
            $record->id,
            'id ASC',
            false
        );
        $names = array_map(static fn(stored_file $file): string => $file->get_filename(), $existingfiles);
        if ($names && !in_array($submission['pdf'], $names, true)) {
            throw new RuntimeException("Refusing to overwrite an unexpected submission for {$submission['username']}.");
        }
        if ($record->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED && in_array($submission['pdf'], $names, true)) {
            continue;
        }
    }

    $usercontext = context_user::instance($user->id);
    $draftid = file_get_unused_draft_itemid();
    $fs->create_file_from_pathname([
        'contextid' => $usercontext->id,
        'component' => 'user',
        'filearea' => 'draft',
        'itemid' => $draftid,
        'filepath' => '/',
        'filename' => $submission['pdf'],
        'userid' => $user->id,
    ], $packageroot . '/assets/submissions/' . $submission['pdf']);

    \core\session\manager::set_user($user);
    $USER = $user;
    $modulecontext = context_module::instance($assignment['cm']->id);
    $PAGE->set_context($modulecontext);
    $assignapi = new assign($modulecontext, $assignment['cm'], $mhf4u);
    $assignapi->update_effective_access($user->id);
    if (!$assignapi->submissions_open($user->id)) {
        throw new RuntimeException("Submissions are not open for {$submission['username']}.");
    }
    $notices = [];
    $submissiondata = (object)['files_filemanager' => $draftid];
    if (!$assignapi->save_submission($submissiondata, $notices)) {
        throw new RuntimeException('Submission save failed: ' . implode('; ', $notices));
    }
    $submitdata = (object)['submissionstatement' => false];
    if (!$assignapi->submit_for_grading($submitdata, $notices)) {
        throw new RuntimeException('Submit for grading failed: ' . implode('; ', $notices));
    }
    $plan['changes'][] = "submitted:{$submission['username']}:{$submission['assignment']}";
    \core\session\manager::set_user($admin);
    $USER = $admin;
}

foreach ($studentusers as $user) {
    set_user_preference('auth_forcepasswordchange', 1, $user);
}

foreach ($courses as $course) {
    rebuild_course_cache((int)$course->id, true);
}
purge_all_caches();

$plan['change_count'] = count($plan['changes']);
echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
