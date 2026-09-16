<?php

/**
 * Idempotently enrich only the existing MHF4U and SPH4U courses with
 * Moodle-native curriculum, lessons, practice, quizzes, assignments and exams.
 * Existing activities are never deleted or overwritten.
 */
define('CLI_SCRIPT', true);
$options = getopt('', ['dry-run']);
$dryrun = array_key_exists('dry-run', $options);
$root = getenv('NEXUS_MOODLE_ROOT') ?: '/var/www/moodle';
chdir($root);
require $root . '/config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/course/modlib.php';
require_once $CFG->libdir . '/gradelib.php';
require_once $CFG->libdir . '/questionlib.php';
require_once $CFG->dirroot . '/question/editlib.php';
require_once $CFG->dirroot . '/question/engine/bank.php';
require_once $CFG->dirroot . '/mod/quiz/locallib.php';
global $DB, $CFG;

function nexus_find_cm(stdClass $course, string $modname, string $idnumber): ?stdClass {
    global $DB;
    $sql = 'SELECT cm.* FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid AND cm.idnumber = :idnumber AND m.name = :modname
             AND cm.deletioninprogress = 0';
    return $DB->get_record_sql($sql, ['courseid' => $course->id, 'idnumber' => $idnumber, 'modname' => $modname]) ?: null;
}

function nexus_page(stdClass $course, int $section, string $idnumber, string $name, string $html, bool $visible = true): bool {
    global $DB;
    if (nexus_find_cm($course, 'page', $idnumber)) return false;
    $info = (object)[
        'modulename' => 'page', 'module' => $DB->get_field('modules', 'id', ['name' => 'page'], MUST_EXIST),
        'name' => $name, 'intro' => '', 'introformat' => FORMAT_HTML, 'content' => $html,
        'contentformat' => FORMAT_HTML, 'display' => 0, 'printintro' => 0, 'section' => $section,
        'visible' => $visible ? 1 : 0, 'cmidnumber' => $idnumber,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
    ];
    add_moduleinfo($info, $course);
    return true;
}

function nexus_assign(stdClass $course, int $section, string $idnumber, string $name, string $html): bool {
    global $DB;
    if (nexus_find_cm($course, 'assign', $idnumber)) return false;
    $info = (object)[
        'modulename' => 'assign', 'module' => $DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST),
        'name' => $name, 'intro' => $html, 'introformat' => FORMAT_HTML, 'alwaysshowdescription' => 1,
        'submissiondrafts' => 0, 'requiresubmissionstatement' => 1, 'sendnotifications' => 0,
        'sendstudentnotifications' => 1, 'sendlatenotifications' => 0, 'grade' => 100,
        'teamsubmission' => 0, 'requireallteammemberssubmit' => 0, 'blindmarking' => 0,
        'attemptreopenmethod' => 'untilpass', 'maxattempts' => 1, 'markingworkflow' => 0,
        'markingallocation' => 0, 'markinganonymous' => 0, 'activityformat' => 0,
        'timelimit' => 0, 'duedate' => 0, 'cutoffdate' => 0, 'gradingduedate' => 0,
        'allowsubmissionsfromdate' => 0, 'submissionattachments' => 0, 'assignsubmission_onlinetext_enabled' => 1,
        'assignsubmission_file_enabled' => 1, 'assignsubmission_file_maxfiles' => 3,
        'assignsubmission_file_maxsizebytes' => 0, 'section' => $section, 'visible' => 1,
        'cmidnumber' => $idnumber, 'completion' => COMPLETION_TRACKING_AUTOMATIC,
        'completionusegrade' => 1, 'completionsubmit' => 1,
    ];
    add_moduleinfo($info, $course);
    return true;
}

function nexus_quiz(stdClass $course, int $section, string $idnumber, string $name, string $intro): array {
    global $DB;
    $cm = nexus_find_cm($course, 'quiz', $idnumber);
    if ($cm) return [$DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST), false];
    $info = (object)[
        'modulename' => 'quiz', 'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST),
        'name' => $name, 'intro' => $intro, 'introformat' => FORMAT_HTML, 'timeopen' => 0,
        'timeclose' => 0, 'preferredbehaviour' => 'deferredfeedback', 'attempts' => 0,
        'grademethod' => QUIZ_GRADEHIGHEST, 'decimalpoints' => 2, 'questiondecimalpoints' => -1,
        'questionsperpage' => 1, 'shuffleanswers' => 1, 'sumgrades' => 0, 'grade' => 20,
        'timelimit' => 0, 'overduehandling' => 'autosubmit', 'graceperiod' => 86400,
        'quizpassword' => '', 'subnet' => '', 'browsersecurity' => '', 'navmethod' => QUIZ_NAVMETHOD_FREE,
        'section' => $section, 'visible' => 1, 'cmidnumber' => $idnumber,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionusegrade' => 1,
    ];
    $created = add_moduleinfo($info, $course);
    return [$DB->get_record('quiz', ['id' => $created->instance], '*', MUST_EXIST), true];
}

function nexus_category(context_course $context, string $idnumber, string $name): stdClass {
    global $DB;
    $existing = $DB->get_record('question_categories', ['contextid' => $context->id, 'idnumber' => $idnumber]);
    if ($existing) return $existing;
    $top = question_get_top_category($context->id, true);
    $manager = new \core_question\category_manager();
    $data = (object)[
        'name' => $name, 'info' => 'Original Nexus course questions.', 'infoformat' => FORMAT_HTML,
        'stamp' => make_unique_id_code(), 'idnumber' => $idnumber, 'parent' => $top->id,
        'contextid' => $context->id, 'sortorder' => $manager->get_max_sortorder($top->id) + 1,
    ];
    $data->id = $DB->insert_record('question_categories', $data);
    return $data;
}

function nexus_question(stdClass $category, context_course $context, string $idnumber, array $q): stdClass {
    global $DB;
    $sql = 'SELECT q.* FROM {question} q JOIN {question_versions} qv ON qv.questionid = q.id
             JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
             WHERE qbe.questioncategoryid = :categoryid AND qbe.idnumber = :idnumber ORDER BY qv.version DESC';
    $found = $DB->get_records_sql($sql, ['categoryid' => $category->id, 'idnumber' => $idnumber], 0, 1);
    if ($found) return reset($found);
    $question = (object)['qtype' => 'multichoice', 'createdby' => 0, 'idnumber' => $idnumber,
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY];
    $answers = $feedback = $fractions = [];
    foreach ($q['answers'] as $i => $answer) {
        $answers[] = ['text' => $answer, 'format' => FORMAT_PLAIN];
        $feedback[] = ['text' => $i === $q['correct'] ? $q['feedback'] : 'Review the lesson and check each condition.', 'format' => FORMAT_HTML];
        $fractions[] = $i === $q['correct'] ? '1.0' : '0.0';
    }
    $form = (object)[
        'name' => $q['name'], 'questiontext' => ['text' => '<p>' . s($q['text']) . '</p>', 'format' => FORMAT_HTML],
        'generalfeedback' => ['text' => '<p>' . s($q['feedback']) . '</p>', 'format' => FORMAT_HTML],
        'defaultmark' => 1, 'penalty' => 0.25, 'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        'category' => $category->id . ',' . $context->id, 'idnumber' => $idnumber, 'shuffleanswers' => 1,
        'answernumbering' => 'abc', 'single' => '1', 'correctfeedback' => ['text' => 'Correct.', 'format' => FORMAT_HTML],
        'partiallycorrectfeedback' => ['text' => 'Partly correct. Recheck the conditions.', 'format' => FORMAT_HTML],
        'shownumcorrect' => 1, 'incorrectfeedback' => ['text' => 'Not yet. Revisit the lesson and retry.', 'format' => FORMAT_HTML],
        'fraction' => $fractions, 'answer' => $answers, 'feedback' => $feedback, 'hint' => [],
        'hintclearwrong' => [], 'hintshownumcorrect' => [],
    ];
    return question_bank::get_qtype('multichoice')->save_question($question, $form);
}

function nexus_grades(stdClass $course): int {
    $created = 0;
    $root = grade_category::fetch_course_category($course->id);
    foreach (['Knowledge and Understanding', 'Thinking', 'Communication', 'Application'] as $name) {
        $found = grade_category::fetch(['courseid' => $course->id, 'fullname' => $name]);
        if ($found) continue;
        $data = (object)['courseid' => $course->id, 'fullname' => $name, 'parent' => $root->id,
            'aggregation' => GRADE_AGGREGATE_WEIGHTED_MEAN, 'aggregateonlygraded' => 1];
        $cat = new grade_category($data);
        $cat->insert();
        $created++;
    }
    return $created;
}

