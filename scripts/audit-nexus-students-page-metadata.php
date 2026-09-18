<?php

declare(strict_types=1);

define('CLI_SCRIPT', true);
$root = getenv('NEXUS_MOODLE_ROOT') ?: dirname(__DIR__);
chdir($root);
require $root . '/config.php';

global $DB;

$names = ['Muqhwwa', 'Fatima', 'Suban', 'Mohamed', 'Isma', 'Kabir', 'Eknoor', 'Aman'];
$shortnames = ['MHF4U', 'SCH4U', 'ENG4U', 'SPH4U'];

$courses = [];
foreach ($shortnames as $shortname) {
    $course = $DB->get_record('course', ['shortname' => $shortname], 'id,shortname,fullname,visible', MUST_EXIST);
    $courses[$shortname] = $course;
}

$users = [];
foreach ($names as $name) {
    $username = strtolower($name);
    $email = $username . '@nexuseps.com';
    $records = $DB->get_records_sql(
        'SELECT id, username, firstname, lastname, email, auth, suspended, deleted
           FROM {user}
          WHERE deleted = 0
            AND (LOWER(username) = :username OR LOWER(email) = :email)
       ORDER BY id',
        ['username' => $username, 'email' => $email]
    );
    $users[$name] = array_values(array_map(static function(stdClass $user) use ($DB, $courses): array {
        $enrolments = [];
        foreach ($courses as $shortname => $course) {
            $context = context_course::instance((int)$course->id);
            $roles = get_user_roles($context, (int)$user->id, true);
            $shortroles = array_values(array_unique(array_map(static fn(stdClass $role): string => $role->shortname, $roles)));
            if (is_enrolled($context, $user, '', true) || $shortroles) {
                $enrolments[$shortname] = $shortroles;
            }
        }
        return [
            'id' => (int)$user->id,
            'username' => $user->username,
            'name' => fullname($user),
            'email' => $user->email,
            'auth' => $user->auth,
            'suspended' => (bool)$user->suspended,
            'target_enrolments' => $enrolments,
        ];
    }, $records));
}

$pagecounts = [];
$representative = [];
foreach ($courses as $shortname => $course) {
    $params = ['courseid' => $course->id];
    $pages = $DB->get_records_sql(
        "SELECT p.id, p.name, p.displayoptions, p.timemodified, p.content, cm.id AS cmid
           FROM {page} p
           JOIN {course_modules} cm ON cm.instance = p.id
           JOIN {modules} m ON m.id = cm.module AND m.name = 'page'
          WHERE cm.course = :courseid AND cm.deletioninprogress = 0
       ORDER BY cm.id",
        $params
    );
    $pagecounts[$shortname] = [
        'total' => count($pages),
        'showing_last_modified' => count(array_filter($pages, static function(stdClass $page): bool {
            $options = @unserialize((string)$page->displayoptions);
            return !is_array($options)
                || !isset($options['printlastmodified'])
                || !empty($options['printlastmodified']);
        })),
    ];
    if ($pages) {
        $page = reset($pages);
        $representative[$shortname] = [
            'pageid' => (int)$page->id,
            'cmid' => (int)$page->cmid,
            'name' => $page->name,
            'printlastmodified' => (int)(@unserialize((string)$page->displayoptions)['printlastmodified'] ?? 0),
            'timemodified' => (int)$page->timemodified,
            'content_sha256' => hash('sha256', $page->content),
        ];
    }
}

$allpages = $DB->get_records('page', null, 'id', 'id,displayoptions,timemodified,content');
$pagedigest = [];
foreach ($allpages as $page) {
    $pagedigest[] = [(int)$page->id, (int)$page->timemodified, hash('sha256', $page->content)];
}

$dates = [];
foreach ($DB->get_records('assign', null, 'id', 'id,course,allowsubmissionsfromdate,duedate,cutoffdate,gradingduedate') as $assign) {
    $dates[] = ['assign', (int)$assign->id, (int)$assign->course, (int)$assign->allowsubmissionsfromdate,
        (int)$assign->duedate, (int)$assign->cutoffdate, (int)$assign->gradingduedate];
}
foreach ($DB->get_records('quiz', null, 'id', 'id,course,timeopen,timeclose') as $quiz) {
    $dates[] = ['quiz', (int)$quiz->id, (int)$quiz->course, (int)$quiz->timeopen, (int)$quiz->timeclose];
}

echo json_encode([
    'courses' => array_map(static fn(stdClass $course): array => [
        'id' => (int)$course->id,
        'fullname' => $course->fullname,
        'visible' => (bool)$course->visible,
    ], $courses),
    'users' => $users,
    'pages' => [
        'total' => count($allpages),
        'showing_last_modified' => count(array_filter($allpages, static function(stdClass $page): bool {
            $options = @unserialize((string)$page->displayoptions);
            return !is_array($options)
                || !isset($options['printlastmodified'])
                || !empty($options['printlastmodified']);
        })),
        'by_target_course' => $pagecounts,
        'representative' => $representative,
        'timestamp_content_digest' => hash('sha256', json_encode($pagedigest)),
    ],
    'academic_dates_digest' => hash('sha256', json_encode($dates)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
