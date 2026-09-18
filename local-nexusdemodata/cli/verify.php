<?php

/**
 * Read-only verification for the Nexus visual and demo-learning package.
 */

declare(strict_types=1);

define('CLI_SCRIPT', true);

$root = getenv('NEXUS_MOODLE_ROOT') ?: dirname(__DIR__, 3);
if (!is_readable($root . '/config.php')) {
    throw new RuntimeException('Moodle config.php is not readable at the resolved root.');
}
require $root . '/config.php';
require_once $CFG->libdir . '/enrollib.php';
require_once $CFG->dirroot . '/mod/assign/locallib.php';

global $CFG, $DB;

$coursemap = [
    'MHF4U' => 'mhf4u', 'SBI4U' => 'sbi4u', 'SCH4U' => 'sch4u', 'ENG4U' => 'eng4u',
    'ASM4M' => 'asm4m', 'SPH4U' => 'sph4u', 'TDJ4M' => 'tdj4m', 'AVI1O' => 'avi1o',
];
$studentmatrix = [
    'ethan.campbell' => ['MHF4U', 'SBI4U', 'SPH4U'],
    'olivia.bennett' => ['MHF4U', 'SCH4U', 'ENG4U', 'ASM4M'],
    'liam.foster' => ['MHF4U', 'SBI4U', 'TDJ4M'],
    'chloe.martin' => ['MHF4U', 'ENG4U', 'AVI1O'],
];
$assignmentplan = [
    1 => ['idnumber' => 'nexus_demo_assignment_1', 'due' => '2026-09-18 23:59:00', 'grade' => 40, 'pdf' => 'assignment-1-function-transformations.pdf'],
    2 => ['idnumber' => 'nexus_demo_assignment_2', 'due' => '2026-09-25 23:59:00', 'grade' => 50, 'pdf' => 'assignment-2-polynomial-rational-functions.pdf'],
    3 => ['idnumber' => 'nexus_demo_assignment_3', 'due' => '2026-10-02 23:59:00', 'grade' => 45, 'pdf' => 'assignment-3-exp-log-trig-applications.pdf'],
];
$submissionmatrix = [
    1 => ['ethan.campbell', 'olivia.bennett', 'chloe.martin'],
    2 => ['olivia.bennett', 'liam.foster'],
    3 => ['chloe.martin'],
];

$failures = [];
$checks = [];
$fs = get_file_storage();
$siteadmins = array_fill_keys(array_map('intval', array_keys(get_admins())), true);
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
$managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);

/** Record one verification result. */
function nexusverify(bool $condition, string $label, array &$checks, array &$failures, mixed $detail = null): void {
    $checks[] = ['check' => $label, 'pass' => $condition, 'detail' => $detail];
    if (!$condition) {
        $failures[] = $label;
    }
}

/** Resolve exactly one course. */
function nexusverify_course(string $shortname): stdClass {
    global $DB;
    $records = $DB->get_records('course', ['shortname' => $shortname]);
    if (count($records) !== 1) {
        throw new RuntimeException("Expected exactly one {$shortname}; found " . count($records) . '.');
    }
    return reset($records);
}

/** Resolve a stable course module. */
function nexusverify_cm(stdClass $course, string $idnumber): ?stdClass {
    global $DB;
    $sql = 'SELECT cm.* FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid AND cm.idnumber = :idnumber
                   AND cm.deletioninprogress = 0 AND m.name = :modname';
    return $DB->get_record_sql($sql, [
        'courseid' => $course->id, 'idnumber' => $idnumber, 'modname' => 'assign',
    ]) ?: null;
}

/** Convert Toronto local time to Unix time. */
function nexusverify_time(string $value): int {
    return (new DateTimeImmutable($value, new DateTimeZone('America/Toronto')))->getTimestamp();
}

$courses = [];
foreach ($coursemap as $shortname => $slug) {
    $course = nexusverify_course($shortname);
    $courses[$shortname] = $course;
    $context = context_course::instance($course->id);
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'id ASC', false);
    $file = count($files) === 1 ? reset($files) : null;
    $content = $file ? $file->get_content() : '';
    $validanimatedwebp = $file && substr($content, 0, 4) === 'RIFF' && substr($content, 8, 4) === 'WEBP' &&
        str_contains($content, 'ANIM');
    nexusverify(
        $file && $file->get_filename() === $slug . '.webp' && $file->get_mimetype() === 'image/webp' &&
            $file->get_filesize() > 20000 && $validanimatedwebp,
        "course-banner:{$shortname}",
        $checks,
        $failures,
        $file ? ['filename' => $file->get_filename(), 'mimetype' => $file->get_mimetype(), 'bytes' => $file->get_filesize(), 'animated_webp' => $validanimatedwebp] : ['file_count' => count($files)]
    );
    $fallback = dirname(__DIR__) . '/pix/banners/' . $slug . '-static.webp';
    nexusverify(is_readable($fallback) && filesize($fallback) > 20000, "course-fallback:{$shortname}", $checks, $failures);
}