function nexus_section(stdClass $course, int $number, string $name, string $summary): void {
    global $DB;
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $number], '*', MUST_EXIST);
    course_update_section($course, $section, ['name' => $name, 'summary' => '<p>' . s($summary) . '</p>', 'summaryformat' => FORMAT_HTML, 'visible' => 1]);
}

function lesson_html(string $title, string $expectations, string $body, string $example, string $misconception): string {
    return '<h2>' . s($title) . '</h2><p><strong>Expectation focus:</strong> ' . s($expectations) . '</p>' .
        '<h3>Learning goal</h3><p>' . s($body) . '</p><h3>Worked example</h3><p>' . $example . '</p>' .
        '<h3>Common misconception</h3><p>' . s($misconception) . '</p><h3>Checkpoint</h3><p>Explain your reasoning, label units or restrictions, and verify the result using a second representation when possible.</p>';
}

function practice_html(string $title, array $questions, array $answers): string {
    $html = '<h2>' . s($title) . '</h2><p>Complete these original problems in your notes. Show a clear setup, method, and conclusion.</p><ol>';
    foreach ($questions as $question) $html .= '<li>' . $question . '</li>';
    $html .= '</ol><details><summary>Teacher solution check</summary><ol>';
    foreach ($answers as $answer) $html .= '<li>' . $answer . '</li>';
    return $html . '</ol></details>';
}

function build_course(stdClass $course, array $spec, bool $dryrun): array {
    global $DB;
    $summary = ['courseid' => (int)$course->id, 'sections' => 0, 'pages' => 0, 'quizzes' => 0, 'assignments' => 0, 'questions' => 0, 'gradecategories' => 0];
    if ($dryrun) {
        $summary['existing_modules'] = $DB->count_records('course_modules', ['course' => $course->id]);
        $summary['planned_sections'] = count($spec['sections']);
        return $summary;
    }
    $context = context_course::instance($course->id);
    foreach ($spec['sections'] as $number => $section) {
        nexus_section($course, $number, $section['name'], $section['summary']);
        $summary['sections']++;
    }
    foreach ($spec['general'] as $item) {
        if (nexus_page($course, 0, $item['id'], $item['name'], $item['html'], $item['visible'] ?? true)) $summary['pages']++;
    }
    $category = nexus_category($context, $spec['question_category'], $spec['question_category_name']);
    foreach ($spec['units'] as $unit) {
        $prefix = $spec['prefix'] . '_u' . $unit['number'];
        foreach ($unit['pages'] as $page) {
            if (nexus_page($course, $unit['section'], $prefix . '_' . $page['id'], $page['name'], $page['html'], $page['visible'] ?? true)) $summary['pages']++;
        }
        $exitid = $prefix . '_exit';
        $exithtml = '<h2>' . s($unit['name']) . ' — Exit Card and Reflection</h2><p>Before moving on, submit a short exit card in your notebook or to the teacher: (1) one idea you can now explain, (2) one worked example with units or restrictions, (3) one question that remains, and (4) one connection to another representation or real context.</p><p>Use your response to plan the next practice step. This page is a completion checkpoint, not a graded answer key.</p>';
        if (nexus_page($course, $unit['section'], $exitid, $unit['name'] . ' — Exit Card and Reflection', $exithtml)) $summary['pages']++;
        [$quiz, $created] = nexus_quiz($course, $unit['section'], $prefix . '_quiz', $unit['name'] . ' Formative Quiz', '<p>Formative check for ' . s($unit['name']) . '. Review feedback after each attempt.</p>');
        if ($created) $summary['quizzes']++;
        foreach ($unit['questions'] as $index => $q) {
            $question = nexus_question($category, $context, $prefix . '_q' . ($index + 1), $q);
            $qbeid = $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $question->id], IGNORE_MISSING);
            $already = $qbeid && $DB->record_exists_sql(
                'SELECT 1 FROM {quiz_slots} qs
                   JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = :component AND qr.questionarea = :questionarea
                  WHERE qs.quizid = :quizid AND qr.questionbankentryid = :qbeid',
                ['component' => 'mod_quiz', 'questionarea' => 'slot', 'quizid' => $quiz->id, 'qbeid' => $qbeid]
            );
            if (!$already) {
                quiz_add_quiz_question($question->id, $quiz, 0, 1.0);
                $summary['questions']++;
            }
        }
        $calculator = \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator();
        $calculator->recompute_quiz_sumgrades();
        if (nexus_assign($course, $unit['section'], $prefix . '_assignment', $unit['name'] . ' Investigation', $unit['assignment'])) $summary['assignments']++;
        if (nexus_assign($course, $unit['section'], $prefix . '_test', $unit['name'] . ' Unit Test', $unit['test'])) $summary['assignments']++;
    }
    foreach ($spec['final'] as $item) {
        if (($item['type'] ?? 'page') === 'assign') {
            if (nexus_assign($course, 7, $item['id'], $item['name'], $item['html'])) $summary['assignments']++;
        } else {
            if (nexus_page($course, 7, $item['id'], $item['name'], $item['html'], $item['visible'] ?? true)) $summary['pages']++;
        }
    }
    $summary['gradecategories'] = nexus_grades($course);
    rebuild_course_cache($course->id, true);
    return $summary;
}

$mhf = $DB->get_record('course', ['shortname' => 'MHF4U'], '*', MUST_EXIST);
$sph = $DB->get_record('course', ['shortname' => 'SPH4U'], '*', MUST_EXIST);

$common_general = [
    ['id' => 'nexus_course_instructions', 'name' => 'Important Instructions', 'html' => '<h2>How to use this course</h2><p>Work in order: read the unit introduction, complete the entrance diagnostic, study each lesson, practise, complete the formative quiz, submit the investigation, then complete the unit test and exit card.</p><h3>Learning routine</h3><ul><li>Keep a dated notebook or digital lab journal.</li><li>Show complete reasoning, units, graphs, restrictions, and checks.</li><li>Use technology to explore and verify, not to replace explanation.</li></ul>'],
    ['id' => 'nexus_course_outline', 'name' => 'Course Outline and Curriculum Expectations', 'html' => '<h2>Curriculum map</h2><p>This outline maps the course to the Ontario Ministry curriculum. Expectation labels are references to the official strand structure; lesson examples and questions are original Nexus material.</p>'],
    ['id' => 'nexus_assessment_info', 'name' => 'Assessment and Evaluation Information', 'html' => '<h2>Assessment categories</h2><p>Evidence is organized under Knowledge and Understanding, Thinking, Communication, and Application. Teachers may adjust dates and final weighting to the established Nexus school policy.</p><p>Formative quizzes support learning. Investigations and tests provide graded evidence. Feedback is available through the normal Moodle gradebook and assignment interface.</p>'],
    ['id' => 'nexus_integrity', 'name' => 'Academic Integrity', 'html' => '<h2>Academic integrity</h2><p>Submit original work, document sources, and ask before collaborating. You may use a calculator or graphing technology when a task permits it, but your written reasoning must remain your own.</p>'],
    ['id' => 'nexus_submit', 'name' => 'How to Submit Work', 'html' => '<h2>Submission checklist</h2><ol><li>Read the task and rubric.</li><li>Label files with your name, course, and task.</li><li>Upload a readable PDF or use online text as directed.</li><li>Open the submitted file to confirm it is the intended version.</li><li>Read teacher feedback in Grades when it is released.</li></ol>'],
];

