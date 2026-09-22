<?php

/**
 * Idempotently provision the Nexus MHF4U starter course and requested accounts.
 *
 * Passwords are read only from environment variables and are never logged:
 * NEXUS_PASSWORD_RUSHAL, NEXUS_PASSWORD_RADHIKA, NEXUS_PASSWORD_JAINAM,
 * NEXUS_PASSWORD_ADLER, NEXUS_PASSWORD_TEACHER, NEXUS_PASSWORD_STUDENT.
 */

define('CLI_SCRIPT', true);

$options = getopt('', ['dry-run']);
$dryrun = array_key_exists('dry-run', $options);
$root = getenv('NEXUS_MOODLE_ROOT') ?: '/var/www/moodle';

chdir($root);
require $root . '/config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/course/modlib.php';
require_once $CFG->dirroot . '/user/lib.php';
require_once $CFG->libdir . '/enrollib.php';
require_once $CFG->libdir . '/questionlib.php';
require_once $CFG->dirroot . '/question/editlib.php';
require_once $CFG->dirroot . '/question/engine/bank.php';
require_once $CFG->dirroot . '/mod/quiz/locallib.php';

global $CFG, $DB, $USER;

if (getenv('NEXUS_DEBUG') === '1') {
    $CFG->debug = DEBUG_DEVELOPER;
    $CFG->debugdisplay = 1;
    error_reporting(E_ALL);
}

$admin = get_admin();
\core\session\manager::set_user($admin);
$USER = $admin;

$contentpath = getenv('NEXUS_MHF4U_CONTENT') ?: __DIR__ . '/mhf4u-starter-content.php';
if (!is_readable($contentpath)) {
    throw new RuntimeException('MHF4U content data file is not readable.');
}
$content = require $contentpath;

$courses = $DB->get_records('course', ['shortname' => 'MHF4U']);
if (count($courses) !== 1) {
    throw new RuntimeException('Expected exactly one MHF4U course; found ' . count($courses) . '.');
}
$course = reset($courses);
$coursevisible = (bool)$course->visible;

$accounts = [
    ['username' => 'rushal', 'firstname' => 'Rushal', 'lastname' => '', 'email' => 'rushal@nexuseps.com', 'passwordenv' => 'NEXUS_PASSWORD_RUSHAL', 'admin' => true],
    ['username' => 'radhika', 'firstname' => 'Radhika', 'lastname' => '', 'email' => 'radhika@nexuseps.com', 'passwordenv' => 'NEXUS_PASSWORD_RADHIKA', 'admin' => true],
    ['username' => 'jainam', 'firstname' => 'Jainam', 'lastname' => '', 'email' => 'jainam@nexuseps.com', 'passwordenv' => 'NEXUS_PASSWORD_JAINAM', 'admin' => true],
    ['username' => 'adler', 'firstname' => 'Adler', 'lastname' => '', 'email' => 'adler@nexuseps.com', 'passwordenv' => 'NEXUS_PASSWORD_ADLER', 'admin' => true],
    ['username' => 'teacher', 'firstname' => 'Teacher', 'lastname' => 'Nexus', 'email' => 'teacher@nexuseps.com', 'passwordenv' => 'NEXUS_PASSWORD_TEACHER', 'admin' => false],
    ['username' => 'student', 'firstname' => 'Student', 'lastname' => 'Nexus', 'email' => 'student@nexuseps.com', 'passwordenv' => 'NEXUS_PASSWORD_STUDENT', 'admin' => false],
];

$missingsecrets = [];
foreach ($accounts as $account) {
    if ((string)getenv($account['passwordenv']) === '') {
        $missingsecrets[] = $account['passwordenv'];
    }
}

