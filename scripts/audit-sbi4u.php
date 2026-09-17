<?php

/**
 * Read-only SBI4U course audit for the production Moodle instance.
 */

declare(strict_types=1);

define('CLI_SCRIPT', true);

$root = getenv('NEXUS_MOODLE_ROOT') ?: dirname(__DIR__);
if (!is_readable($root . '/config.php')) {
    throw new RuntimeException('Moodle config.php is not readable at the resolved root.');
}

require $root . '/config.php';
require_once $CFG->libdir . '/enrollib.php';
require_once $CFG->libdir . '/gradelib.php';

global $CFG, $DB;

$matches = $DB->get_records('course', ['shortname' => 'SBI4U'], 'id ASC');
if (count($matches) !== 1) {
    throw new RuntimeException('Expected exactly one SBI4U course; found ' . count($matches) . '.');
}
$course = reset($matches);
$coursecontext = context_course::instance((int)$course->id);
$fs = get_file_storage();

$sections = [];
foreach ($DB->get_records('course_sections', ['course' => $course->id], 'section ASC') as $section) {
    $modules = [];
    $sql = 'SELECT cm.*, m.name AS modname
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid AND cm.section = :sectionid
                   AND cm.deletioninprogress = 0
          ORDER BY cm.id ASC';
    foreach ($DB->get_records_sql($sql, ['courseid' => $course->id, 'sectionid' => $section->id]) as $cm) {
        $instance = $DB->get_record($cm->modname, ['id' => $cm->instance]);
        $context = context_module::instance((int)$cm->id, IGNORE_MISSING);
        $files = [];
        if ($context) {
            foreach ($fs->get_area_files($context->id, false, false, 'id ASC', false) as $file) {
                $files[] = [
                    'component' => $file->get_component(),
                    'filearea' => $file->get_filearea(),
                    'itemid' => $file->get_itemid(),
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                    'mimetype' => $file->get_mimetype(),
                    'bytes' => $file->get_filesize(),
                    'contenthash' => $file->get_contenthash(),
                ];
            }
        }
        $modules[] = [
            'cmid' => (int)$cm->id,
            'idnumber' => $cm->idnumber,
            'modname' => $cm->modname,
            'instanceid' => (int)$cm->instance,
            'name' => $instance->name ?? null,
            'visible' => (bool)$cm->visible,
            'availability' => $cm->availability ? json_decode($cm->availability, true) : null,
            'completion' => (int)$cm->completion,
            'completionview' => (int)$cm->completionview,
            'completionexpected' => (int)$cm->completionexpected,
            'grade' => isset($instance->grade) ? (float)$instance->grade : null,
            'intro_has_draftfile' => isset($instance->intro) && str_contains((string)$instance->intro, 'draftfile.php'),
            'files' => $files,
        ];
    }
    $sections[] = [
        'id' => (int)$section->id,
        'number' => (int)$section->section,
        'name' => $section->name,
        'visible' => (bool)$section->visible,
        'availability' => $section->availability ? json_decode($section->availability, true) : null,
        'summary_has_draftfile' => str_contains((string)$section->summary, 'draftfile.php'),
        'module_count' => count($modules),
        'modules' => $modules,
    ];
}

$modulecounts = [];
$rows = $DB->get_records_sql(
    'SELECT m.name AS modname, COUNT(cm.id) AS total
       FROM {course_modules} cm
       JOIN {modules} m ON m.id = cm.module
      WHERE cm.course = :courseid AND cm.deletioninprogress = 0
   GROUP BY m.name ORDER BY m.name',
    ['courseid' => $course->id]
);
foreach ($rows as $row) {
    $modulecounts[$row->modname] = (int)$row->total;
}

$enrolments = [];
$rows = $DB->get_records_sql(
    'SELECT u.id, u.username, u.firstname, u.lastname, r.shortname AS role
       FROM {user} u
       JOIN {role_assignments} ra ON ra.userid = u.id
       JOIN {role} r ON r.id = ra.roleid
      WHERE ra.contextid = :contextid
   ORDER BY u.id, r.shortname',
    ['contextid' => $coursecontext->id]
);
foreach ($rows as $row) {
    $enrolments[] = [
        'userid' => (int)$row->id,
        'username' => $row->username,
        'name' => fullname($row),
        'role' => $row->role,
    ];
}

$gradeitems = [];
$rows = $DB->get_records('grade_items', ['courseid' => $course->id], 'sortorder ASC');
foreach ($rows as $item) {
    $gradeitems[] = [
        'id' => (int)$item->id,
        'itemtype' => $item->itemtype,
        'itemmodule' => $item->itemmodule,
        'iteminstance' => $item->iteminstance ? (int)$item->iteminstance : null,
        'name' => $item->itemname,
        'categoryid' => $item->categoryid ? (int)$item->categoryid : null,
        'grademax' => (float)$item->grademax,
        'aggregationcoef' => (float)$item->aggregationcoef,
    ];
}