$mhf_units = [
    ['number' => 1, 'section' => 1, 'name' => 'Characteristics and Transformations of Functions', 'pages' => [
        ['id' => 'intro', 'name' => 'Unit 1 Introduction and Diagnostic', 'html' => '<h2>Unit 1 — Characteristics and Transformations of Functions</h2><p><strong>Strand D focus:</strong> representations, transformations, inverse and composite functions, and rates of change.</p><p><strong>Success criteria:</strong> I can move between equations, tables, graphs, and words; state domain and range; and justify an inverse or composition.</p><h3>Entrance diagnostic</h3><ol><li>State the domain of 1/(x−4).</li><li>Describe the vertex of y=(x−2)²+3.</li><li>Find the average rate of change of f(x)=x² from x=1 to x=3.</li></ol>'],
        ['id' => 'lesson2', 'name' => 'Lesson 2 — Composition and Inverses', 'html' => lesson_html('Composition and Inverses', 'D1–D2', 'Use composition to describe chained processes and determine when an inverse is a function.', 'Let f(x)=2x+1 and g(x)=x². Then (g∘f)(x)=(2x+1)², while (f∘g)(x)=2x²+1. The order matters. For f(x)=3x−7, interchange x and y and solve to obtain f⁻¹(x)=(x+7)/3.', 'A function and its reciprocal are not automatically inverses. Check composition, not appearance.')],
        ['id' => 'lesson3', 'name' => 'Lesson 3 — Rates of Change and Representations', 'html' => lesson_html('Rates of Change and Representations', 'D1–D3 and mathematical processes', 'Interpret average rate of change as a slope and connect constant, zero, and changing rates to graphs and contexts.', 'For h(t)=t²+2t, the average rate from t=1 to t=4 is [h(4)−h(1)]/3=(24−3)/3=7. A secant slope summarizes the interval; a tangent idea describes local change.', 'A steep graph is not automatically a high rate of change unless the axes and units are understood.')],
        ['id' => 'practice', 'name' => 'Practice 1 — Functions and Transformations', 'html' => practice_html('Practice 1 — Functions and Transformations', ['Find the inverse of f(x)=(x−5)/2.', 'Describe the transformations in y=−3f(2(x+1))+4.', 'For f(x)=x+2 and g(x)=√x, state the domain of f∘g.', 'Interpret a negative average rate of change in a distance–time context.', 'Give a domain restriction that makes x² invertible.'], ['f⁻¹(x)=2x+5.', 'Left 1, horizontal compression by 2, reflection and vertical stretch by 3, up 4.', 'x≥0.', 'The dependent quantity decreases over the stated interval.', 'x≥0 or x≤0.'])],
    ], 'assignment' => '<h2>Unit 1 Investigation — Transforming a Model</h2><p>Choose a real process with a measurable input and output. Build a parent model, apply at least three transformations, and communicate domain, range, key points, and average rates of change.</p><p><strong>Evidence:</strong> labelled graph, table of values, equation, written interpretation, technology check, and a 150-word reflection.</p><p><strong>Assessment:</strong> Knowledge 25%, Thinking 25%, Communication 25%, Application 25%. Submit PDF or online text.</p>', 'test' => '<h2>Unit 1 Unit Test</h2><p>Complete 8 original questions: notation and domain, inverse, composition, transformations, graph interpretation, and a rate-of-change application. Show algebraic and graphical reasoning. Suggested time: 60 minutes, 40 marks.</p>',
    'questions' => [
        ['name' => 'Composition order', 'text' => 'If f(x)=x+1 and g(x)=2x, what is (g∘f)(3)?', 'answers' => ['8', '7', '9', '6'], 'correct' => 0, 'feedback' => 'f(3)=4 and g(4)=8.'],
        ['name' => 'Inverse domain', 'text' => 'Why is x² not one-to-one on all real numbers?', 'answers' => ['Different inputs can have the same output', 'It has no y-intercept', 'Its range is all reals', 'It is not a function'], 'correct' => 0, 'feedback' => 'For example, f(2)=f(−2)=4.'],
        ['name' => 'Average rate', 'text' => 'What is the average rate of change of f(x)=3x−2 on any interval?', 'answers' => ['3', '−2', 'x', 'It changes'], 'correct' => 0, 'feedback' => 'A linear function has constant slope 3.'],
    ]],
    ['number' => 2, 'section' => 2, 'name' => 'Polynomial Functions', 'pages' => [
        ['id' => 'intro', 'name' => 'Unit 2A Introduction and Diagnostic', 'html' => '<h2>Unit 2A — Polynomial Functions</h2><p><strong>Strand C focus:</strong> degree, leading coefficient, end behaviour, zeros, multiplicity, factoring, and equations and inequalities.</p><h3>Learning goals</h3><ul><li>Connect factored form, intercepts, and multiplicity.</li><li>Use remainder and factor theorems.</li><li>Justify a graph from algebraic evidence.</li></ul><h3>Diagnostic</h3><p>Factor x²−9; describe the end behaviour of −x⁴; and explain what a double zero does to a graph.</p>'],
        ['id' => 'lesson2', 'name' => 'Lesson 2 — Zeros, Factors, and Multiplicity', 'html' => lesson_html('Zeros, Factors, and Multiplicity', 'C1–C3', 'Use the factor theorem and multiplicity to connect algebraic factors to graph behaviour.', 'P(x)=x³−4x²−x+4=(x−4)(x²−1)=(x−4)(x−1)(x+1). Each simple zero crosses the axis. In Q(x)=(x−2)²(x+3), x=2 touches and turns while x=−3 crosses.', 'A zero tells where the graph meets the axis, but multiplicity tells whether the sign changes.')],
        ['id' => 'lesson3', 'name' => 'Lesson 3 — Polynomial Equations and Inequalities', 'html' => lesson_html('Polynomial Equations and Inequalities', 'C3–C4', 'Solve factorable equations and inequalities, verify roots, and communicate intervals on a number line.', 'For (x−1)(x+2)(x−4)≥0, mark −2,1,4 and test one value in each interval. The solution is −2≤x≤1 or x≥4.', 'Do not include a zero automatically in a strict inequality; inspect the inequality symbol.')],
        ['id' => 'practice', 'name' => 'Practice 2A — Polynomial Analysis', 'html' => practice_html('Practice 2A — Polynomial Analysis', ['Use the remainder theorem for P(x)=2x³−x+5 divided by x−2.', 'Factor x³+2x²−9x−18.', 'State zeros and multiplicities of (x+3)²(x−1)³.', 'Describe the end behaviour of −2x⁴+7x.', 'Solve (x−2)(x+1)²<0.'], ['19.', '(x+2)(x−3)(x+3).', '−3 has multiplicity 2; 1 has multiplicity 3.', 'Both ends fall.', 'x<2 and x≠−1, so x<−1 or −1<x<2.'])],
    ], 'assignment' => '<h2>Unit 2A Investigation — Polynomial Design</h2><p>Design a degree-four polynomial with two distinct real zeros, one repeated zero, and negative leading coefficient. Use factored and expanded forms, verify with technology, and explain the graph.</p><p>Include a sign chart, inequality, and a short application where the domain is restricted to a meaningful interval.</p><p>Assessment categories are balanced across knowledge, thinking, communication, and application.</p>', 'test' => '<h2>Unit 2A Unit Test</h2><p>Complete 10 questions on polynomial features, finite differences, factoring, theorem use, roots, multiplicity, equations, inequalities, and a graph-to-equation application. Suggested time: 75 minutes, 50 marks.</p>', 'questions' => [
        ['name' => 'Factor theorem', 'text' => 'If P(3)=0, which statement must be true?', 'answers' => ['x−3 is a factor', 'x+3 is a factor', '3 is the y-intercept', 'The degree is 3'], 'correct' => 0, 'feedback' => 'The factor theorem states that P(a)=0 exactly when x−a is a factor.'],
        ['name' => 'Multiplicity', 'text' => 'What usually happens at a zero of odd multiplicity?', 'answers' => ['The graph crosses the x-axis', 'The graph has a vertical asymptote', 'The graph cannot be factored', 'The graph is constant'], 'correct' => 0, 'feedback' => 'Odd multiplicity changes the sign of the function.'],
        ['name' => 'Inequality endpoint', 'text' => 'For P(x)≥0, when is a zero included?', 'answers' => ['Always, because equality is allowed', 'Never', 'Only if the degree is even', 'Only for negative zeros'], 'correct' => 0, 'feedback' => 'The equality symbol includes permitted zeros.'],
    ]],
    ['number' => 3, 'section' => 3, 'name' => 'Rational Functions', 'pages' => [
        ['id' => 'intro', 'name' => 'Unit 2B Introduction and Diagnostic', 'html' => '<h2>Unit 2B — Rational Functions</h2><p><strong>Strand C focus:</strong> restrictions, holes, asymptotes, intercepts, transformations, equations, and graphical behaviour.</p><p><strong>Prerequisite:</strong> factoring and solving polynomial equations.</p><h3>Diagnostic</h3><p>State restrictions for 1/(x−2), simplify (x²−4)/(x−2), and identify the degree relationship in (3x²+1)/(x²−5).</p>'],
        ['id' => 'lesson2', 'name' => 'Lesson 2 — Restrictions, Holes, and Asymptotes', 'html' => lesson_html('Restrictions, Holes, and Asymptotes', 'C2–C3', 'Distinguish a removable hole from a vertical asymptote and determine horizontal or oblique behaviour.', 'For R(x)=(x²−4)/(x−2), x=2 is excluded, but cancellation gives x+2. The graph is the line y=x+2 with a hole at (2,4). For (2x−1)/(x+5), x=−5 is a vertical asymptote and y=2 is horizontal.', 'Cancellation simplifies a formula but never restores an excluded input from the original denominator.')],
        ['id' => 'lesson3', 'name' => 'Lesson 3 — Rational Equations and Applications', 'html' => lesson_html('Rational Equations and Applications', 'C3–C4', 'Solve rational equations while preserving restrictions and interpret solutions in context.', 'Solve 2/(x−1)=3/(x+2). Restrictions are x≠1,−2. Cross-multiplication gives 2x+4=3x−3, so x=7, which is valid.', 'Cross-multiplication is conditional on non-zero denominators; always check the candidate in the original equation.')],
        ['id' => 'practice', 'name' => 'Practice 2B — Rational Functions', 'html' => practice_html('Practice 2B — Rational Functions', ['State restrictions for (x+4)/(x²−9).', 'Find asymptotes of (2x−1)/(x+5).', 'Find the hole in (x²−4)/(x−2).', 'Solve 1/x+1/(x+2)=1.', 'Explain why an asymptote is not an intercept.'], ['x≠−3,3.', 'x=−5 and y=2.', 'Hole (2,4).', 'x=±√2.', 'An asymptote describes limiting behaviour; an intercept is where the graph meets an axis.'])],
    ], 'assignment' => '<h2>Unit 2B Investigation — Rational Model</h2><p>Investigate R(x)=(x²−x−6)/(x²−4). Factor, state restrictions, identify holes and asymptotes, find intercepts, and verify points on each interval using a graphing tool.</p><p>Explain how the algebra predicts each graphical feature and submit a labelled graph plus a 250-word analysis.</p>', 'test' => '<h2>Unit 2B Unit Test</h2><p>Complete 8 questions on restrictions, simplification, holes, asymptotes, intercepts, equations, inequalities, and an application. State excluded values before multiplying. Suggested time: 60 minutes, 40 marks.</p>', 'questions' => [
        ['name' => 'Restriction', 'text' => 'Which values are excluded from (x+1)/(x²−4)?', 'answers' => ['−2 and 2', '−1 only', '2 only', 'None'], 'correct' => 0, 'feedback' => 'The denominator factors as (x−2)(x+2).'],
        ['name' => 'Hole', 'text' => 'A cancelled common factor creates which feature?', 'answers' => ['A removable discontinuity', 'A horizontal asymptote', 'A maximum', 'A second y-intercept'], 'correct' => 0, 'feedback' => 'The original restriction remains as a hole.'],
        ['name' => 'Degree test', 'text' => 'For equal numerator and denominator degrees, the horizontal asymptote is found using:', 'answers' => ['The ratio of leading coefficients', 'The y-intercept', 'The product of roots', 'The denominator zero'], 'correct' => 0, 'feedback' => 'Equal degrees approach the ratio of leading coefficients.'],
    ]],
    ['number' => 4, 'section' => 5, 'name' => 'Trigonometric Functions', 'pages' => [
        ['id' => 'intro', 'name' => 'Unit 3 Introduction and Diagnostic', 'html' => '<h2>Unit 3 — Trigonometric Functions</h2><p><strong>Strand B focus:</strong> radians, exact values, primary and reciprocal ratios, sinusoidal transformations, identities, equations, and modelling.</p><h3>Diagnostic</h3><p>Convert 180° to radians, state sin(π/6), and identify amplitude and period in y=2cos(3x)−1.</p>'],
        ['id' => 'lesson2', 'name' => 'Lesson 2 — Radians and Sinusoidal Models', 'html' => lesson_html('Radians and Sinusoidal Models', 'B1–B2', 'Use radians as arc length on the unit circle and interpret amplitude, period, phase shift, and midline.', 'For y=3sin(2(x−π/4))+1, amplitude=3, period=π, phase shift=π/4 right, and midline y=1. The maximum is 4 and minimum is −2.', 'The coefficient of x is not the period; period is 2π/|k|.')],
        ['id' => 'lesson3', 'name' => 'Lesson 3 — Identities and Equations', 'html' => lesson_html('Identities and Trigonometric Equations', 'B2–B3', 'Prove identities by transforming one side and solve equations on 0≤x≤2π.', 'To prove (1−cos²x)/sin x=sin x, replace 1−cos²x with sin²x, then simplify to sin x where defined. For 2sin x−1=0, x=π/6 and 5π/6 on [0,2π].', 'An identity is true for every permitted input; an equation may be true only for selected solutions.')],
        ['id' => 'practice', 'name' => 'Practice 3 — Trigonometric Functions', 'html' => practice_html('Practice 3 — Trigonometric Functions', ['Convert 225° to radians.', 'Find amplitude, period, and midline for y=−4cos(πx)+2.', 'Evaluate sin(5π/6) exactly.', 'Solve cos x=−√3/2 on [0,2π].', 'Verify 1+tan²x=sec²x where defined.'], ['5π/4.', 'Amplitude 4, period 2, midline y=2.', '1/2.', 'x=5π/6,7π/6.', 'Divide sin²x+cos²x=1 by cos²x.'])],
    ], 'assignment' => '<h2>Unit 3 Investigation — Seasonal Sinusoidal Model</h2><p>Use a small original data set (temperature, daylight, tide, or another periodic context) to build and critique a sinusoidal model. Include a scatter plot, equation in radians, parameter interpretation, residual discussion, and one prediction with units.</p>', 'test' => '<h2>Unit 3 Unit Test</h2><p>Complete 10 questions on radians, exact ratios, graph features, transformations, identities, equations, and a modelling problem. Suggested time: 75 minutes, 50 marks.</p>', 'questions' => [
        ['name' => 'Radian conversion', 'text' => 'What is 60° in radians?', 'answers' => ['π/3', 'π/6', '2π/3', '3π/2'], 'correct' => 0, 'feedback' => 'Multiply degrees by π/180.'],
        ['name' => 'Period', 'text' => 'What is the period of y=sin(4x)?', 'answers' => ['π/2', '4π', '2π', '1/4'], 'correct' => 0, 'feedback' => 'Use 2π/|k|.'],
        ['name' => 'Identity', 'text' => 'Which equation is a Pythagorean identity?', 'answers' => ['sin²x+cos²x=1', 'sin x+cos x=1', 'tan x=sin x cos x', 'sin x=cos x'], 'correct' => 0, 'feedback' => 'The unit-circle relation holds for every defined x.'],
    ]],
    ['number' => 5, 'section' => 4, 'name' => 'Exponential and Logarithmic Functions', 'pages' => [
        ['id' => 'intro', 'name' => 'Unit 4 Introduction and Diagnostic', 'html' => '<h2>Unit 4 — Exponential and Logarithmic Functions</h2><p><strong>Strand A focus:</strong> inverse relationships, laws, graphs, equations, and growth and decay applications.</p><h3>Diagnostic</h3><p>Evaluate 2³, rewrite 10⁴=10000 in logarithmic form, and state the domain of log(x−5).</p>'],
        ['id' => 'lesson2', 'name' => 'Lesson 2 — Logarithms and Their Laws', 'html' => lesson_html('Logarithms and Their Laws', 'A1–A2', 'Convert between exponential and logarithmic forms, apply product, quotient, and power laws, and state domains.', 'log₂(8)=3 because 2³=8. log_b(MN)=log_bM+log_bN. Thus log₃(9x²)=2+2log₃x for x>0.', 'A logarithm argument must be positive; log(0) and log(−3) are undefined in the real numbers.')],
        ['id' => 'lesson3', 'name' => 'Lesson 3 — Exponential Models and Equations', 'html' => lesson_html('Exponential Models and Equations', 'A2–A3', 'Solve exponential and simple logarithmic equations and interpret parameters in growth and decay models.', 'A(t)=800(1.06)^t models 6% annual growth. To solve 3^(2x−1)=27, write 27=3³, so 2x−1=3 and x=2. For unmatched bases, use logarithms and check the domain.', 'Do not accept an algebraic logarithm solution without checking the original domain.')],
        ['id' => 'practice', 'name' => 'Practice 4 — Exponential and Logarithmic Functions', 'html' => practice_html('Practice 4 — Exponential and Logarithmic Functions', ['Solve 5^(x+1)=125.', 'Expand log₃(9x²/y).', 'Condense 2ln x−ln(x−1).', 'Solve log₁₀(x+4)=2.', 'A quantity starts at 800 and grows 6% annually. Find A(5).'], ['x=2.', '2+2log₃x−log₃y.', 'ln(x²/(x−1)).', 'x=96.', 'Approximately 1070.58.'])],
    ], 'assignment' => '<h2>Unit 4 Investigation — Growth and Decay</h2><p>Compare two original exponential models such as population, medication concentration, battery capacity, or investment value. Estimate parameters, solve a threshold equation, graph both models, and evaluate one social or environmental implication.</p>', 'test' => '<h2>Unit 4 Unit Test</h2><p>Complete 10 questions on laws, graphs, transformations, equations, logarithmic domains, and a growth or decay application. Suggested time: 75 minutes, 50 marks.</p>', 'questions' => [
        ['name' => 'Inverse relation', 'text' => 'Which statement describes y=bˣ and y=log_b(x)?', 'answers' => ['They are inverse functions', 'They have the same domain', 'Both have range all positive reals', 'Neither can be graphed'], 'correct' => 0, 'feedback' => 'The operations undo each other for b>0, b≠1.'],
        ['name' => 'Log domain', 'text' => 'What is the domain of log(x−5)?', 'answers' => ['x>5', 'x≥5', 'x<5', 'All real x'], 'correct' => 0, 'feedback' => 'The argument x−5 must be positive.'],
        ['name' => 'Growth factor', 'text' => 'A 4% annual increase uses which factor?', 'answers' => ['1.04', '0.04', '4', '104'], 'correct' => 0, 'feedback' => 'Growth factor = 1 + rate as a decimal.'],
    ]],
    ['number' => 6, 'section' => 6, 'name' => 'Cumulative Review and Mathematical Processes', 'pages' => [
        ['id' => 'intro', 'name' => 'Cumulative Review and Exit Planning', 'html' => '<h2>Cumulative Review and Mathematical Processes</h2><p>Use this section to connect numeric, graphical, algebraic, and contextual representations across all strands. Your error log should classify mistakes as concept, representation, algebra, communication, or technology use.</p><p>Review: functions and inverses; polynomial and rational features; radians, identities, and models; exponential and logarithmic equations.</p>'],
        ['id' => 'practice', 'name' => 'Cumulative Practice Set', 'html' => practice_html('Cumulative Practice Set', ['Compare a rational and exponential model with the same intercept.', 'Solve a mixed polynomial inequality and represent it on a number line.', 'Build a sinusoidal model from amplitude, period, and a maximum.', 'Explain why a composition may have a smaller domain than either function.', 'Write a complete solution with a reasonableness check.'], ['Answers depend on the model chosen; justify each representation and condition explicitly.'])],
    ], 'assignment' => '<h2>Cumulative Error Analysis</h2><p>Select four corrected problems from earlier units. For each, show the original attempt, identify the misconception, provide a corrected solution, and write one prevention strategy. This is a communication and reflection task.</p>', 'test' => '<h2>Cumulative Readiness Test</h2><p>Complete a balanced mixed assessment across all four Ministry strands. Suggested time: 90 minutes, 60 marks. Use this as preparation for the final exam.</p>', 'questions' => [
        ['name' => 'Representation', 'text' => 'Which representation best reveals a function’s zeros?', 'answers' => ['A graph or factored equation', 'A domain statement alone', 'A constant table only', 'A password'], 'correct' => 0, 'feedback' => 'Zeros are x-intercepts and factors of the numerator or polynomial.'],
        ['name' => 'Reasonableness', 'text' => 'What should a complete applied solution include?', 'answers' => ['Units and an interpretation', 'Only a calculator display', 'Only a final number', 'No restrictions'], 'correct' => 0, 'feedback' => 'Communication and application require context and units.'],
    ]],
];

