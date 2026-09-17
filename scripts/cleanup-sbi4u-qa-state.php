<?php

/** Remove administrator-only view/submission records created during SBI4U QA. */

declare(strict_types=1);
define('CLI_SCRIPT', true);

$options = getopt('', ['apply']);
if (!isset($options['apply'])) {
    throw new RuntimeException('Run with --apply after confirming the original learner-data baseline was empty.');
}

$root = getenv('NEXUS_MOODLE_ROOT') ?: dirname(__DIR__);
require $root . '/config.php';
global $DB;

$course = $DB->get_record('course', ['shortname' => 'SBI4U'], '*', MUST_EXIST);
if ((int)$course->id !== 33) {
    throw new RuntimeException('Refusing to clean an unexpected course.');
}
$admin = $DB->get_record('user', ['username' => 'admin', 'deleted' => 0], '*', MUST_EXIST);

$submissionids = $DB->get_fieldset_sql(
    "SELECT s.id
       FROM {assign_submission} s
       JOIN {assign} a ON a.id = s.assignment
       JOIN {course_modules} cm ON cm.course = a.course AND cm.instance = a.id
       JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
      WHERE a.course = :courseid AND s.userid = :userid AND s.status = 'new'
            AND cm.idnumber LIKE :prefix",
    ['courseid' => $course->id, 'userid' => $admin->id, 'prefix' => 'nexus_sbi4u_%']
);
$completionids = $DB->get_fieldset_sql(
    "SELECT cmc.id
       FROM {course_modules_completion} cmc
       JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
      WHERE cm.course = :courseid AND cmc.userid = :userid
            AND cm.idnumber LIKE :prefix",
    ['courseid' => $course->id, 'userid' => $admin->id, 'prefix' => 'nexus_sbi4u_%']
);

$transaction = $DB->start_delegated_transaction();
if ($submissionids) {
    $DB->delete_records_list('assign_submission', 'id', array_map('intval', $submissionids));
}
if ($completionids) {
    $DB->delete_records_list('course_modules_completion', 'id', array_map('intval', $completionids));
}
$transaction->allow_commit();

$remaining = [
    'admin_new_submissions' => (int)$DB->count_records_sql(
        "SELECT COUNT(s.id) FROM {assign_submission} s JOIN {assign} a ON a.id=s.assignment WHERE a.course=:courseid AND s.userid=:userid AND s.status='new'",
        ['courseid' => $course->id, 'userid' => $admin->id]
    ),
    'admin_completion_records' => (int)$DB->count_records_sql(
        'SELECT COUNT(cmc.id) FROM {course_modules_completion} cmc JOIN {course_modules} cm ON cm.id=cmc.coursemoduleid WHERE cm.course=:courseid AND cmc.userid=:userid',
        ['courseid' => $course->id, 'userid' => $admin->id]
    ),
];
$pass = array_sum($remaining) === 0;
echo json_encode([
    'status' => $pass ? 'PASS' : 'FAIL',
    'courseid' => (int)$course->id,
    'removed_admin_new_submissions' => count($submissionids),
    'removed_admin_completion_records' => count($completionids),
    'remaining' => $remaining,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($pass ? 0 : 1);
