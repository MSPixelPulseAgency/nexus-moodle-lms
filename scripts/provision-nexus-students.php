<?php

declare(strict_types=1);

define('CLI_SCRIPT', true);
$options = getopt('', ['dry-run', 'apply', 'help']);
if (isset($options['help']) || (isset($options['dry-run']) === isset($options['apply']))) {
    echo "Usage: php provision-nexus-students.php --dry-run|--apply\n";
    exit(isset($options['help']) ? 0 : 1);
}

$apply = isset($options['apply']);
$root = getenv('NEXUS_MOODLE_ROOT') ?: dirname(__DIR__);
chdir($root);
require $root . '/config.php';
require_once $CFG->dirroot . '/user/lib.php';
require_once $CFG->libdir . '/enrollib.php';

global $DB, $USER;

// Randomized once on 2026-09-17 and intentionally fixed for rerun stability.
$mapping = [
    'Muqhwwa' => ['MHF4U', 'SCH4U', 'ENG4U', 'SPH4U'],
    'Fatima' => ['MHF4U', 'SCH4U', 'ENG4U'],
    'Suban' => ['MHF4U', 'SCH4U', 'SPH4U'],
    'Mohamed' => ['MHF4U', 'ENG4U', 'SPH4U'],
    'Isma' => ['SCH4U', 'ENG4U', 'SPH4U'],
    'Kabir' => ['MHF4U', 'SCH4U'],
    'Eknoor' => ['ENG4U', 'SPH4U'],
    'Aman' => ['MHF4U', 'ENG4U'],
];

echo 'STABLE_MAPPING ' . json_encode($mapping, JSON_UNESCAPED_SLASHES) . PHP_EOL;

$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$manual = enrol_get_plugin('manual');
if (!$manual) {
    throw new RuntimeException('Manual enrolment plugin is unavailable.');
}

$courses = [];
$instances = [];
foreach (array_values(array_unique(array_merge(...array_values($mapping)))) as $shortname) {
    $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
    $courses[$shortname] = $course;
    $instances[$shortname] = null;
    foreach (enrol_get_instances((int)$course->id, true) as $instance) {
        if ($instance->enrol === 'manual' && (int)$instance->status === ENROL_INSTANCE_ENABLED) {
            $instances[$shortname] = $instance;
            break;
        }
    }
    if (!$instances[$shortname]) {
        throw new RuntimeException("No enabled manual enrolment instance for {$shortname}.");
    }
}

function nexus_lookup_ci(string $field, string $value): ?stdClass {
    global $DB;
    $allowed = ['username', 'email'];
    if (!in_array($field, $allowed, true)) {
        throw new coding_exception('Unsupported lookup field.');
    }
    $records = $DB->get_records_sql(
        "SELECT * FROM {user} WHERE deleted = 0 AND LOWER({$field}) = :value ORDER BY id",
        ['value' => strtolower($value)]
    );
    if (count($records) > 1) {
        throw new RuntimeException("Multiple active users match {$field}={$value}.");
    }
    return $records ? reset($records) : null;
}

function nexus_password(string $username): string {
    return strtoupper($username[0]) . substr($username, 1) . '@2026';
}

function nexus_safe_identity(string $name): array {
    global $CFG, $DB;
    $base = strtolower($name);
    $expectedemail = $base . '@nexuseps.com';
    $byusername = nexus_lookup_ci('username', $base);
    $byemail = nexus_lookup_ci('email', $expectedemail);

    if ($byusername && $byemail && (int)$byusername->id !== (int)$byemail->id) {
        throw new RuntimeException("Conflicting username and email records for {$name}.");
    }

    $candidate = $byusername ?: $byemail;
    if ($candidate) {
        $namematches = strtolower(trim($candidate->firstname . ' ' . $candidate->lastname)) === strtolower($name)
            || strtolower(trim($candidate->firstname)) === strtolower($name);
        $emailmatches = strtolower($candidate->email) === $expectedemail;
        if (!$namematches || !$emailmatches) {
            $candidate = null;
        }
    }

    if ($candidate) {
        if ($candidate->auth !== 'manual' || $candidate->suspended || $candidate->deleted) {
            throw new RuntimeException("Existing {$name} account is not an active local manual account.");
        }
        if (!str_ends_with(strtolower($candidate->email), '@nexuseps.com')) {
            throw new RuntimeException("Existing {$name} account does not use the Nexus email domain.");
        }
        return [$candidate, false, nexus_password($candidate->username)];
    }

    $username = $base;
    $email = $expectedemail;
    if ($byusername || $byemail) {
        $suffix = 1;
        do {
            $username = $base . '.student' . ($suffix === 1 ? '' : $suffix);
            $email = $username . '@nexuseps.com';
            $suffix++;
        } while (nexus_lookup_ci('username', $username) || nexus_lookup_ci('email', $email));
    }

    $user = (object)[
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => (int)$CFG->mnet_localhost_id,
        'username' => $username,
        'password' => nexus_password($username),
        'firstname' => $name,
        'lastname' => '',
        'email' => $email,
        'city' => 'Toronto',
        'country' => 'CA',
        'lang' => 'en',
        'timezone' => 'America/Toronto',
        'suspended' => 0,
        'deleted' => 0,
    ];
    return [$user, true, $user->password];
}