$mhf_spec = [
    'prefix' => 'nexus_mhf4u', 'question_category' => 'nexus_mhf4u_complete', 'question_category_name' => 'Nexus MHF4U Questions',
    'general' => array_merge($common_general, [['id' => 'nexus_mhf4u_curriculum_map', 'name' => 'MHF4U Ontario Curriculum Map', 'html' => '<h2>Ontario curriculum map</h2><p><strong>Prerequisite:</strong> Functions, Grade 11 University Preparation (MCR3U) or Mathematics for College Technology, Grade 12 (MCT4C).</p><table><thead><tr><th>Strand</th><th>Course emphasis</th><th>Nexus evidence</th></tr></thead><tbody><tr><td>A — Exponential and Logarithmic Functions</td><td>Relationships, graphs, equations, and models</td><td>Unit 4 lessons, practice, quiz, investigation, and test</td></tr><tr><td>B — Trigonometric Functions</td><td>Radians, ratios, graphs, identities, equations</td><td>Unit 3 lessons, practice, quiz, investigation, and test</td></tr><tr><td>C — Polynomial and Rational Functions</td><td>Characteristics, representations, graphing, equations, inequalities</td><td>Units 2A and 2B lessons, practice, quizzes, investigations, and tests</td></tr><tr><td>D — Characteristics of Functions</td><td>Transformations, inverses, composition, rates of change</td><td>Unit 1 lessons, practice, quiz, investigation, and test</td></tr></tbody></table><p>All units intentionally practise problem solving, reasoning and proving, reflecting, selecting tools, connecting representations, and communicating. This is an original course map based on the Ontario Mathematics curriculum; it does not reproduce official expectation wording.</p>'], ['id' => 'nexus_mhf_formula', 'name' => 'MHF4U Formula and Reference Sheet', 'html' => '<h2>Formula and reference sheet</h2><p>Transformations: y=af(k(x−d))+c. Average rate: [f(b)−f(a)]/(b−a). Polynomial remainder: remainder on division by x−a is P(a). Rational restrictions come from original denominators. Trig: period of a sin(kx) or cos(kx) is 2π/|k|. Log laws: product becomes sum, quotient becomes difference, power becomes a multiplier.</p>']]),
    'sections' => [0 => ['name' => 'General Course Information', 'summary' => 'Welcome, instructions, curriculum expectations, assessment, integrity, submission, and reference material.'], 1 => ['name' => 'Unit 1 — Characteristics and Transformations of Functions', 'summary' => 'Strand D: representations, transformations, inverse and composite functions, and rates of change.'], 2 => ['name' => 'Unit 2A — Polynomial Functions', 'summary' => 'Strand C: polynomial characteristics, factoring, zeros, multiplicity, equations, and inequalities.'], 3 => ['name' => 'Unit 2B — Rational Functions', 'summary' => 'Strand C: restrictions, holes, asymptotes, equations, and applications.'], 4 => ['name' => 'Unit 4 — Exponential and Logarithmic Functions', 'summary' => 'Strand A: inverse relationships, laws, graphs, equations, and modelling.'], 5 => ['name' => 'Unit 3 — Trigonometric Functions', 'summary' => 'Strand B: radians, ratios, sinusoidal models, identities, and equations.'], 6 => ['name' => 'Cumulative Review and Mathematical Processes', 'summary' => 'Integrated problem solving, reasoning, representation, communication, and reflection.'], 7 => ['name' => 'FINAL ASSESSMENT', 'summary' => 'Course review, practice assessment, culminating written evaluation, and final exam.']],
    'units' => $mhf_units,
    'final' => [
        ['id' => 'nexus_mhf4u_final_review', 'name' => 'Course Review — All MHF4U Strands', 'html' => '<h2>Course Review</h2><p>Review Strand A logarithms and models; Strand B radians, identities, and sinusoidal equations; Strand C polynomial and rational features, equations, and inequalities; Strand D transformations, inverses, compositions, and rates of change. Rework one problem from each strand and annotate your error log.</p>'],
        ['id' => 'nexus_mhf4u_final_practice', 'name' => 'Final Practice Assessment', 'type' => 'assign', 'html' => '<h2>Final Practice Assessment</h2><p>Complete the original mixed practice set supplied by your teacher or write your own representative set: two questions from each strand, with full solutions and a self-assessment against Knowledge, Thinking, Communication, and Application.</p>'],
        ['id' => 'nexus_mhf4u_final_exam', 'name' => 'FINAL EXAM — Advanced Functions', 'type' => 'assign', 'html' => '<h2>Final Exam — Advanced Functions</h2><p><strong>Suggested duration:</strong> 120 minutes. <strong>Materials:</strong> approved calculator and the teacher-released formula sheet. Complete algebraic, graphical, and applied reasoning across Strands A–D. Submit a single readable PDF; teacher answer key and marking guide remain teacher-controlled.</p><h3>Blueprint</h3><ul><li>Knowledge and Understanding: 25 marks</li><li>Thinking: 20 marks</li><li>Communication: 15 marks</li><li>Application: 20 marks</li></ul>'],
        ['id' => 'nexus_mhf4u_final_key', 'name' => 'Teacher Key — Final Exam', 'visible' => false, 'html' => '<h2>Teacher Key — Final Exam</h2><p>Teacher-only marking guide: award method marks for valid algebraic or graphical reasoning, require restrictions and units where applicable, and record evidence by achievement category. Keep hidden until all attempts are complete.</p>'],
    ],
];