if ($dryrun) {
    echo json_encode([
        'mode' => 'dry-run',
        'courseid' => (int)$course->id,
        'coursevisible' => $coursevisible,
        'existingsections' => $DB->count_records('course_sections', ['course' => $course->id]),
        'plannedsections' => 8,
        'plannedpages' => 15,
        'plannedquizzes' => 7,
        'plannedassignments' => 7,
        'plannedquestions' => 24,
        'missingsecretvariables' => $missingsecrets,
    ], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

if ($missingsecrets) {
    throw new RuntimeException('Required password environment variables are missing: ' . implode(', ', $missingsecrets));
}

/** Return an existing course module matching the stable idnumber. */
function nexus_find_activity(stdClass $course, string $modname, string $idnumber): ?stdClass {
    global $DB;
    $sql = 'SELECT cm.*
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid
               AND cm.idnumber = :idnumber
               AND cm.deletioninprogress = 0
               AND m.name = :modname';
    return $DB->get_record_sql($sql, [
        'courseid' => $course->id,
        'idnumber' => $idnumber,
        'modname' => $modname,
    ]) ?: null;
}

/** Create an accessible Moodle page when it does not already exist. */
function nexus_ensure_page(stdClass $course, int $section, string $idnumber, string $name, string $html): bool {
    global $DB;
    if (nexus_find_activity($course, 'page', $idnumber)) {
        return false;
    }
    $moduleinfo = (object)[
        'modulename' => 'page',
        'module' => $DB->get_field('modules', 'id', ['name' => 'page'], MUST_EXIST),
        'name' => $name,
        'intro' => '',
        'introformat' => FORMAT_HTML,
        'content' => $html,
        'contentformat' => FORMAT_HTML,
        'display' => 0,
        'printintro' => 0,
        'printlastmodified' => 0,
        'section' => $section,
        'visible' => 1,
        'cmidnumber' => $idnumber,
        'completion' => COMPLETION_TRACKING_AUTOMATIC,
        'completionview' => 1,
    ];
    add_moduleinfo($moduleinfo, $course);
    return true;
}

/** Create a graded Moodle assignment with file and online-text submissions. */
function nexus_ensure_assignment(stdClass $course, int $section, string $idnumber, string $name, string $html): bool {
    global $DB;
    if (nexus_find_activity($course, 'assign', $idnumber)) {
        return false;
    }
    $moduleinfo = (object)[
        'modulename' => 'assign',
        'module' => $DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST),
        'name' => $name,
        'intro' => $html,
        'introformat' => FORMAT_HTML,
        'alwaysshowdescription' => 1,
        'submissiondrafts' => 0,
        'requiresubmissionstatement' => 1,
        'sendnotifications' => 0,
        'sendstudentnotifications' => 1,
        'sendlatenotifications' => 0,
        'duedate' => 0,
        'allowsubmissionsfromdate' => 0,
        'cutoffdate' => 0,
        'gradingduedate' => 0,
        'grade' => 100,
        'teamsubmission' => 0,
        'requireallteammemberssubmit' => 0,
        'teamsubmissiongroupingid' => 0,
        'blindmarking' => 0,
        'attemptreopenmethod' => 'untilpass',
        'maxattempts' => 1,
        'markingworkflow' => 0,
        'markingallocation' => 0,
        'markinganonymous' => 0,
        'activityformat' => 0,
        'timelimit' => 0,
        'submissionattachments' => 0,
        'assignsubmission_onlinetext_enabled' => 1,
        'assignsubmission_file_enabled' => 1,
        'assignsubmission_file_maxfiles' => 3,
        'assignsubmission_file_maxsizebytes' => 0,
        'section' => $section,
        'visible' => 1,
        'cmidnumber' => $idnumber,
        'completion' => COMPLETION_TRACKING_AUTOMATIC,
        'completionsubmit' => 1,
    ];
    add_moduleinfo($moduleinfo, $course);
    return true;
}

/** Create a quiz using safe Moodle defaults. */
function nexus_ensure_quiz(stdClass $course, int $section, string $idnumber, string $name, string $intro): array {
    global $DB;
    $cm = nexus_find_activity($course, 'quiz', $idnumber);
    if ($cm) {
        return [$DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST), false];
    }
    $moduleinfo = (object)[
        'modulename' => 'quiz',
        'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST),
        'name' => $name,
        'intro' => $intro,
        'introformat' => FORMAT_HTML,
        'timeopen' => 0,
        'timeclose' => 0,
        'preferredbehaviour' => 'deferredfeedback',
        'attempts' => 0,
        'attemptonlast' => 0,
        'grademethod' => QUIZ_GRADEHIGHEST,
        'decimalpoints' => 2,
        'questiondecimalpoints' => -1,
        'attemptduring' => 1,
        'correctnessduring' => 1,
        'maxmarksduring' => 1,
        'marksduring' => 1,
        'specificfeedbackduring' => 1,
        'generalfeedbackduring' => 1,
        'rightanswerduring' => 1,
        'overallfeedbackduring' => 0,
        'attemptimmediately' => 1,
        'correctnessimmediately' => 1,
        'maxmarksimmediately' => 1,
        'marksimmediately' => 1,
        'specificfeedbackimmediately' => 1,
        'generalfeedbackimmediately' => 1,
        'rightanswerimmediately' => 1,
        'overallfeedbackimmediately' => 1,
        'attemptopen' => 1,
        'correctnessopen' => 1,
        'maxmarksopen' => 1,
        'marksopen' => 1,
        'specificfeedbackopen' => 1,
        'generalfeedbackopen' => 1,
        'rightansweropen' => 1,
        'overallfeedbackopen' => 1,
        'attemptclosed' => 1,
        'correctnessclosed' => 1,
        'maxmarksclosed' => 1,
        'marksclosed' => 1,
        'specificfeedbackclosed' => 1,
        'generalfeedbackclosed' => 1,
        'rightanswerclosed' => 1,
        'overallfeedbackclosed' => 1,
        'questionsperpage' => 1,
        'shuffleanswers' => 1,
        'sumgrades' => 0,
        'grade' => 10,
        'timelimit' => 0,
        'overduehandling' => 'autosubmit',
        'graceperiod' => 86400,
        'quizpassword' => '',
        'subnet' => '',
        'browsersecurity' => '',
        'delay1' => 0,
        'delay2' => 0,
        'showuserpicture' => 0,
        'showblocks' => 0,
        'navmethod' => QUIZ_NAVMETHOD_FREE,
        'section' => $section,
        'visible' => 1,
        'cmidnumber' => $idnumber,
        'completion' => COMPLETION_TRACKING_AUTOMATIC,
        'completionusegrade' => 1,
    ];
    $created = add_moduleinfo($moduleinfo, $course);
    return [$DB->get_record('quiz', ['id' => $created->instance], '*', MUST_EXIST), true];
}

