<?php

/** Read-only diagnostics for the three Nexus demo assignments. */

declare(strict_types=1);
define('CLI_SCRIPT', true);

$root = getenv('NEXUS_MOODLE_ROOT') ?: '/var/www/moodle';
require $root . '/config.php';
require_once $CFG->dirroot . '/mod/assign/locallib.php';

global $DB;
$fs = get_file_storage();
$users = ['ethan.campbell', 'olivia.bennett', 'liam.foster', 'chloe.martin'];
$result = [];

foreach ([1, 2, 3] as $number) {
    $sql = 'SELECT cm.id AS cmid, cm.instance
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.idnumber = :idnumber AND m.name = :modname AND cm.deletioninprogress = 0';
    $cm = $DB->get_record_sql($sql, ['idnumber' => 'nexus_demo_assignment_' . $number, 'modname' => 'assign'], MUST_EXIST);
    $context = context_module::instance($cm->cmid);
    foreach ($users as $username) {
        $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], '*', MUST_EXIST);
        $submission = $DB->get_record('assign_submission', [
            'assignment' => $cm->instance, 'userid' => $user->id, 'groupid' => 0, 'latest' => 1,
        ]);
        $files = [];
        if ($submission) {
            foreach ($fs->get_area_files($context->id, 'assignsubmission_file', 'submission_files', $submission->id, 'id ASC', false) as $file) {
                $files[] = ['filename' => $file->get_filename(), 'bytes' => $file->get_filesize()];
            }
        }
        $result[$number][$username] = [
            'enrolled' => is_enrolled(context_course::instance($DB->get_field('assign', 'course', ['id' => $cm->instance])), $user, '', true),
            'status' => $submission->status ?? null,
            'submissionid' => isset($submission->id) ? (int)$submission->id : null,
            'files' => $files,
        ];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
