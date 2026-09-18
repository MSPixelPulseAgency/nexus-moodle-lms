<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';

global $DB;

$username = $argv[1] ?? 'admin';

$user = $DB->get_record(
    'user',
    [
        'username' => $username,
        'deleted' => 0,
    ],
    '*',
    MUST_EXIST
);

$studentrole = $DB->get_record(
    'role',
    ['shortname' => 'student'],
    '*',
    MUST_EXIST
);

$manual = enrol_get_plugin('manual');

if (!$manual) {
    throw new coding_exception(
        'Manual enrolment plugin is unavailable.'
    );
}

$courses = $DB->get_records_select(
    'course',
    'id <> :siteid',
    ['siteid' => SITEID],
    'shortname ASC'
);

$enrolled = 0;
$existing = 0;
$skipped = 0;

foreach ($courses as $course) {
    $instance = $DB->get_record(
        'enrol',
        [
            'courseid' => $course->id,
            'enrol' => 'manual',
            'status' => ENROL_INSTANCE_ENABLED,
        ]
    );

    if (!$instance) {
        $instanceid = $manual->add_instance(
            $course,
            [
                'status' => ENROL_INSTANCE_ENABLED,
                'roleid' => $studentrole->id,
            ]
        );

        $instance = $DB->get_record(
            'enrol',
            ['id' => $instanceid],
            '*',
            MUST_EXIST
        );
    }

    if (
        is_enrolled(
            context_course::instance($course->id),
            $user,
            '',
            true
        )
    ) {
        echo "EXISTS: {$course->shortname}" . PHP_EOL;
        $existing++;
        continue;
    }

    try {
        $manual->enrol_user(
            $instance,
            $user->id,
            $studentrole->id,
            0,
            0,
            ENROL_USER_ACTIVE
        );

        echo "ENROLLED: {$course->shortname}" . PHP_EOL;
        $enrolled++;
    } catch (Throwable $exception) {
        echo "SKIPPED: {$course->shortname} — "
            . $exception->getMessage()
            . PHP_EOL;

        $skipped++;
    }
}

echo PHP_EOL;
echo "{$enrolled} new enrolments created." . PHP_EOL;
echo "{$existing} existing enrolments retained." . PHP_EOL;
echo "{$skipped} courses skipped." . PHP_EOL;