/** Create or find the dedicated MHF4U question category. */
function nexus_ensure_question_category(context_course $context): stdClass {
    global $DB;
    $category = $DB->get_record('question_categories', [
        'contextid' => $context->id,
        'idnumber' => 'nexus_mhf4u_starter',
    ]);
    if ($category) {
        return $category;
    }
    $top = question_get_top_category($context->id, true);
    $manager = new \core_question\category_manager();
    $record = (object)[
        'name' => 'Nexus MHF4U Starter Questions',
        'info' => 'Original checkpoint and cumulative review questions for the Nexus MHF4U starter course.',
        'infoformat' => FORMAT_HTML,
        'stamp' => make_unique_id_code(),
        'idnumber' => 'nexus_mhf4u_starter',
        'parent' => $top->id,
        'contextid' => $context->id,
        'sortorder' => $manager->get_max_sortorder($top->id) + 1,
    ];
    $record->id = $DB->insert_record('question_categories', $record);
    return $record;
}

/** Find the latest version of a stable question bank entry. */
function nexus_find_question(stdClass $category, string $idnumber): ?stdClass {
    global $DB;
    $sql = 'SELECT q.*
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
             WHERE qbe.questioncategoryid = :categoryid
               AND qbe.idnumber = :idnumber
          ORDER BY qv.version DESC';
    $records = $DB->get_records_sql($sql, ['categoryid' => $category->id, 'idnumber' => $idnumber], 0, 1);
    return $records ? reset($records) : null;
}