$planned = [];
foreach ($mapping as $name => $shortnames) {
    [$user, $create, $password] = nexus_safe_identity($name);
    if (!$create && is_siteadmin((int)$user->id)) {
        throw new RuntimeException("Refusing to use site administrator account {$user->username}.");
    }
    $planned[$name] = compact('user', 'create', 'password', 'shortnames');
}

$summary = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'accounts_created' => 0,
    'existing_accounts_reused' => 0,
    'new_enrolments' => 0,
    'already_enrolled_skipped' => 0,
    'failed_enrolments' => 0,
    'students' => [],
];

if ($apply) {
    $admin = get_admin();
    \core\session\manager::set_user($admin);
    $USER = $admin;
    $transaction = $DB->start_delegated_transaction();

    foreach ($planned as $name => &$entry) {
        $user = $entry['user'];
        if ($entry['create']) {
            $user->id = user_create_user($user, true, false);
            $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
            $summary['accounts_created']++;
            $status = 'CREATED';
        } else {
            $summary['existing_accounts_reused']++;
            $status = 'EXISTING';
        }

        // Accounts created by this script must belong to Moodle's local MNet
        // host. Older script runs left this as 0, which made otherwise valid
        // local credentials fail at the web login boundary.
        if ((int)$user->mnethostid === 0) {
            $user->mnethostid = (int)$CFG->mnet_localhost_id;
            user_update_user($user, false, false);
            $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        } elseif ((int)$user->mnethostid !== (int)$CFG->mnet_localhost_id) {
            throw new RuntimeException("Refusing to convert remote account {$user->username} to a local account.");
        }

        // Set the local password explicitly after creation so verification
        // is independent of user_create_user() password handling.
        update_internal_user_password($user, $entry['password']);
        $entry['user'] = $user;

        foreach ($entry['shortnames'] as $shortname) {
            $course = $courses[$shortname];
            $context = context_course::instance((int)$course->id);
            $roles = get_user_roles($context, (int)$user->id, true);
            foreach ($roles as $role) {
                if ($role->shortname !== 'student') {
                    throw new RuntimeException("{$user->username} has non-student role {$role->shortname} in {$shortname}.");
                }
            }
            if (is_enrolled($context, $user, '', true)) {
                if (!$DB->record_exists('role_assignments', [
                    'roleid' => $studentrole->id,
                    'userid' => $user->id,
                    'contextid' => $context->id,
                ])) {
                    role_assign((int)$studentrole->id, (int)$user->id, (int)$context->id);
                }
                $summary['already_enrolled_skipped']++;
            } else {
                $manual->enrol_user(
                    $instances[$shortname],
                    (int)$user->id,
                    (int)$studentrole->id,
                    0,
                    0,
                    ENROL_USER_ACTIVE
                );
                $summary['new_enrolments']++;
            }
        }

        $summary['students'][$name] = [
            'username' => $user->username,
            'password' => $entry['password'],
            'email' => $user->email,
            'status' => $status,
            'courses' => $entry['shortnames'],
        ];
    }
    unset($entry);

    $transaction->allow_commit();
    purge_all_caches();

    foreach ($planned as $name => $entry) {
        $user = $DB->get_record('user', ['id' => $entry['user']->id], '*', MUST_EXIST);
        $visiblecourses = enrol_get_users_courses((int)$user->id, true, 'id,shortname');
        $visiblebyshortname = [];
        foreach ($visiblecourses as $course) {
            $visiblebyshortname[$course->shortname] = true;
        }
        $rolesok = !is_siteadmin((int)$user->id);
        $coursesok = true;
        foreach ($entry['shortnames'] as $shortname) {
            $context = context_course::instance((int)$courses[$shortname]->id);
            $roles = get_user_roles($context, (int)$user->id, true);
            $shortroles = array_values(array_unique(array_map(static fn(stdClass $role): string => $role->shortname, $roles)));
            $rolesok = $rolesok && $shortroles === ['student'];
            $coursesok = $coursesok && isset($visiblebyshortname[$shortname]);
        }
        $summary['students'][$name]['login_verified'] =
            (bool)authenticate_user_login($user->username, $entry['password'], false);
        $summary['students'][$name]['student_role_verified'] = $rolesok;
        $summary['students'][$name]['my_courses_verified'] = $coursesok;
    }
} else {
    foreach ($planned as $name => $entry) {
        $summary['students'][$name] = [
            'username' => $entry['user']->username,
            'email' => $entry['user']->email,
            'status' => $entry['create'] ? 'WOULD_CREATE' : 'WOULD_REUSE',
            'courses' => $entry['shortnames'],
        ];
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($apply) {
    foreach ($summary['students'] as $student) {
        if (!$student['login_verified'] || !$student['student_role_verified'] || !$student['my_courses_verified']) {
            exit(1);
        }
    }
}