$sph_units = [
    ['number' => 1, 'section' => 2, 'name' => 'Dynamics', 'pages' => [
        ['id' => 'intro', 'name' => 'Unit 1 Introduction and Diagnostic', 'html' => '<h2>Unit 1 — Dynamics</h2><p><strong>Strand B focus:</strong> vectors and kinematics review, Newton’s laws, equilibrium, friction, projectiles, circular motion, and STSE analysis.</p><h3>Safety</h3><p>Use eye protection and a clear area for motion investigations. Never launch objects toward people.</p><h3>Diagnostic</h3><p>Resolve a vector into components, draw a free-body diagram, and state Newton’s second law with SI units.</p>'],
        ['id' => 'lesson2', 'name' => 'Lesson 2 — Forces and Newton’s Laws', 'html' => lesson_html('Forces and Newton’s Laws', 'B2–B3', 'Draw system diagrams, distinguish net force from individual forces, and use ΣF=ma with SI units.', 'A 2.0 kg cart pulled by 10 N on a frictionless track has a=ΣF/m=5.0 m/s². On a rough track, subtract kinetic friction before dividing.', 'Mass is not force. Weight is a force measured in newtons: Fg=mg.')],
        ['id' => 'lesson3', 'name' => 'Lesson 3 — Circular Motion and Investigation', 'html' => lesson_html('Circular Motion and Investigation', 'B1–B3, A1', 'Relate centripetal acceleration to speed and radius and design a controlled investigation.', 'For a 0.50 kg mass moving at 4.0 m/s in a circle of radius 2.0 m, ac=v²/r=8.0 m/s² and Fc=mac=4.0 N toward the centre.', 'Centripetal force is not a new force; it is the net inward force supplied by tension, friction, gravity, or another interaction.')],
        ['id' => 'practice', 'name' => 'Practice 1 — Dynamics', 'html' => practice_html('Practice 1 — Dynamics', ['A 4 kg object accelerates at 3 m/s². Find net force.', 'Resolve 20 N at 30° into horizontal and vertical components.', 'Find friction if a 10 kg box has μk=0.20 on level ground.', 'A 1.5 kg object moves at 6 m/s in a 3 m circle. Find centripetal force.', 'Identify one social or environmental implication of a circular-motion technology.'], ['12 N.', 'Fx≈17.3 N, Fy=10.0 N.', '19.6 N.', '18 N.', 'Answers vary; support the claim with evidence.'])],
    ], 'assignment' => '<h2>Unit 1 Investigation — Friction or Circular Motion</h2><p>Plan and conduct a safe investigation or simulation. State a question, hypothesis, variables, apparatus, procedure, uncertainty, data table, graph, model, and conclusion. Include a short STSE paragraph on a technology such as vehicle safety, centrifuges, or satellites.</p>', 'test' => '<h2>Unit 1 Unit Test — Dynamics</h2><p>Complete conceptual and quantitative questions on vectors, free-body diagrams, Newton’s laws, friction, projectiles, and circular motion. Include units and significant figures. Suggested time: 75 minutes, 50 marks.</p>', 'questions' => [
        ['name' => 'Net force', 'text' => 'What determines acceleration?', 'answers' => ['Net force and mass', 'Mass alone', 'Speed alone', 'Temperature'], 'correct' => 0, 'feedback' => 'Newton’s second law is ΣF=ma.'],
        ['name' => 'Centripetal direction', 'text' => 'The net centripetal force points:', 'answers' => ['Toward the centre', 'Away from the centre', 'Along the tangent', 'Upward always'], 'correct' => 0, 'feedback' => 'Inward net force changes velocity direction.'],
        ['name' => 'SI unit', 'text' => 'What is the SI unit of force?', 'answers' => ['newton', 'joule', 'watt', 'pascal'], 'correct' => 0, 'feedback' => '1 N = 1 kg·m/s².'],
    ]],
    ['number' => 2, 'section' => 3, 'name' => 'Energy and Momentum', 'pages' => [
        ['id' => 'intro', 'name' => 'Unit 2 Introduction and Diagnostic', 'html' => '<h2>Unit 2 — Energy and Momentum</h2><p><strong>Strand C focus:</strong> work, kinetic, gravitational and elastic potential energy, power, efficiency, impulse, momentum, collisions, and STSE.</p><h3>Diagnostic</h3><p>State the units of work, power, and momentum. Decide whether a force does positive, negative, or zero work in three described situations.</p>'],
        ['id' => 'lesson2', 'name' => 'Lesson 2 — Work, Energy, and Power', 'html' => lesson_html('Work, Energy, and Power', 'C2–C3', 'Use W=Fd cosθ, kinetic and potential energy, conservation of energy, power, and efficiency.', 'A 20 N force moves a box 3 m in its direction: W=60 J. If 120 J enters a device and 90 J becomes useful output, efficiency=75%.', 'Energy is not “used up”; it is transferred or transformed, often with thermal dissipation.')],
        ['id' => 'lesson3', 'name' => 'Lesson 3 — Momentum and Collisions', 'html' => lesson_html('Momentum, Impulse, and Collisions', 'C2–C3', 'Relate impulse to change in momentum and analyze one-dimensional collisions.', 'A 0.20 kg ball changes velocity from +10 to −6 m/s in 0.04 s. Δp=m(vf−vi)=−3.2 kg·m/s, so average force=−80 N.', 'Momentum is a vector. Keep a sign convention and report direction.')],
        ['id' => 'practice', 'name' => 'Practice 2 — Energy and Momentum', 'html' => practice_html('Practice 2 — Energy and Momentum', ['Find work done by 50 N over 4 m at 60°.', 'Find kinetic energy of a 1200 kg car at 20 m/s.', 'A 2 kg mass falls 5 m. Find Δ gravitational potential energy.', 'Find momentum of a 0.15 kg puck at 8 m/s.', 'Explain one design choice that improves collision safety.'], ['100 J.', '240 000 J.', '−98 J (system loses gravitational potential energy).', '1.2 kg·m/s.', 'Answers vary; connect impulse, stopping time, and force.'])],
    ], 'assignment' => '<h2>Unit 2 Investigation — Energy or Collision Design</h2><p>Model an energy transfer or collision using data from a safe home experiment or simulation. Include a system boundary, conservation statement, calculations, uncertainty, and an STSE evaluation of efficiency, sustainability, or safety.</p>', 'test' => '<h2>Unit 2 Unit Test — Energy and Momentum</h2><p>Complete questions on work-energy, conservation, power, efficiency, impulse, momentum, and collisions in one and two dimensions. Suggested time: 75 minutes, 50 marks.</p>', 'questions' => [
        ['name' => 'Work', 'text' => 'When is work by a force zero?', 'answers' => ['When force is perpendicular to displacement', 'When force is parallel', 'When mass is zero only', 'When speed increases'], 'correct' => 0, 'feedback' => 'W=Fd cosθ and cos90°=0.'],
        ['name' => 'Momentum unit', 'text' => 'Which is a unit of momentum?', 'answers' => ['kg·m/s', 'J/s', 'N/m', 'kg/m³'], 'correct' => 0, 'feedback' => 'Momentum p=mv.'],
        ['name' => 'Efficiency', 'text' => 'Efficiency equals:', 'answers' => ['useful output/input ×100%', 'input/output ×100%', 'force × distance only', 'power × time only'], 'correct' => 0, 'feedback' => 'Compare useful output with total input.'],
    ]],
    ['number' => 3, 'section' => 4, 'name' => 'Gravitational, Electric, and Magnetic Fields', 'pages' => [
        ['id' => 'intro', 'name' => 'Unit 3 Introduction and Diagnostic', 'html' => '<h2>Unit 3 — Gravitational, Electric, and Magnetic Fields</h2><p><strong>Strand D focus:</strong> inverse-square fields, universal gravitation, Coulomb’s law, electric potential, magnetic force, and field comparisons.</p><h3>Diagnostic</h3><p>Distinguish a field from a force, identify the direction of an electric field around a positive charge, and state the inverse-square pattern.</p>'],
        ['id' => 'lesson2', 'name' => 'Lesson 2 — Gravitational and Electric Fields', 'html' => lesson_html('Gravitational and Electric Fields', 'D2–D3', 'Use Fg=Gm₁m₂/r² and Fe=kq₁q₂/r² and compare field strength with distance.', 'Doubling separation reduces an inverse-square force to one quarter. Field strength g=F/m and E=F/q describe force per unit source quantity.', 'Electric force can attract or repel; gravitational force between ordinary masses is attractive.')],
        ['id' => 'lesson3', 'name' => 'Lesson 3 — Magnetic Fields and Applications', 'html' => lesson_html('Magnetic Fields and Applications', 'D1–D3', 'Use right-hand rules and F=qvB sinθ or F=BIL sinθ to analyze fields and technologies.', 'A 2.0 μC charge moving at 3.0×10⁵ m/s perpendicular to a 0.40 T field experiences F=qvB=0.24 N.', 'The magnetic force is perpendicular to velocity for a perpendicular field; it changes direction, not speed, in ideal uniform motion.')],
        ['id' => 'practice', 'name' => 'Practice 3 — Fields', 'html' => practice_html('Practice 3 — Fields', ['If separation triples, how does an inverse-square force change?', 'Calculate electric force for q₁=2 μC, q₂=3 μC, r=0.30 m.', 'State the direction of E around a positive point charge.', 'Find magnetic force for q=1 μC, v=2×10⁶ m/s, B=0.5 T at 90°.', 'Compare one benefit and one concern of MRI or particle accelerators.'], ['It becomes 1/9.', '0.60 N approximately.', 'Radially outward.', '1.0 N.', 'Answers vary; cite a physical mechanism and impact.'])],
    ], 'assignment' => '<h2>Unit 3 Investigation — Mapping a Field</h2><p>Use a simulation or safe model to map an electric, gravitational, or magnetic field. Include field-line conventions, measurements, a graph of strength versus distance, an inverse-square analysis, and an application or equity/sustainability discussion.</p>', 'test' => '<h2>Unit 3 Unit Test — Fields</h2><p>Complete conceptual and numerical questions on gravitation, electric force, potential, magnetic force, field diagrams, and applications. Suggested time: 80 minutes, 50 marks.</p>', 'questions' => [
        ['name' => 'Inverse square', 'text' => 'If distance doubles in an inverse-square law, the force becomes:', 'answers' => ['one quarter', 'one half', 'twice', 'four times'], 'correct' => 0, 'feedback' => 'F is proportional to 1/r².'],
        ['name' => 'Electric field direction', 'text' => 'Electric field direction is defined as the force on a:', 'answers' => ['positive test charge', 'neutron', 'negative test charge only', 'magnet'], 'correct' => 0, 'feedback' => 'The convention uses a positive test charge.'],
        ['name' => 'Magnetic work', 'text' => 'An ideal magnetic force on a moving charge does no work because it is:', 'answers' => ['perpendicular to velocity', 'parallel to velocity', 'zero everywhere', 'always attractive'], 'correct' => 0, 'feedback' => 'Perpendicular force changes direction but not kinetic energy.'],
    ]],
    ['number' => 4, 'section' => 5, 'name' => 'The Wave Nature of Light', 'pages' => [
        ['id' => 'intro', 'name' => 'Unit 4 Introduction and Diagnostic', 'html' => '<h2>Unit 4 — The Wave Nature of Light</h2><p><strong>Strand E focus:</strong> electromagnetic radiation, interference, diffraction, polarization, wave parameters, and applications.</p><h3>Safety</h3><p>Never look directly into a laser. Use low-power classroom-safe sources and follow teacher instructions.</p>'],
        ['id' => 'lesson2', 'name' => 'Lesson 2 — Waves and Electromagnetic Radiation', 'html' => lesson_html('Waves and Electromagnetic Radiation', 'E2–E3', 'Relate v=fλ, describe the electromagnetic spectrum, and interpret wavefront diagrams.', 'For light with f=6.0×10¹⁴ Hz, λ=c/f=5.0×10⁻⁷ m. Frequency and wavelength are inversely related in a fixed medium.', 'Amplitude is not the same as frequency; amplitude relates to intensity while frequency determines photon energy.')],
        ['id' => 'lesson3', 'name' => 'Lesson 3 — Interference, Diffraction, and Polarization', 'html' => lesson_html('Interference, Diffraction, and Polarization', 'E1–E3', 'Predict constructive and destructive interference and explain how diffraction and polarization support a wave model.', 'For a double slit, bright fringes occur when path difference mλ; dark fringes occur at (m+1/2)λ. A polarizer transmits the component aligned with its axis.', 'Diffraction is most noticeable when aperture size is comparable to wavelength.')],
        ['id' => 'practice', 'name' => 'Practice 4 — Wave Nature of Light', 'html' => practice_html('Practice 4 — Wave Nature of Light', ['Find wavelength for f=5×10¹⁴ Hz.', 'State the condition for constructive interference.', 'Explain why radio waves diffract around obstacles more than visible light.', 'Describe one use of polarization.', 'Predict the effect of doubling slit separation on fringe spacing.'], ['6.0×10⁻⁷ m.', 'Path difference mλ.', 'Longer wavelength diffracts more.', 'Sunglasses, antennas, stress analysis, or communications.', 'Fringes become half as far apart.'])],
    ], 'assignment' => '<h2>Unit 4 Investigation — Interference or Polarization</h2><p>Design a safe simulation or low-power investigation. Record a question, prediction, controlled variables, observations, a quantitative relationship, and a conclusion connecting evidence to the wave model. Include an application and a limitation.</p>', 'test' => '<h2>Unit 4 Unit Test — Wave Nature of Light</h2><p>Complete questions on wave parameters, spectrum, interference, diffraction, polarization, and data interpretation. Suggested time: 70 minutes, 45 marks.</p>', 'questions' => [
        ['name' => 'Wave speed', 'text' => 'For light in vacuum, which relation is correct?', 'answers' => ['c=fλ', 'c=f/λ', 'c=λ/f', 'c=f+λ'], 'correct' => 0, 'feedback' => 'Wave speed equals frequency times wavelength.'],
        ['name' => 'Constructive interference', 'text' => 'Constructive interference occurs when path difference is:', 'answers' => ['mλ', '(m+1/2)λ', 'always zero', '2m+1'], 'correct' => 0, 'feedback' => 'Whole-number wavelengths arrive in phase.'],
        ['name' => 'Polarization', 'text' => 'Polarization is evidence that light has:', 'answers' => ['transverse wave behaviour', 'only particle behaviour', 'no frequency', 'zero speed'], 'correct' => 0, 'feedback' => 'Only transverse waves can be polarized.'],
    ]],
    ['number' => 5, 'section' => 6, 'name' => 'Revolutions in Modern Physics', 'pages' => [
        ['id' => 'intro', 'name' => 'Unit 5 Introduction and Diagnostic', 'html' => '<h2>Unit 5 — Revolutions in Modern Physics</h2><p><strong>Strand F focus:</strong> quantum concepts, photons, photoelectric effect, matter waves, special relativity, mass–energy, and societal implications.</p><h3>Diagnostic</h3><p>Relate photon energy to frequency, identify what a frame of reference means, and distinguish classical from quantum predictions.</p>'],
        ['id' => 'lesson2', 'name' => 'Lesson 2 — Photons and Matter Waves', 'html' => lesson_html('Photons and Matter Waves', 'F2–F3', 'Use E=hf and λ=h/p and explain evidence for particle and wave models.', 'A photon of frequency 6.0×10¹⁴ Hz has E=hf≈3.98×10⁻¹⁹ J. Increasing frequency increases photon energy, not amplitude.', 'The photoelectric effect depends on photon frequency above threshold, not simply on brightness.')],
        ['id' => 'lesson3', 'name' => 'Lesson 3 — Special Relativity and STSE', 'html' => lesson_html('Special Relativity and STSE', 'F1–F3', 'State Einstein’s postulates, interpret time dilation and mass–energy equivalence, and evaluate an application.', 'For γ=1/√(1−v²/c²), a moving clock’s proper interval Δt₀ is related to observer interval Δt=γΔt₀. At everyday speeds γ≈1; near c the difference matters.', 'Relativity does not mean “anything is relative”; the laws of physics and speed of light in vacuum are invariant.')],
        ['id' => 'practice', 'name' => 'Practice 5 — Modern Physics', 'html' => practice_html('Practice 5 — Modern Physics', ['Calculate photon energy for f=5×10¹⁴ Hz.', 'What happens to de Broglie wavelength when momentum doubles?', 'State Einstein’s two postulates.', 'Explain one application of the photoelectric effect.', 'Describe one societal implication of a modern-physics technology.'], ['3.31×10⁻¹⁹ J.', 'It halves.', 'Relativity principle; invariant c in vacuum.', 'Solar cells, sensors, or imaging; explain mechanism.', 'Answers vary; support with evidence and acknowledge trade-offs.'])],
    ], 'assignment' => '<h2>Unit 5 Investigation — Modern Physics and Society</h2><p>Research one original question about a quantum or relativistic technology. Use at least two credible sources, distinguish evidence from interpretation, include one quantitative calculation, and evaluate social, economic, ethical, or environmental implications.</p>', 'test' => '<h2>Unit 5 Unit Test — Modern Physics</h2><p>Complete conceptual and quantitative questions on photons, photoelectric evidence, matter waves, relativity, mass–energy, and STSE. Suggested time: 80 minutes, 50 marks.</p>', 'questions' => [
        ['name' => 'Photon energy', 'text' => 'Photon energy is proportional to:', 'answers' => ['frequency', 'wavelength squared only', 'amplitude only', 'mass of a wire'], 'correct' => 0, 'feedback' => 'E=hf.'],
        ['name' => 'Matter waves', 'text' => 'De Broglie wavelength is:', 'answers' => ['h/p', 'hp', 'p/h', 'mc²'], 'correct' => 0, 'feedback' => 'Matter wavelength decreases as momentum increases.'],
        ['name' => 'Relativity', 'text' => 'Which speed is invariant in special relativity?', 'answers' => ['The speed of light in vacuum', 'Every object’s speed', 'The speed of sound', 'The speed of a train'], 'correct' => 0, 'feedback' => 'All inertial observers measure the same c in vacuum.'],
    ]],
];