/** Create one versioned multiple-choice question through the question-type API. */
function nexus_ensure_multichoice(stdClass $category, context_course $context, string $idnumber, array $data): array {
    $existing = nexus_find_question($category, $idnumber);
    if ($existing) {
        return [$existing, false];
    }
    $question = (object)[
        'qtype' => 'multichoice',
        'createdby' => 0,
        'idnumber' => $idnumber,
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
    ];
    $answers = [];
    $feedback = [];
    $fractions = [];
    foreach ($data['answers'] as $index => $answer) {
        $answers[] = ['text' => $answer, 'format' => FORMAT_PLAIN];
        $feedback[] = [
            'text' => $index === $data['correct'] ? $data['feedback'] : 'Review the relevant lesson and check each condition carefully.',
            'format' => FORMAT_HTML,
        ];
        $fractions[] = $index === $data['correct'] ? '1.0' : '0.0';
    }
    $form = (object)[
        'name' => $data['name'],
        'questiontext' => ['text' => '<p>' . s($data['text']) . '</p>', 'format' => FORMAT_HTML],
        'generalfeedback' => ['text' => '<p>' . s($data['feedback']) . '</p>', 'format' => FORMAT_HTML],
        'defaultmark' => 1,
        'penalty' => 0.3333333,
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        'category' => $category->id . ',' . $context->id,
        'idnumber' => $idnumber,
        'shuffleanswers' => 1,
        'answernumbering' => 'abc',
        'showstandardinstruction' => 1,
        'single' => '1',
        'correctfeedback' => ['text' => 'Correct. Your reasoning matches the required concept.', 'format' => FORMAT_HTML],
        'partiallycorrectfeedback' => ['text' => 'Partly correct. Review every condition before trying again.', 'format' => FORMAT_HTML],
        'shownumcorrect' => 1,
        'incorrectfeedback' => ['text' => 'Not yet. Review the worked example and retry.', 'format' => FORMAT_HTML],
        'fraction' => $fractions,
        'answer' => $answers,
        'feedback' => $feedback,
        'hint' => [],
        'hintclearwrong' => [],
        'hintshownumcorrect' => [],
    ];
    $saved = question_bank::get_qtype('multichoice')->save_question($question, $form);
    return [$saved, true];
}

/** Create/update a local manual account without logging the password. */
function nexus_ensure_user(array $account): array {
    global $CFG, $DB;
    $user = $DB->get_record('user', ['username' => $account['username'], 'deleted' => 0]);
    $fields = (object)[
        'auth' => 'manual',
        'confirmed' => 1,
        'suspended' => 0,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => $account['username'],
        'password' => (string)getenv($account['passwordenv']),
        'firstname' => $account['firstname'],
        'lastname' => $account['lastname'],
        'email' => $account['email'],
        'emailstop' => 0,
        'maildisplay' => 2,
        'city' => 'Toronto',
        'country' => 'CA',
        'lang' => 'en',
        'timezone' => 'America/Toronto',
    ];
    if ($user) {
        $fields->id = $user->id;
        user_update_user($fields, true, false);
        $created = false;
    } else {
        $fields->id = user_create_user($fields, true, false);
        $created = true;
    }
    unset_user_preference('auth_forcepasswordchange', $fields->id);
    return [$DB->get_record('user', ['id' => $fields->id], '*', MUST_EXIST), $created];
}

