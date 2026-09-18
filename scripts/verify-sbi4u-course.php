<?php

/** Read-only verification for the production Nexus SBI4U course. */

declare(strict_types=1);
define('CLI_SCRIPT', true);

$root = getenv('NEXUS_MOODLE_ROOT') ?: dirname(__DIR__);
$mediaroot = rtrim((string)(getenv('NEXUS_SBI4U_MEDIA') ?: '/home/master/nexus-sbi4u-media-20260917'), '/');
$plan = require __DIR__ . '/sbi4u-course-plan.php';
require $root . '/config.php';
require_once $CFG->libdir . '/completionlib.php';
require_once $CFG->libdir . '/gradelib.php';
require_once $CFG->dirroot . '/mod/quiz/locallib.php';

global $CFG, $DB;
$checks = [];
$failures = [];
function sbi4u_check(bool $ok, string $label, mixed $detail = null): void {
    global $checks, $failures;
    $checks[] = ['check' => $label, 'pass' => $ok, 'detail' => $detail];
    if (!$ok) {
        $failures[] = $label;
    }
}

$courses = $DB->get_records('course', ['shortname' => 'SBI4U']);
sbi4u_check(count($courses) === 1, 'exactly-one-sbi4u', count($courses));
$course = reset($courses);
sbi4u_check((int)$course->id === 33, 'course-id-33', (int)$course->id);
sbi4u_check((bool)$course->enablecompletion, 'course-completion-enabled');
$context = context_course::instance((int)$course->id);

$expectedsections = [
    0 => 'Course Information and Announcements', 1 => 'Scientific Investigation Skills',
    2 => 'Unit 1 - Biochemistry', 3 => 'Unit 2 - Metabolic Processes',
    4 => 'Unit 3 - Molecular Genetics', 5 => 'Unit 4 - Homeostasis',
    6 => 'Unit 5 - Population Dynamics', 7 => 'Final Evaluation',
];
foreach ($expectedsections as $number => $name) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $number]);
    sbi4u_check($section && $section->name === $name && $section->visible, "section-{$number}", $section->name ?? null);
}

$modulecounts = [];
$rows = $DB->get_records_sql('SELECT m.name AS modname, COUNT(cm.id) AS total FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module WHERE cm.course = :courseid AND cm.deletioninprogress = 0 GROUP BY m.name', ['courseid' => $course->id]);
foreach ($rows as $row) {
    $modulecounts[$row->modname] = (int)$row->total;
}
$expectedcounts = ['assign' => 41, 'book' => 7, 'forum' => 1, 'page' => 11, 'quiz' => 12];
sbi4u_check($modulecounts === $expectedcounts, 'module-counts', $modulecounts);

$chapters = $DB->get_records_sql('SELECT bc.* FROM {book_chapters} bc JOIN {book} b ON b.id = bc.bookid WHERE b.course = :courseid AND bc.hidden = 0', ['courseid' => $course->id]);
sbi4u_check(count($chapters) === 51, 'book-chapter-count', count($chapters));
$chapterids = array_map('intval', array_keys($chapters));

$expectedcodes = [];
for ($i = 1; $i <= 13; $i++) {$expectedcodes[] = 'A1.' . $i;}
for ($i = 1; $i <= 2; $i++) {$expectedcodes[] = 'A2.' . $i;}
$expectedcodes = array_merge($expectedcodes, [
    'B1.1', 'B1.2', 'B2.1', 'B2.2', 'B2.3', 'B2.4', 'B2.5', 'B3.1', 'B3.2', 'B3.3', 'B3.4', 'B3.5', 'B3.6',
    'C1.1', 'C1.2', 'C2.1', 'C2.2', 'C2.3', 'C3.1', 'C3.2', 'C3.3', 'C3.4',
    'D1.1', 'D1.2', 'D2.1', 'D2.2', 'D2.3', 'D2.4', 'D3.1', 'D3.2', 'D3.3', 'D3.4', 'D3.5', 'D3.6', 'D3.7',
    'E1.1', 'E1.2', 'E2.1', 'E2.2', 'E2.3', 'E2.4', 'E3.1', 'E3.2', 'E3.3',
    'F1.1', 'F1.2', 'F2.1', 'F2.2', 'F2.3', 'F3.1', 'F3.2', 'F3.3', 'F3.4', 'F3.5',
]);
$mappedcodes = array_fill_keys(array_slice($expectedcodes, 0, 15), true);
foreach ($plan['units'] as $unit) {
    foreach ($unit['chapters'] as $chapter) {
        foreach ($chapter['expectations'] as $code) {$mappedcodes[$code] = true;}
    }
}
$missingcodes = array_values(array_filter($expectedcodes, static fn($code) => empty($mappedcodes[$code])));
$livechaptercontent = implode("\n", array_map(static fn($chapter) => strip_tags((string)$chapter->content), $chapters));
$missinglivecodes = array_values(array_filter($expectedcodes, static fn($code) => !preg_match('/(?<![A-Z0-9.])' . preg_quote($code, '/') . '(?!\\d)/', $livechaptercontent)));
sbi4u_check(count($expectedcodes) === 69 && !$missingcodes && !$missinglivecodes, 'all-specific-expectations-mapped', [
    'expected' => count($expectedcodes),
    'mapped' => count($expectedcodes) - count($missingcodes),
    'missing_plan' => $missingcodes,
    'missing_live' => $missinglivecodes,
]);

