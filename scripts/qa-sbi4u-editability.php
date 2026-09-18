<?php

/**
 * Reversible production QA for SBI4U editing-teacher activity management.
 * Creates one hidden temporary Page, confirms it, and deletes it immediately.
 */

declare(strict_types=1);
define('CLI_SCRIPT', true);

$root = getenv('NEXUS_MOODLE_ROOT') ?: dirname(__DIR__);
require $root . '/config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/course/modlib.php';

global $CFG, $DB, $USER;
$course = $DB->get_record('course', ['shortname' => 'SBI4U'], '*', MUST_EXIST);
if ((int)$course->id !== 33) {
    throw new RuntimeException('Refusing to test an unexpected course.');
}
$context = context_course::instance((int)$course->id);
$teacher = $DB->get_record('user', ['username' => 'teacher', 'deleted' => 0], '*', MUST_EXIST);

\core\session\manager::set_user($teacher);
$USER = $teacher;
require_capability('moodle/course:manageactivities', $context);

$idnumber = 'nexus_sbi4u_qa_editability_20260917';
$existing = $DB->get_record('course_modules', ['course' => $course->id, 'idnumber' => $idnumber, 'deletioninprogress' => 0]);
if ($existing) {
    throw new RuntimeException('The temporary QA activity already exists; stopping without deleting an unexpected record.');
}

$before = (int)$DB->count_records('course_modules', ['course' => $course->id, 'deletioninprogress' => 0]);
$cmid = null;
$created = false;
$deleted = false;
try {
    $info = (object)[
        'modulename' => 'page',
        'module' => $DB->get_field('modules', 'id', ['name' => 'page'], MUST_EXIST),
        'name' => 'TEMPORARY SBI4U editability QA - DELETE',
        'intro' => '',
        'introformat' => FORMAT_HTML,
        'content' => '<p>Temporary reversible QA activity.</p>',
        'contentformat' => FORMAT_HTML,
        'display' => 0,
        'printintro' => 0,
        'printlastmodified' => 0,
        'section' => 1,
        'visible' => 0,
        'cmidnumber' => $idnumber,
        'completion' => COMPLETION_TRACKING_NONE,
    ];
    $module = add_moduleinfo($info, $course);
    $cmid = (int)$module->coursemodule;
    $created = $DB->record_exists('course_modules', ['id' => $cmid, 'course' => $course->id, 'idnumber' => $idnumber]);
    if (!$created) {
        throw new RuntimeException('The temporary activity was not created as expected.');
    }
} finally {
    if ($cmid && $DB->record_exists('course_modules', ['id' => $cmid])) {
        course_delete_module($cmid, false);
    }
    $deleted = !$DB->record_exists('course_modules', ['course' => $course->id, 'idnumber' => $idnumber, 'deletioninprogress' => 0]);
}

$after = (int)$DB->count_records('course_modules', ['course' => $course->id, 'deletioninprogress' => 0]);
$pass = $created && $deleted && $before === $after;
echo json_encode([
    'status' => $pass ? 'PASS' : 'FAIL',
    'courseid' => (int)$course->id,
    'teacher' => $teacher->username,
    'can_manage_activities' => has_capability('moodle/course:manageactivities', $context, $teacher),
    'temporary_activity_created' => $created,
    'temporary_activity_deleted' => $deleted,
    'module_count_before' => $before,
    'module_count_after' => $after,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($pass ? 0 : 1);