/** Ensure a user is manually enrolled with the requested role. */
function nexus_ensure_enrolment(stdClass $course, stdClass $user, string $roleshortname): void {
    global $DB;
    $role = $DB->get_record('role', ['shortname' => $roleshortname], '*', MUST_EXIST);
    $manual = enrol_get_plugin('manual');
    if (!$manual) {
        throw new RuntimeException('Manual enrolment plugin is unavailable.');
    }
    $instance = null;
    foreach (enrol_get_instances($course->id, true) as $candidate) {
        if ($candidate->enrol === 'manual') {
            $instance = $candidate;
            break;
        }
    }
    if (!$instance) {
        $instanceid = $manual->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
    }
    $manual->enrol_user($instance, $user->id, $role->id, 0, 0, ENROL_USER_ACTIVE);
}

/** Store a branded image in a Moodle admin/theme file area. */
function nexus_store_brand_file(string $component, string $filearea, string $configplugin, string $configname, string $source, string $filename): void {
    if (!is_readable($source)) {
        throw new RuntimeException('Brand asset is not readable: ' . $source);
    }
    $context = context_system::instance();
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, $component, $filearea, 0);
    $record = [
        'contextid' => $context->id,
        'component' => $component,
        'filearea' => $filearea,
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $filename,
    ];
    $fs->create_file_from_pathname($record, $source);
    set_config($configname, '/' . $filename, $configplugin);
}

$summary = [
    'courseid' => (int)$course->id,
    'coursevisible_preserved' => $coursevisible,
    'users_created' => [],
    'users_updated' => [],
    'sections_configured' => 0,
    'pages_created' => 0,
    'quizzes_created' => 0,
    'assignments_created' => 0,
    'questions_created' => 0,
];

$transaction = $DB->start_delegated_transaction();

$site = get_site();
$site->fullname = 'Nexus Education Private School';
$site->shortname = 'Nexus EPS';
$site->summary = 'Nexus Education Private School is an Ontario private school providing high-quality secondary education, online learning, high school credit courses, academic support, and flexible learning opportunities for students.';
$site->summaryformat = FORMAT_HTML;
$DB->update_record('course', $site);
set_config('lang', 'en');
set_config('country', 'CA');
set_config('theme', 'moove');
set_config('supportemail', 'rushal@nexuseps.com');

$registration = (object)[
    'policyagreed' => 0,
    'language' => 'en',
    'countrycode' => 'CA',
    'privacy' => 'linked',
    'contactemail' => 'rushal@nexuseps.com',
    'emailalert' => 1,
    'emailalertemail' => 'rushal@nexuseps.com',
    'commnews' => 0,
    'commnewsemail' => '',
    'contactname' => 'Rushal',
    'name' => 'Nexus Education Private School',
    'description' => $site->summary,
    'imageurl' => '',
    'contactphone' => '',
    'regioncode' => '-',
    'geolocation' => '',
    'street' => '',
    'organisationtype' => 5,
];
\core\hub\registration::save_site_info($registration);

$provisionedusers = [];
foreach ($accounts as $account) {
    [$user, $created] = nexus_ensure_user($account);
    $provisionedusers[$account['username']] = $user;
    $summary[$created ? 'users_created' : 'users_updated'][] = $account['username'];
}

$siteadminids = array_values(array_unique(array_map('intval', preg_split('/\s*,\s*/', (string)get_config('core', 'siteadmins'), -1, PREG_SPLIT_NO_EMPTY))));
$nonadminids = [(int)$provisionedusers['teacher']->id, (int)$provisionedusers['student']->id];
$siteadminids = array_values(array_diff($siteadminids, $nonadminids));
foreach (['rushal', 'radhika', 'jainam', 'adler'] as $username) {
    $siteadminids[] = (int)$provisionedusers[$username]->id;
}
$siteadminids[] = (int)$admin->id;
$siteadminids = array_values(array_unique($siteadminids));
sort($siteadminids, SORT_NUMERIC);
set_config('siteadmins', implode(',', $siteadminids));

$coursecontext = context_course::instance($course->id);
nexus_ensure_enrolment($course, $provisionedusers['teacher'], 'editingteacher');
nexus_ensure_enrolment($course, $provisionedusers['student'], 'student');
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
role_unassign($studentrole->id, $provisionedusers['teacher']->id, $coursecontext->id);
role_unassign($teacherrole->id, $provisionedusers['student']->id, $coursecontext->id);