$planned = [];
foreach ($plan['units'] as $unit) {
    foreach ($unit['chapters'] as $chapter) {
        foreach ($chapter['videos'] as $relative) {
            $planned[$relative] = $chapter['id'];
        }
    }
}
sbi4u_check(count($planned) === 65, 'planned-video-count', count($planned));

$filecount = 0;
$badfiles = [];
$fs = get_file_storage();
foreach ($plan['units'] as $unit) {
    $book = $DB->get_record('book', ['course' => $course->id, 'name' => $unit['book']], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('book', (int)$book->id, (int)$course->id, false, MUST_EXIST);
    $cmcontext = context_module::instance((int)$cm->id);
    foreach ($unit['chapters'] as $chapterplan) {
        $chapter = $DB->get_record('book_chapters', ['bookid' => $book->id, 'importsrc' => $chapterplan['id']], '*', MUST_EXIST);
        sbi4u_check(!str_contains((string)$chapter->content, 'draftfile.php'), 'chapter-no-draft-' . $chapterplan['id']);
        foreach ($chapterplan['videos'] as $relative) {
            $filename = basename($relative);
            $file = $fs->get_file($cmcontext->id, 'mod_book', 'chapter', (int)$chapter->id, '/', $filename);
            $source = $mediaroot . '/' . $relative;
            $valid = $file && $file->get_mimetype() === 'video/mp4' && (int)$file->get_filesize() === (int)filesize($source) && $file->get_contenthash() === sha1_file($source);
            if (!$valid) {
                $badfiles[] = $relative;
            }
            $filecount++;
            sbi4u_check(str_contains((string)$chapter->content, '@@PLUGINFILE@@/' . rawurlencode($filename)), 'chapter-video-ref-' . sha1($relative));
        }
    }
}
sbi4u_check($filecount === 65 && !$badfiles, 'video-file-integrity', ['count' => $filecount, 'bad' => $badfiles]);

$contenttables = [
    ['book_chapters', 'content', 'bookid IN (SELECT id FROM {book} WHERE course = :courseid)'],
    ['page', 'content', 'course = :courseid'],
    ['assign', 'intro', 'course = :courseid'],
    ['quiz', 'intro', 'course = :courseid'],
    ['course_sections', 'summary', 'course = :courseid'],
];
$badrefs = [];
foreach ($contenttables as [$table, $field, $where]) {
    foreach ($DB->get_records_select($table, $where, ['courseid' => $course->id], '', 'id,' . $field) as $record) {
        $value = (string)$record->{$field};
        foreach (['draftfile.php', 'file://', 'localhost', '/tmp/', '/Users/'] as $needle) {
            if (stripos($value, $needle) !== false) {
                $badrefs[] = "{$table}:{$record->id}:{$needle}";
            }
        }
    }
}
sbi4u_check(!$badrefs, 'no-broken-or-local-refs', $badrefs);

$qcats = $DB->get_records_select('question_categories', 'contextid = :contextid AND idnumber LIKE :prefix', ['contextid' => $context->id, 'prefix' => 'nexus_sbi4u_%']);
sbi4u_check(count($qcats) === 6, 'question-category-count', array_values(array_map(static fn($c) => $c->name, $qcats)));
$questionentries = $DB->count_records_sql('SELECT COUNT(qbe.id) FROM {question_bank_entries} qbe JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid WHERE qc.contextid = :contextid AND qc.idnumber LIKE :prefix', ['contextid' => $context->id, 'prefix' => 'nexus_sbi4u_%']);
sbi4u_check((int)$questionentries === 55, 'question-bank-entry-count', (int)$questionentries);

$quizslots = [];
foreach ($DB->get_records('quiz', ['course' => $course->id]) as $quiz) {
    $quizslots[$quiz->name] = (int)$DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
}
$expectedslotcounts = [];
for ($unit = 1; $unit <= 5; $unit++) {
    $expectedslotcounts["Unit {$unit} Quiz"] = 4;
    $expectedslotcounts["Unit {$unit} Test"] = 5;
}
$expectedslotcounts['Final Practice Assessment'] = 20;
$expectedslotcounts['SBI4U Final Examination'] = 10;
ksort($quizslots);
ksort($expectedslotcounts);
sbi4u_check($quizslots === $expectedslotcounts, 'quiz-slot-counts', $quizslots);

$rootcategory = grade_category::fetch_course_category((int)$course->id);
$term = grade_category::fetch(['courseid' => $course->id, 'fullname' => 'Term Evaluation - 70%']);
$final = grade_category::fetch(['courseid' => $course->id, 'fullname' => 'Final Evaluation - 30%']);
sbi4u_check((int)$rootcategory->aggregation === GRADE_AGGREGATE_WEIGHTED_MEAN, 'gradebook-weighted-root', (int)$rootcategory->aggregation);
sbi4u_check((bool)$term && abs((float)$term->get_grade_item()->aggregationcoef - 0.70) < 0.0001, 'gradebook-term-70', $term ? $term->get_grade_item()->aggregationcoef : null);
sbi4u_check((bool)$final && abs((float)$final->get_grade_item()->aggregationcoef - 0.30) < 0.0001, 'gradebook-final-30', $final ? $final->get_grade_item()->aggregationcoef : null);

$cms = $DB->get_records('course_modules', ['course' => $course->id, 'deletioninprogress' => 0]);
$cmids = array_fill_keys(array_map('intval', array_keys($cms)), true);
$edges = [];
$badavailability = [];
$restrictioncount = 0;
foreach ($cms as $cm) {
    if (!$cm->availability) {
        continue;
    }
    $restrictioncount++;
    $data = json_decode($cm->availability, true);
    if (!is_array($data)) {
        $badavailability[] = 'invalid-json:' . $cm->id;
        continue;
    }
    foreach ($data['c'] ?? [] as $condition) {
        if (($condition['type'] ?? '') === 'completion') {
            $required = (int)($condition['cm'] ?? 0);
            if (!isset($cmids[$required]) || $required === (int)$cm->id) {
                $badavailability[] = 'invalid-completion:' . $cm->id;
            }
            $edges[(int)$cm->id][] = $required;
        }
    }
}
$visiting = $visited = [];
$cycle = false;
$walk = function(int $node) use (&$walk, &$visiting, &$visited, &$edges, &$cycle): void {
    if (isset($visiting[$node])) {$cycle = true; return;}
    if (isset($visited[$node])) {return;}
    $visiting[$node] = true;
    foreach ($edges[$node] ?? [] as $next) {$walk($next);}
    unset($visiting[$node]); $visited[$node] = true;
};
foreach (array_keys($edges) as $node) {$walk((int)$node);}
sbi4u_check($restrictioncount === 68 && !$badavailability && !$cycle, 'completion-restrictions-valid', ['count' => $restrictioncount, 'bad' => $badavailability, 'cycle' => $cycle]);

$solutioncms = $DB->get_records_sql("SELECT cm.* FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module WHERE cm.course = :courseid AND cm.idnumber LIKE :pattern AND m.name = 'page'", ['courseid' => $course->id, 'pattern' => 'nexus_sbi4u_u%_solution_%']);
$solutionsok = count($solutioncms) === 10;
foreach ($solutioncms as $cm) {
    $solutionsok = $solutionsok && (int)$cm->completion === COMPLETION_TRACKING_NONE && !empty($cm->availability);
}
sbi4u_check($solutionsok, 'solutions-restricted-not-required', count($solutioncms));

$teacher = $DB->get_record('user', ['username' => 'teacher', 'deleted' => 0], '*', MUST_EXIST);
$student = $DB->get_record('user', ['username' => 'student', 'deleted' => 0], '*', MUST_EXIST);
$admins = get_admins();
sbi4u_check(has_capability('moodle/course:update', $context, $teacher), 'teacher-can-edit');
sbi4u_check(!has_capability('moodle/course:update', $context, $student), 'student-cannot-edit');
sbi4u_check(count($admins) >= 1 && has_capability('moodle/course:update', $context, reset($admins)), 'admin-can-edit');

$roles = $DB->get_records_sql('SELECT CONCAT(u.id, \'-\', r.id) AS rowid, u.username, r.shortname FROM {user} u JOIN {role_assignments} ra ON ra.userid=u.id JOIN {role} r ON r.id=ra.roleid WHERE ra.contextid=:contextid ORDER BY u.username,r.shortname', ['contextid' => $context->id]);
$currentroles = [];
foreach ($roles as $role) {
    $currentroles[$role->username . ':' . $role->shortname] = true;
}
$baselineRoles = [
    'admin:manager', 'rushal:manager', 'radhika:manager', 'jainam:manager', 'adler:manager',
    'teacher:editingteacher', 'student:student', 'ethan:student', 'liam.foster:student',
];
$missingroles = array_values(array_filter($baselineRoles, static fn($role) => empty($currentroles[$role])));
sbi4u_check(!$missingroles, 'enrolments-preserved', ['current' => count($roles), 'missing_baseline_roles' => $missingroles]);
$userdata = [
    'grade_grades' => $DB->count_records_sql("SELECT COUNT(gg.id) FROM {grade_grades} gg JOIN {grade_items} gi ON gi.id=gg.itemid WHERE gi.courseid=:courseid AND EXISTS (SELECT 1 FROM {role_assignments} ra JOIN {role} r ON r.id=ra.roleid WHERE ra.contextid=:contextid AND ra.userid=gg.userid AND r.shortname='student')", ['courseid' => $course->id, 'contextid' => $context->id]),
    'assign_submissions' => $DB->count_records_sql("SELECT COUNT(s.id) FROM {assign_submission} s JOIN {assign} a ON a.id=s.assignment WHERE a.course=:courseid AND EXISTS (SELECT 1 FROM {role_assignments} ra JOIN {role} r ON r.id=ra.roleid WHERE ra.contextid=:contextid AND ra.userid=s.userid AND r.shortname='student')", ['courseid' => $course->id, 'contextid' => $context->id]),
    'quiz_attempts' => $DB->count_records_sql("SELECT COUNT(qa.id) FROM {quiz_attempts} qa JOIN {quiz} q ON q.id=qa.quiz WHERE q.course=:courseid AND EXISTS (SELECT 1 FROM {role_assignments} ra JOIN {role} r ON r.id=ra.roleid WHERE ra.contextid=:contextid AND ra.userid=qa.userid AND r.shortname='student')", ['courseid' => $course->id, 'contextid' => $context->id]),
    'completion_records' => $DB->count_records_sql("SELECT COUNT(cmc.id) FROM {course_modules_completion} cmc JOIN {course_modules} cm ON cm.id=cmc.coursemoduleid WHERE cm.course=:courseid AND EXISTS (SELECT 1 FROM {role_assignments} ra JOIN {role} r ON r.id=ra.roleid WHERE ra.contextid=:contextid AND ra.userid=cmc.userid AND r.shortname='student')", ['courseid' => $course->id, 'contextid' => $context->id]),
];
sbi4u_check(array_sum(array_map('intval', $userdata)) === 0, 'pre-existing-learner-data-unchanged', $userdata);

$result = [
    'status' => $failures ? 'FAIL' : 'PASS', 'courseid' => (int)$course->id,
    'check_count' => count($checks), 'failure_count' => count($failures), 'failures' => $failures,
    'module_counts' => $modulecounts, 'book_chapters' => count($chapters), 'videos' => $filecount,
    'question_entries' => (int)$questionentries, 'quiz_slots' => $quizslots,
    'restrictions' => $restrictioncount, 'checks' => $checks,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failures ? 1 : 0);
