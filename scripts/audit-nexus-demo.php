<?php

/**
 * Read-only audit for the Nexus course-visual and demo-learning package.
 *
 * Usage:
 *   NEXUS_MOODLE_ROOT=/path/to/moodle php audit-nexus-demo.php
 */

declare(strict_types=1);

define('CLI_SCRIPT', true);

$root = getenv('NEXUS_MOODLE_ROOT') ?: '/var/www/moodle';
require $root . '/config.php';
require_once $CFG->libdir . '/enrollib.php';

global $CFG, $DB;

$shortnames = ['MHF4U', 'SBI4U', 'SCH4U', 'ENG4U', 'ASM4M', 'SPH4U', 'TDJ4M', 'AVI1O'];
$targetusernames = ['teacher', 'ethan.campbell', 'olivia.bennett', 'liam.foster', 'chloe.martin'];
$fs = get_file_storage();
$siteadminids = array_fill_keys(array_map('intval', array_keys(get_admins())), true);

$result = [
    'mode' => 'read-only',
    'wwwroot' => $CFG->wwwroot,
    'moodle_release' => $CFG->release,
    'moodle_version' => (int)$CFG->version,
    'php_version' => PHP_VERSION,
    'theme' => (string)get_config('core', 'theme'),
    'course_count' => $DB->count_records_select('course', 'id <> :siteid', ['siteid' => SITEID]),
    'courses' => [],
    'users' => [],
    'mfh4u' => null,
    'local_plugins' => [],
    'scheduled_tasks' => [],
];

foreach ($shortnames as $shortname) {
    $matches = $DB->get_records('course', ['shortname' => $shortname], 'id ASC');
    $records = [];
    foreach ($matches as $course) {
        $context = context_course::instance((int)$course->id);
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'id ASC', false);
        $records[] = [
            'id' => (int)$course->id,
            'fullname' => $course->fullname,
            'visible' => (bool)$course->visible,
            'category' => (int)$course->category,
            'overview_files' => array_values(array_map(static fn(stored_file $file): array => [
                'filename' => $file->get_filename(),
                'mimetype' => $file->get_mimetype(),
                'filesize' => $file->get_filesize(),
            ], $files)),
        ];
    }
    $result['courses'][$shortname] = $records;
}

foreach ($targetusernames as $username) {
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if (!$user) {
        $result['users'][$username] = ['exists' => false];
        continue;
    }

    $roles = $DB->get_records_sql(
        'SELECT CONCAT(ctx.instanceid, :separator, r.shortname) AS recordkey,
                ctx.instanceid AS courseid, r.shortname
           FROM {role_assignments} ra
           JOIN {role} r ON r.id = ra.roleid
           JOIN {context} ctx ON ctx.id = ra.contextid
          WHERE ra.userid = :userid AND ctx.contextlevel = :contextcourse
       ORDER BY ctx.instanceid, r.shortname',
        [
            'separator' => ':',
            'userid' => $user->id,
            'contextcourse' => CONTEXT_COURSE,
        ]
    );

    $result['users'][$username] = [
        'exists' => true,
        'id' => (int)$user->id,
        'name' => fullname($user),
        'auth' => $user->auth,
        'confirmed' => (bool)$user->confirmed,
        'suspended' => (bool)$user->suspended,
        'site_admin' => isset($siteadminids[(int)$user->id]),
        'course_roles' => array_values(array_map(static fn(stdClass $role): array => [
            'courseid' => (int)$role->courseid,
            'role' => $role->shortname,
        ], $roles)),
    ];
}