$sph_spec = [
    'prefix' => 'nexus_sph4u', 'question_category' => 'nexus_sph4u_complete', 'question_category_name' => 'Nexus SPH4U Questions',
    'general' => array_merge($common_general, [['id' => 'nexus_sph4u_curriculum_map', 'name' => 'SPH4U Ontario Curriculum Map', 'html' => '<h2>Ontario curriculum map</h2><p><strong>Prerequisite:</strong> Physics, Grade 11 University Preparation (SPH3U).</p><table><thead><tr><th>Strand</th><th>Course emphasis</th><th>Nexus evidence</th></tr></thead><tbody><tr><td>A — Scientific Investigation Skills</td><td>Questions, safe inquiry, data, analysis, communication, and STSE</td><td>Investigation launch, lab-journal routine, and embedded STSE prompts</td></tr><tr><td>B — Dynamics</td><td>Forces, Newton’s laws, friction, projectiles, circular motion</td><td>Unit 1 lessons, practice, quiz, investigation, and test</td></tr><tr><td>C — Energy and Momentum</td><td>Work, energy, power, impulse, momentum, collisions</td><td>Unit 2 lessons, practice, quiz, investigation, and test</td></tr><tr><td>D — Gravitational, Electric, and Magnetic Fields</td><td>Fields, forces, potential, magnetic interactions</td><td>Unit 3 lessons, practice, quiz, investigation, and test</td></tr><tr><td>E — The Wave Nature of Light</td><td>Radiation, interference, diffraction, polarization</td><td>Unit 4 lessons, practice, quiz, investigation, and test</td></tr><tr><td>F — Revolutions in Modern Physics</td><td>Quantum ideas, photons, matter waves, relativity, STSE</td><td>Unit 5 lessons, practice, quiz, investigation, and test</td></tr></tbody></table><p>Each unit develops the Ministry fundamental concepts of matter, energy, systems and interactions, structure and function, and sustainability and stewardship through original Nexus activities.</p>'], ['id' => 'nexus_sph_formula', 'name' => 'SPH4U Formula and Reference Sheet', 'html' => '<h2>Formula and reference sheet</h2><p>Dynamics: ΣF=ma, ac=v²/r. Energy: W=Fd cosθ, Ek=½mv², Eg=mgh, P=W/t, η=useful/input. Momentum: p=mv, J=Δp. Fields: Fg=Gm₁m₂/r², Fe=kq₁q₂/r², F=qvB sinθ. Waves: v=fλ. Modern physics: E=hf, λ=h/p, E=mc². State SI units and significant figures.</p>']]),
    'sections' => [0 => ['name' => 'General Course Information', 'summary' => 'Welcome, investigation safety, curriculum expectations, assessment, integrity, submission, and formula reference.'], 1 => ['name' => 'Scientific Investigation Skills and Career Exploration', 'summary' => 'Strand A: questions, safe inquiry, data analysis, communication, and physics careers.'], 2 => ['name' => 'Unit 1 — Dynamics', 'summary' => 'Strand B: forces, Newton’s laws, friction, projectiles, circular motion, and STSE.'], 3 => ['name' => 'Unit 2 — Energy and Momentum', 'summary' => 'Strand C: work, energy, power, impulse, momentum, collisions, and STSE.'], 4 => ['name' => 'Unit 3 — Gravitational, Electric, and Magnetic Fields', 'summary' => 'Strand D: fields, forces, potential, magnetic interactions, and applications.'], 5 => ['name' => 'Unit 4 — The Wave Nature of Light', 'summary' => 'Strand E: electromagnetic radiation, interference, diffraction, polarization, and applications.'], 6 => ['name' => 'Unit 5 — Revolutions in Modern Physics', 'summary' => 'Strand F: quantum mechanics, photons, matter waves, relativity, and STSE.'], 7 => ['name' => 'FINAL ASSESSMENT', 'summary' => 'Course review, practice assessment, culminating evaluation, and final exam.']],
    'units' => $sph_units,
    'final' => [
        ['id' => 'nexus_sph4u_final_review', 'name' => 'Course Review — All SPH4U Strands', 'html' => '<h2>Course Review</h2><p>Review Strand A inquiry and communication; Strand B dynamics; Strand C energy and momentum; Strand D fields; Strand E wave nature of light; and Strand F modern physics. Rework one conceptual and one quantitative problem from each strand with units and a reasonableness check.</p>'],
        ['id' => 'nexus_sph4u_final_practice', 'name' => 'Final Practice Assessment', 'type' => 'assign', 'html' => '<h2>Final Practice Assessment</h2><p>Complete an original comprehensive practice set covering all five content strands. Include a free-body diagram, an energy or momentum model, a field calculation, a wave analysis, a quantum or relativity calculation, and one STSE response.</p>'],
        ['id' => 'nexus_sph4u_final_exam', 'name' => 'FINAL EXAM — Physics', 'type' => 'assign', 'html' => '<h2>Final Exam — Physics</h2><p><strong>Suggested duration:</strong> 120 minutes. <strong>Materials:</strong> approved calculator and teacher-released formula sheet. Complete conceptual and quantitative problems across Strands B–F, show units and reasoning, and submit one readable PDF. Teacher answer key remains hidden until release.</p><h3>Blueprint</h3><ul><li>Knowledge and Understanding: 30 marks</li><li>Thinking: 25 marks</li><li>Communication: 15 marks</li><li>Application: 30 marks</li></ul>'],
        ['id' => 'nexus_sph4u_final_key', 'name' => 'Teacher Key — Final Exam', 'visible' => false, 'html' => '<h2>Teacher Key — Final Exam</h2><p>Teacher-only marking guide. Award method marks for diagrams, equations, substitutions, units, significant figures, and interpretations. Keep hidden until all attempts are complete.</p>'],
    ],
];

if ($dryrun) {
    echo json_encode(['mode' => 'dry-run', 'MHF4U' => build_course($mhf, $mhf_spec, true), 'SPH4U' => build_course($sph, $sph_spec, true)], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}
$transaction = $DB->start_delegated_transaction();
$result = ['MHF4U' => build_course($mhf, $mhf_spec, false), 'SPH4U' => build_course($sph, $sph_spec, false)];
$transaction->allow_commit();
purge_all_caches();
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