$teacher = $DB->get_record('user', ['username' => 'teacher', 'deleted' => 0]);
nexusverify((bool)$teacher, 'teacher-exists', $checks, $failures);
if ($teacher) {
    nexusverify(!isset($siteadmins[(int)$teacher->id]), 'teacher-not-site-admin', $checks, $failures);
    nexusverify(!$DB->record_exists('role_assignments', ['userid' => $teacher->id, 'roleid' => $managerrole->id]), 'teacher-not-manager', $checks, $failures);
    foreach ($courses as $shortname => $course) {
        $context = context_course::instance($course->id);
        nexusverify(
            user_has_role_assignment($teacher->id, $teacherrole->id, $context->id),
            "teacher-role:{$shortname}",
            $checks,
            $failures
        );
        nexusverify(has_capability('moodle/course:update', $context, $teacher), "teacher-capability:{$shortname}", $checks, $failures);
    }
}

foreach ($studentmatrix as $username => $expectedshortnames) {
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    nexusverify((bool)$user, "student-exists:{$username}", $checks, $failures);
    if (!$user) {
        continue;
    }
    nexusverify(!isset($siteadmins[(int)$user->id]), "student-not-admin:{$username}", $checks, $failures);
    nexusverify(!$DB->record_exists_select('role_assignments', 'userid = :userid AND roleid IN (:manager, :teacher)', [
        'userid' => $user->id, 'manager' => $managerrole->id, 'teacher' => $teacherrole->id,
    ]), "student-no-privileged-role:{$username}", $checks, $failures);

    $actual = [];
    foreach ($courses as $shortname => $course) {
        $context = context_course::instance($course->id);
        if (user_has_role_assignment($user->id, $studentrole->id, $context->id)) {
            $actual[] = $shortname;
        }
        nexusverify(!has_capability('moodle/course:update', $context, $user), "student-cannot-update:{$username}:{$shortname}", $checks, $failures);
    }
    sort($actual);
    sort($expectedshortnames);
    nexusverify($actual === $expectedshortnames, "student-course-matrix:{$username}", $checks, $failures, $actual);
}

$mhf4u = $courses['MHF4U'];
$modulecounts = $DB->get_records_sql(
    'SELECT m.name AS modname, COUNT(1) AS modulecount
       FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
      WHERE cm.course = :courseid AND cm.deletioninprogress = 0
   GROUP BY m.name',
    ['courseid' => $mhf4u->id]
);
nexusverify($DB->count_records('course_sections', ['course' => $mhf4u->id]) >= 8, 'mhf4u-seven-units-plus-general', $checks, $failures);
nexusverify(isset($modulecounts['page']) && (int)$modulecounts['page']->modulecount >= 15, 'mhf4u-pages-preserved', $checks, $failures);
nexusverify(isset($modulecounts['quiz']) && (int)$modulecounts['quiz']->modulecount >= 7, 'mhf4u-quizzes-preserved', $checks, $failures);
nexusverify(isset($modulecounts['assign']) && (int)$modulecounts['assign']->modulecount >= 10, 'mhf4u-assignments-expanded', $checks, $failures);

foreach ($assignmentplan as $number => $spec) {
    $cm = nexusverify_cm($mhf4u, $spec['idnumber']);
    nexusverify((bool)$cm, "assignment-exists:{$number}", $checks, $failures);
    if (!$cm) {
        continue;
    }
    $assignment = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);
    nexusverify((int)$assignment->duedate === nexusverify_time($spec['due']), "assignment-due:{$number}", $checks, $failures, userdate($assignment->duedate));
    nexusverify((float)$assignment->grade === (float)$spec['grade'], "assignment-grade:{$number}", $checks, $failures, $assignment->grade);
    nexusverify(!str_contains($assignment->intro, 'draftfile.php'), "assignment-no-draft-link:{$number}", $checks, $failures);
    $handout = $fs->get_file($context->id, 'mod_assign', 'intro', 0, '/', $spec['pdf']);
    nexusverify($handout && $handout->get_mimetype() === 'application/pdf' && $handout->get_filesize() > 50000, "assignment-handout:{$number}", $checks, $failures);

    $expected = $submissionmatrix[$number];
    $actual = [];
    $submissions = $DB->get_records('assign_submission', [
        'assignment' => $assignment->id,
        'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
        'latest' => 1,
    ]);
    foreach ($submissions as $submission) {
        $user = $DB->get_record('user', ['id' => $submission->userid, 'deleted' => 0]);
        if ($user) {
            $actual[] = $user->username;
            $files = $fs->get_area_files($context->id, 'assignsubmission_file', 'submission_files', $submission->id, 'id ASC', false);
            nexusverify(count($files) === 1, "submission-one-file:{$user->username}:{$number}", $checks, $failures);
            if ($files) {
                $file = reset($files);
                nexusverify($file->get_mimetype() === 'application/pdf' && $file->get_filesize() > 50000, "submission-valid-pdf:{$user->username}:{$number}", $checks, $failures);
            }
        }
    }
    sort($actual);
    sort($expected);
    nexusverify($actual === $expected, "submission-matrix:{$number}", $checks, $failures, $actual);
}

$result = [
    'status' => $failures ? 'FAIL' : 'PASS',
    'check_count' => count($checks),
    'failure_count' => count($failures),
    'failures' => $failures,
    'checks' => $checks,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failures ? 1 : 0);