course_create_sections_if_missing($course, range(0, 7));
for ($sectionnumber = 1; $sectionnumber <= 7; $sectionnumber++) {
    if (!$DB->record_exists('course_sections', ['course' => $course->id, 'section' => $sectionnumber])) {
        course_create_section($course, $sectionnumber, true);
    }
}
$sections = [$content['welcome']];
foreach ($content['units'] as $unit) {
    $sections[] = $unit;
}
foreach ($sections as $sectiondata) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectiondata['section']], '*', MUST_EXIST);
    course_update_section($course, $section, [
        'name' => $sectiondata['title'],
        'summary' => '<p>' . s($sectiondata['summary']) . '</p>',
        'summaryformat' => FORMAT_HTML,
        'visible' => 1,
    ]);
    $summary['sections_configured']++;
}

if (nexus_ensure_page($course, 0, 'nexus_mhf4u_welcome', 'Course Welcome and Overview', $content['welcome']['page'])) {
    $summary['pages_created']++;
}

$questioncategory = nexus_ensure_question_category($coursecontext);
foreach ($content['units'] as $unit) {
    $section = (int)$unit['section'];
    $slug = 'unit' . $section;
    if (nexus_ensure_page($course, $section, "nexus_mhf4u_{$slug}_lesson", $unit['title'] . ' Lesson and Worked Examples', $unit['lesson'])) {
        $summary['pages_created']++;
    }
    if (nexus_ensure_page($course, $section, "nexus_mhf4u_{$slug}_practice", $unit['title'] . ' Guided Practice', $unit['practice'])) {
        $summary['pages_created']++;
    }
    [$quiz, $quizcreated] = nexus_ensure_quiz(
        $course,
        $section,
        "nexus_mhf4u_{$slug}_quiz",
        $unit['title'] . ' Checkpoint Quiz',
        '<p>Use this checkpoint to test the core outcomes from the unit. Show supporting work in your notes and review the feedback after each attempt.</p>'
    );
    if ($quizcreated) {
        $summary['quizzes_created']++;
    }
    foreach ($unit['quiz'] as $index => $questiondata) {
        $questionidnumber = "nexus_mhf4u_{$slug}_q" . ($index + 1);
        [$question, $questioncreated] = nexus_ensure_multichoice($questioncategory, $coursecontext, $questionidnumber, $questiondata);
        if ($questioncreated) {
            $summary['questions_created']++;
        }
        quiz_add_quiz_question($question->id, $quiz, 0, 1.0);
    }
    \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

    if (nexus_ensure_assignment(
        $course,
        $section,
        "nexus_mhf4u_{$slug}_assignment",
        $unit['title'] . ' Graded Application',
        $unit['assignment']
    )) {
        $summary['assignments_created']++;
    }
}

$brandroot = $CFG->dirroot . '/local/nexusbranding/pix';
nexus_store_brand_file('core_admin', 'logo', 'core_admin', 'logo', $brandroot . '/logo.png', 'nexus-logo.png');
nexus_store_brand_file('core_admin', 'logocompact', 'core_admin', 'logocompact', $brandroot . '/site-icon.png', 'nexus-compact.png');
nexus_store_brand_file('core_admin', 'favicon', 'core_admin', 'favicon', $brandroot . '/site-icon.png', 'nexus-favicon.png');
nexus_store_brand_file('theme_moove', 'logo', 'theme_moove', 'logo', $brandroot . '/logo.png', 'nexus-logo.png');
nexus_store_brand_file('theme_moove', 'favicon', 'theme_moove', 'favicon', $brandroot . '/site-icon.png', 'nexus-favicon.png');

$DB->set_field('course', 'visible', $coursevisible ? 1 : 0, ['id' => $course->id]);
$transaction->allow_commit();

rebuild_course_cache($course->id, true);
purge_all_caches();

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
