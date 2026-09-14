<?php

/** Verify the Nexus MHF4U starter package without changing Moodle data. */

define('CLI_SCRIPT', true);

$root = getenv('NEXUS_MOODLE_ROOT') ?: '/var/www/moodle';
chdir($root);
require $root . '/config.php';

global $CFG, $DB;

$contentpath = getenv('NEXUS_MHF4U_CONTENT') ?: __DIR__ . '/mhf4u-starter-content.php';
$content = require $contentpath;
$failures = [];
$metrics = [];

$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$site = get_site();
$expectedwwwroot = getenv('NEXUS_EXPECTED_WWWROOT') ?: 'https://lms.nexuseps.com';
$check($CFG->wwwroot === $expectedwwwroot, 'Canonical Moodle URL is incorrect.');
$check($site->fullname === 'Nexus Education Private School', 'Full site name is incorrect.');
$check($site->shortname === 'Nexus EPS', 'Short site name is incorrect.');
$expecteddescription = 'Nexus Education Private School is an Ontario private school providing high-quality secondary education, online learning, high school credit courses, academic support, and flexible learning opportunities for students.';
$check(trim(strip_tags($site->summary)) === $expecteddescription, 'Site description is incorrect.');
$check(($CFG->lang ?? '') === 'en', 'Default language is not English.');
$check(($CFG->country ?? '') === 'CA', 'Default country is not Canada.');
$check((string)$DB->get_field('config', 'value', ['name' => 'supportemail']) === 'rushal@nexuseps.com', 'Support email is not the requested Nexus address.');

$adminids = array_map(static fn($user): int => (int)$user->id, get_admins());
$expectedusers = [
    'rushal' => ['Rushal', '', 'rushal@nexuseps.com', true],
    'radhika' => ['Radhika', '', 'radhika@nexuseps.com', true],
    'jainam' => ['Jainam', '', 'jainam@nexuseps.com', true],
    'adler' => ['Adler', '', 'adler@nexuseps.com', true],
    'teacher' => ['Teacher', 'Nexus', 'teacher@nexuseps.com', false],
    'student' => ['Student', 'Nexus', 'student@nexuseps.com', false],
];
$users = [];
foreach ($expectedusers as $username => [$firstname, $lastname, $email, $shouldbeadmin]) {
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    $check((bool)$user, "User {$username} is missing.");
    if (!$user) {
        continue;
    }
    $users[$username] = $user;
    $check($user->firstname === $firstname, "User {$username} has an incorrect first name.");
    $check($user->lastname === $lastname, "User {$username} has an incorrect last name.");
    $check($user->email === $email, "User {$username} has an incorrect email.");
    $check($user->auth === 'manual' && (bool)$user->confirmed && !(bool)$user->suspended, "User {$username} is not an active confirmed manual account.");
    $check($user->country === 'CA' && $user->lang === 'en', "User {$username} has incorrect locale settings.");
    $check(!(bool)get_user_preferences('auth_forcepasswordchange', false, $user->id), "User {$username} is forced to change password.");
    $check(in_array((int)$user->id, $adminids, true) === $shouldbeadmin, "User {$username} has an incorrect site-administrator state.");
}