$mhf4umatches = $DB->get_records('course', ['shortname' => 'MHF4U']);
if (count($mhf4umatches) === 1) {
    $course = reset($mhf4umatches);
    $context = context_course::instance((int)$course->id);
    $sections = [];
    foreach ($DB->get_records('course_sections', ['course' => $course->id], 'section ASC') as $section) {
        $sections[] = [
            'section' => (int)$section->section,
            'name' => $section->name,
            'visible' => (bool)$section->visible,
        ];
    }

    $modules = [];
    $modulerows = $DB->get_records_sql(
        'SELECT cm.id, cm.idnumber, cm.section, cm.visible, m.name AS modname,
                COALESCE(a.name, q.name, p.name, r.name) AS activityname
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
      LEFT JOIN {assign} a ON m.name = :assignname AND a.id = cm.instance
      LEFT JOIN {quiz} q ON m.name = :quizname AND q.id = cm.instance
      LEFT JOIN {page} p ON m.name = :pagename AND p.id = cm.instance
      LEFT JOIN {resource} r ON m.name = :resourcename AND r.id = cm.instance
          WHERE cm.course = :courseid AND cm.deletioninprogress = 0
       ORDER BY cm.section, cm.id',
        [
            'assignname' => 'assign',
            'quizname' => 'quiz',
            'pagename' => 'page',
            'resourcename' => 'resource',
            'courseid' => $course->id,
        ]
    );
    foreach ($modulerows as $module) {
        $modules[] = [
            'cmid' => (int)$module->id,
            'sectionid' => (int)$module->section,
            'modname' => $module->modname,
            'idnumber' => $module->idnumber,
            'name' => $module->activityname,
            'visible' => (bool)$module->visible,
        ];
    }

    $assignments = [];
    $assignmentrows = $DB->get_records_sql(
        'SELECT a.id, a.name, a.duedate, a.cutoffdate, a.grade, cm.id AS cmid, cm.idnumber
           FROM {assign} a
           JOIN {course_modules} cm ON cm.instance = a.id
           JOIN {modules} m ON m.id = cm.module AND m.name = :modname
          WHERE a.course = :courseid
       ORDER BY cm.section, cm.id',
        ['modname' => 'assign', 'courseid' => $course->id]
    );
    foreach ($assignmentrows as $assignment) {
        $submissions = $DB->count_records_select(
            'assign_submission',
            'assignment = :assignment AND status = :status AND latest = 1',
            ['assignment' => $assignment->id, 'status' => 'submitted']
        );
        $assignments[] = [
            'id' => (int)$assignment->id,
            'cmid' => (int)$assignment->cmid,
            'idnumber' => $assignment->idnumber,
            'name' => $assignment->name,
            'duedate' => (int)$assignment->duedate,
            'cutoffdate' => (int)$assignment->cutoffdate,
            'grade' => (float)$assignment->grade,
            'submitted_count' => $submissions,
        ];
    }

    $result['mfh4u'] = [
        'id' => (int)$course->id,
        'fullname' => $course->fullname,
        'visible' => (bool)$course->visible,
        'sections' => $sections,
        'modules' => $modules,
        'assignments' => $assignments,
        'stored_file_count' => $DB->count_records_select(
            'files',
            'contextid = :contextid AND filename <> :directory',
            ['contextid' => $context->id, 'directory' => '.']
        ),
    ];
}

$pluginmanager = core_plugin_manager::instance();
foreach ($pluginmanager->get_plugins_of_type('local') as $name => $info) {
    $result['local_plugins'][$name] = [
        'version' => $info->versiondisk,
        'enabled' => $info->is_enabled(),
    ];
}
ksort($result['local_plugins']);

foreach ($DB->get_records_select(
    'task_scheduled',
    $DB->sql_like('classname', ':cron') . ' OR ' . $DB->sql_like('classname', ':nexus'),
    ['cron' => '%cron%', 'nexus' => '%nexus%'],
    'classname ASC',
    'classname,lastruntime,nextruntime,faildelay,disabled'
) as $task) {
    $result['scheduled_tasks'][] = [
        'classname' => $task->classname,
        'lastruntime' => (int)$task->lastruntime,
        'nextruntime' => (int)$task->nextruntime,
        'faildelay' => (int)$task->faildelay,
        'disabled' => (bool)$task->disabled,
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
