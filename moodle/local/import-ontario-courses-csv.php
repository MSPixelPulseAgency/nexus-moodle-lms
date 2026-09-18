<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';
require_once $CFG->dirroot . '/course/lib.php';

global $DB;

$options = getopt('', ['file:']);

if (empty($options['file'])) {
    fwrite(STDERR, "Usage: php import-ontario-courses-csv.php --file=/path/courses.csv\n");
    exit(1);
}

$file = $options['file'];

if (!is_readable($file)) {
    fwrite(STDERR, "CSV file not found: {$file}\n");
    exit(1);
}

function nexus_get_category(
    string $name,
    string $idnumber,
    int $parent = 0
): core_course_category {
    global $DB;

    $existingid = $DB->get_field(
        'course_categories',
        'id',
        ['idnumber' => $idnumber]
    );

    if ($existingid) {
        return core_course_category::get((int)$existingid);
    }

    return core_course_category::create([
        'name' => $name,
        'idnumber' => $idnumber,
        'parent' => $parent,
        'visible' => 1,
        'descriptionformat' => FORMAT_HTML,
    ]);
}

function nexus_import_course(
    core_course_category $category,
    array $row
): void {
    global $DB;

    $code = trim($row['code']);
    $title = trim($row['title']);
    $grade = (int)$row['grade'];
    $type = trim($row['type']);
    $credit = (float)($row['credit'] ?: 1);
    $prerequisite = trim($row['prerequisite'] ?: 'None');
    $description = trim($row['description']);

    if ($code === '' || $title === '' || !$grade) {
        throw new coding_exception('Missing required course information.');
    }

    $existing = $DB->get_record('course', ['shortname' => $code]);

    $summary = '
        <div class="nexus-course-profile">
            <h3>Ontario Ministry Course Information</h3>
            <p><strong>Course code:</strong> ' . s($code) . '</p>
            <p><strong>Grade:</strong> ' . $grade . '</p>
            <p><strong>Course type:</strong> ' . s($type) . '</p>
            <p><strong>Credit value:</strong> ' .
                number_format($credit, 1) . '</p>
            <p><strong>Prerequisite:</strong> ' .
                s($prerequisite) . '</p>
            <h4>Course description</h4>
            <p>' . s($description) . '</p>
            <p><strong>Curriculum source:</strong>
                Ontario Ministry of Education</p>
            <p><strong>Status:</strong>
                Pending Nexus academic and compliance review.</p>
        </div>
    ';

    $data = [
        'category' => $category->id,
        'fullname' => $title . ' | ' . $code,
        'shortname' => $code,
        'idnumber' => 'NEXUS-' . $code,
        'summary' => $summary,
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => 6,
        'enablecompletion' => 1,
        'visible' => 0,
        'showgrades' => 1,
        'newsitems' => 5,
        'lang' => 'en',
    ];

    if ($existing) {
        $data['id'] = $existing->id;
        update_course((object)$data);
        $course = get_course($existing->id);
        echo "UPDATED: {$code}\n";
    } else {
        $course = create_course((object)$data);
        echo "CREATED: {$code}\n";
    }

    $sectionnames = [
        0 => 'Course Information and Announcements',
        1 => 'Strand A',
        2 => 'Strand B',
        3 => 'Strand C',
        4 => 'Strand D',
        5 => 'Culminating Activity',
        6 => 'Final Evaluation',
    ];

    $sections = $DB->get_records(
        'course_sections',
        ['course' => $course->id],
        'section ASC'
    );

    foreach ($sections as $section) {
        $number = (int)$section->section;

        if (isset($sectionnames[$number])) {
            $DB->set_field(
                'course_sections',
                'name',
                $sectionnames[$number],
                ['id' => $section->id]
            );
        }
    }

    rebuild_course_cache($course->id, true);
}

$handle = fopen($file, 'r');
$headers = fgetcsv($handle);

if (!$headers) {
    throw new coding_exception('CSV header is missing.');
}

$headers = array_map('trim', $headers);

$required = [
    'department',
    'department_id',
    'code',
    'title',
    'grade',
    'type',
    'credit',
    'prerequisite',
    'description',
];

foreach ($required as $column) {
    if (!in_array($column, $headers, true)) {
        throw new coding_exception("Missing CSV column: {$column}");
    }
}

$root = nexus_get_category(
    'Ontario Secondary School Courses',
    'ONTARIO-SECONDARY'
);

$count = 0;

while (($values = fgetcsv($handle)) !== false) {
    if (count($values) !== count($headers)) {
        echo "SKIPPED: Invalid column count\n";
        continue;
    }

    $row = array_combine($headers, $values);

    $department = nexus_get_category(
        trim($row['department']),
        trim($row['department_id']),
        $root->id
    );

    $grade = (int)$row['grade'];

    $gradecategory = nexus_get_category(
        "Grade {$grade}",
        trim($row['department_id']) . "-GRADE-{$grade}",
        $department->id
    );

    nexus_import_course($gradecategory, $row);
    $count++;
}

fclose($handle);

echo "\n{$count} courses processed successfully.\n";
echo "All imported courses remain hidden pending Nexus review.\n";