$course = $DB->get_record('course', ['shortname' => 'MHF4U']);
$check((bool)$course, 'MHF4U course is missing.');
if ($course) {
    $check($course->fullname === 'Advanced Functions | MHF4U', 'MHF4U full name changed unexpectedly.');
    $metrics['courseid'] = (int)$course->id;
    $metrics['coursevisible'] = (bool)$course->visible;
    $metrics['sections'] = $DB->count_records('course_sections', ['course' => $course->id]);
    $check($metrics['sections'] === 8, 'MHF4U does not have exactly eight sections.');

    $expectedsections = [$content['welcome']];
    foreach ($content['units'] as $unit) {
        $expectedsections[] = $unit;
    }
    foreach ($expectedsections as $expectedsection) {
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $expectedsection['section']]);
        $check((bool)$section, 'Missing section ' . $expectedsection['section'] . '.');
        if ($section) {
            $check($section->name === $expectedsection['title'], 'Incorrect title for section ' . $expectedsection['section'] . '.');
        }
    }

    $modulecounts = [];
    $rows = $DB->get_records_sql(
        'SELECT m.name, COUNT(cm.id) AS total
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
          WHERE cm.course = :courseid AND cm.deletioninprogress = 0
       GROUP BY m.name',
        ['courseid' => $course->id]
    );
    foreach ($rows as $row) {
        $modulecounts[$row->name] = (int)$row->total;
    }
    $metrics['modules'] = $modulecounts;
    $check(($modulecounts['page'] ?? 0) === 15, 'Expected 15 MHF4U pages.');
    $check(($modulecounts['quiz'] ?? 0) === 7, 'Expected seven MHF4U quizzes.');
    $check(($modulecounts['assign'] ?? 0) === 7, 'Expected seven MHF4U assignments.');

    $quizrows = $DB->get_records_sql(
        'SELECT q.id, q.name, COUNT(qs.id) AS slots
           FROM {quiz} q
           JOIN {course_modules} cm ON cm.instance = q.id
           JOIN {modules} m ON m.id = cm.module AND m.name = :modname
      LEFT JOIN {quiz_slots} qs ON qs.quizid = q.id
          WHERE q.course = :courseid AND cm.idnumber LIKE :prefix
       GROUP BY q.id, q.name',
        ['modname' => 'quiz', 'courseid' => $course->id, 'prefix' => 'nexus_mhf4u_%']
    );
    $metrics['quizslots'] = [];
    foreach ($quizrows as $quizrow) {
        $metrics['quizslots'][$quizrow->name] = (int)$quizrow->slots;
        $check((int)$quizrow->slots >= 3, 'Quiz has fewer than three questions: ' . $quizrow->name);
    }

    $questioncount = $DB->count_records_sql(
        'SELECT COUNT(qbe.id)
           FROM {question_bank_entries} qbe
           JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
          WHERE qc.contextid = :contextid AND qbe.idnumber LIKE :prefix',
        ['contextid' => context_course::instance($course->id)->id, 'prefix' => 'nexus_mhf4u_%']
    );
    $metrics['questions'] = $questioncount;
    $check($questioncount === 24, 'Expected 24 versioned MHF4U question-bank entries.');

    if (isset($users['teacher'], $users['student'])) {
        $context = context_course::instance($course->id);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $check(user_has_role_assignment($users['teacher']->id, $teacherrole->id, $context->id), 'Teacher Nexus is not an editing teacher in MHF4U.');
        $check(!user_has_role_assignment($users['teacher']->id, $studentrole->id, $context->id), 'Teacher Nexus incorrectly has the student role in MHF4U.');
        $check(user_has_role_assignment($users['student']->id, $studentrole->id, $context->id), 'Student Nexus is not a student in MHF4U.');
        $check(!user_has_role_assignment($users['student']->id, $teacherrole->id, $context->id), 'Student Nexus incorrectly has the teacher role in MHF4U.');
        $check(is_enrolled($context, $users['teacher'], '', true), 'Teacher Nexus is not actively enrolled in MHF4U.');
        $check(has_capability('moodle/course:update', $context, $users['teacher']->id), 'Teacher Nexus cannot manage MHF4U.');
        $check(is_enrolled($context, $users['student'], '', true), 'Student Nexus is not actively enrolled in MHF4U.');
        $check(!has_capability('moodle/course:update', $context, $users['student']->id), 'Student Nexus can incorrectly manage MHF4U.');

        $assigncm = $DB->get_record_sql(
            'SELECT cm.*
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = :courseid AND m.name = :modname AND cm.idnumber LIKE :prefix',
            ['courseid' => $course->id, 'modname' => 'assign', 'prefix' => 'nexus_mhf4u_%'],
            IGNORE_MULTIPLE
        );
        $check((bool)$assigncm, 'No MHF4U assignment context is available for capability checks.');
        if ($assigncm) {
            $assigncontext = context_module::instance($assigncm->id);
            $check(has_capability('mod/assign:grade', $assigncontext, $users['teacher']->id), 'Teacher Nexus cannot grade MHF4U assignments.');
            $check(!has_capability('mod/assign:grade', $assigncontext, $users['student']->id), 'Student Nexus can incorrectly grade MHF4U assignments.');
            $check(has_capability('mod/assign:submit', $assigncontext, $users['student']->id), 'Student Nexus cannot submit MHF4U assignments.');
        }

        $teacheroutside = $DB->count_records_sql(
            'SELECT COUNT(ra.id)
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE ra.userid = :userid
                AND ctx.contextlevel = :contextlevel
                AND ctx.instanceid <> :courseid',
            ['userid' => $users['teacher']->id, 'contextlevel' => CONTEXT_COURSE, 'courseid' => $course->id]
        );
        $check($teacheroutside === 0, 'Teacher Nexus has a role assignment outside MHF4U.');
    }
}

$systemcontext = context_system::instance();
$brandareas = [
    ['core_admin', 'logo'],
    ['core_admin', 'logocompact'],
    ['core_admin', 'favicon'],
    ['theme_moove', 'logo'],
    ['theme_moove', 'favicon'],
];
foreach ($brandareas as [$component, $filearea]) {
    $files = get_file_storage()->get_area_files($systemcontext->id, $component, $filearea, 0, 'id', false);
    $check(count($files) === 1, "Brand file area {$component}/{$filearea} does not contain exactly one file.");
}

$check((string)get_config('hub', 'site_organisationtype') === '5', 'Registration profile organisation type is not High school.');
$check((string)get_config('hub', 'site_privacy') === 'linked', 'Registration profile is not set to display the site name with its link.');
$check((string)get_config('hub', 'site_emailalert') === '1', 'Important Moodle registration alerts are not enabled in the local profile.');
$check((string)get_config('hub', 'site_commnews') === '0', 'Moodle communication news is unexpectedly enabled.');

$result = [
    'status' => $failures ? 'failed' : 'passed',
    'metrics' => $metrics,
    'failures' => array_values(array_filter($failures)),
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failures ? 1 : 0);