$gradecategories = [];
foreach ($DB->get_records('grade_categories', ['courseid' => $course->id], 'depth ASC, id ASC') as $category) {
    $gradecategories[] = [
        'id' => (int)$category->id,
        'parent' => $category->parent ? (int)$category->parent : null,
        'name' => $category->fullname,
        'aggregation' => (int)$category->aggregation,
    ];
}

$questioncategories = [];
$sql = 'SELECT qc.id, qc.name, qc.contextid, qc.parent, COUNT(qe.id) AS entrycount
          FROM {question_categories} qc
     LEFT JOIN {question_bank_entries} qe ON qe.questioncategoryid = qc.id
         WHERE qc.contextid = :contextid
      GROUP BY qc.id, qc.name, qc.contextid, qc.parent
      ORDER BY qc.id';
foreach ($DB->get_records_sql($sql, ['contextid' => $coursecontext->id]) as $category) {
    $questioncategories[] = [
        'id' => (int)$category->id,
        'name' => $category->name,
        'parent' => (int)$category->parent,
        'question_entries' => (int)$category->entrycount,
    ];
}

$userdata = [
    'grade_grades' => (int)$DB->count_records_sql(
        'SELECT COUNT(gg.id) FROM {grade_grades} gg JOIN {grade_items} gi ON gi.id = gg.itemid WHERE gi.courseid = :courseid',
        ['courseid' => $course->id]
    ),
    'assign_submissions' => (int)$DB->count_records_sql(
        'SELECT COUNT(s.id) FROM {assign_submission} s JOIN {assign} a ON a.id = s.assignment WHERE a.course = :courseid',
        ['courseid' => $course->id]
    ),
    'quiz_attempts' => (int)$DB->count_records_sql(
        'SELECT COUNT(qa.id) FROM {quiz_attempts} qa JOIN {quiz} q ON q.id = qa.quiz WHERE q.course = :courseid',
        ['courseid' => $course->id]
    ),
    'completion_records' => (int)$DB->count_records_sql(
        'SELECT COUNT(cmc.id) FROM {course_modules_completion} cmc JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid WHERE cm.course = :courseid',
        ['courseid' => $course->id]
    ),
    'feedback_responses' => (int)$DB->count_records_sql(
        'SELECT COUNT(v.id) FROM {feedback_value} v JOIN {feedback_completed} c ON c.id = v.completed JOIN {feedback} f ON f.id = c.feedback WHERE f.course = :courseid',
        ['courseid' => $course->id]
    ),
];

$userdatadetails = [
    'assign_submissions' => array_values(array_map(static function($row) {
        return [
            'id' => (int)$row->id,
            'assignment' => $row->assignmentname,
            'username' => $row->username,
            'status' => $row->status,
            'timecreated' => (int)$row->timecreated,
            'timemodified' => (int)$row->timemodified,
        ];
    }, $DB->get_records_sql(
        'SELECT s.id, a.name AS assignmentname, u.username, s.status, s.timecreated, s.timemodified
           FROM {assign_submission} s
           JOIN {assign} a ON a.id = s.assignment
           JOIN {user} u ON u.id = s.userid
          WHERE a.course = :courseid
       ORDER BY s.id',
        ['courseid' => $course->id]
    ))),
    'completion_records' => array_values(array_map(static function($row) {
        return [
            'id' => (int)$row->id,
            'activity' => $row->activityname,
            'modname' => $row->modname,
            'username' => $row->username,
            'completionstate' => (int)$row->completionstate,
            'timemodified' => (int)$row->timemodified,
        ];
    }, $DB->get_records_sql(
        "SELECT cmc.id, COALESCE(a.name, b.name, p.name, q.name) AS activityname,
                m.name AS modname, u.username, cmc.completionstate, cmc.timemodified
           FROM {course_modules_completion} cmc
           JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
           JOIN {modules} m ON m.id = cm.module
           JOIN {user} u ON u.id = cmc.userid
      LEFT JOIN {assign} a ON m.name = 'assign' AND a.id = cm.instance
      LEFT JOIN {book} b ON m.name = 'book' AND b.id = cm.instance
      LEFT JOIN {page} p ON m.name = 'page' AND p.id = cm.instance
      LEFT JOIN {quiz} q ON m.name = 'quiz' AND q.id = cm.instance
          WHERE cm.course = :courseid
       ORDER BY cmc.id",
        ['courseid' => $course->id]
    ))),
];

$result = [
    'generated_at' => gmdate('c'),
    'wwwroot' => $CFG->wwwroot,
    'course' => [
        'id' => (int)$course->id,
        'shortname' => $course->shortname,
        'fullname' => $course->fullname,
        'visible' => (bool)$course->visible,
        'enablecompletion' => (bool)$course->enablecompletion,
        'format' => $course->format,
    ],
    'sections' => $sections,
    'module_counts' => $modulecounts,
    'enrolments' => $enrolments,
    'grade_categories' => $gradecategories,
    'grade_items' => $gradeitems,
    'question_categories' => $questioncategories,
    'user_data' => $userdata,
    'user_data_details' => $userdatadetails,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
