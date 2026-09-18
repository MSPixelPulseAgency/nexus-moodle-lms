<?php

/**
 * Idempotently provision the original Nexus SBI4U asynchronous course.
 *
 * Usage:
 * NEXUS_MOODLE_ROOT=/path/to/moodle \
 * NEXUS_SBI4U_MEDIA=/path/to/staged/media \
 * php provision-sbi4u-course.php --dry-run|--apply
 */

declare(strict_types=1);

define('CLI_SCRIPT', true);

$options = getopt('', ['dry-run', 'apply', 'help']);
if (isset($options['help'])) {
    echo "Usage: php provision-sbi4u-course.php --dry-run|--apply\n";
    exit(0);
}
$dryrun = isset($options['dry-run']);
$apply = isset($options['apply']);
if ($dryrun === $apply) {
    throw new RuntimeException('Choose exactly one of --dry-run or --apply.');
}

$root = getenv('NEXUS_MOODLE_ROOT') ?: dirname(__DIR__);
$mediaroot = rtrim((string)(getenv('NEXUS_SBI4U_MEDIA') ?: '/home/master/nexus-sbi4u-media-20260917'), '/');
$planpath = __DIR__ . '/sbi4u-course-plan.php';
if (!is_readable($root . '/config.php') || !is_readable($planpath)) {
    throw new RuntimeException('Moodle config.php or the SBI4U plan is not readable.');
}

require $root . '/config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/course/modlib.php';
require_once $CFG->libdir . '/completionlib.php';
require_once $CFG->libdir . '/gradelib.php';
require_once $CFG->libdir . '/questionlib.php';
require_once $CFG->dirroot . '/question/editlib.php';
require_once $CFG->dirroot . '/question/engine/bank.php';
require_once $CFG->dirroot . '/mod/quiz/locallib.php';

global $CFG, $DB, $USER;
$plan = require $planpath;
$admin = get_admin();
\core\session\manager::set_user($admin);
$USER = $admin;

$courses = $DB->get_records('course', ['shortname' => 'SBI4U'], 'id ASC');
if (count($courses) !== 1) {
    throw new RuntimeException('Expected exactly one SBI4U course; found ' . count($courses) . '.');
}
$course = reset($courses);
if ((int)$course->id !== 33) {
    throw new RuntimeException('Refusing to provision unexpected SBI4U course id ' . $course->id . '.');
}
$context = context_course::instance((int)$course->id);

$plannedvideos = [];
foreach ($plan['units'] as $unit) {
    foreach ($unit['chapters'] as $chapter) {
        foreach ($chapter['videos'] as $video) {
            $plannedvideos[] = $video;
        }
    }
}
$plannedvideos = array_values(array_unique($plannedvideos));
if (count($plannedvideos) !== 65) {
    throw new RuntimeException('Expected 65 unique planned videos; found ' . count($plannedvideos) . '.');
}
$missingvideos = [];
$totalbytes = 0;
foreach ($plannedvideos as $video) {
    $path = $mediaroot . '/' . $video;
    if (!is_readable($path) || filesize($path) < 1000000) {
        $missingvideos[] = $video;
    } else {
        $totalbytes += filesize($path);
    }
}

if ($dryrun) {
    echo json_encode([
        'mode' => 'dry-run',
        'courseid' => (int)$course->id,
        'units' => count($plan['units']),
        'lessonchapters' => array_sum(array_map(static fn(array $u): int => count($u['chapters']), $plan['units'])),
        'videos' => count($plannedvideos),
        'video_bytes' => $totalbytes,
        'missing_videos' => $missingvideos,
        'existing_modules' => (int)$DB->count_records('course_modules', ['course' => $course->id, 'deletioninprogress' => 0]),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($missingvideos ? 1 : 0);
}
if ($missingvideos) {
    throw new RuntimeException('Media staging is incomplete: ' . implode(', ', $missingvideos));
}

/** Return a stable course module. */
function sbi4u_cm(stdClass $course, string $modname, string $idnumber): ?stdClass {
    global $DB;
    $sql = 'SELECT cm.* FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid AND cm.idnumber = :idnumber
                   AND cm.deletioninprogress = 0 AND m.name = :modname';
    return $DB->get_record_sql($sql, ['courseid' => $course->id, 'idnumber' => $idnumber, 'modname' => $modname]) ?: null;
}

/** Consistent instructional HTML card. */
function sbi4u_panel(string $heading, string $body): string {
    return '<section style="border-left:4px solid #1d5f73;background:#f5fbfc;padding:1rem 1.1rem;margin:1rem 0;border-radius:.35rem">' .
        '<h3 style="margin-top:0">' . s($heading) . '</h3>' . $body . '</section>';
}

/** Render a complete, topic-specific book chapter. */
function sbi4u_chapter_html(array $chapter): string {
    $list = static fn(array $items): string => '<ul>' . implode('', array_map(static fn(string $v): string => '<li>' . s($v) . '</li>', $items)) . '</ul>';
    $goals = [
        'Explain the central biological mechanism using precise Grade 12 terminology.',
        'Interpret a model, pathway, or data set and justify conclusions with evidence.',
        'Connect the concept to investigation, technology, society, or the environment.',
    ];
    $criteria = [
        'I can use the key vocabulary accurately and connect structure with function.',
        'I can explain cause-and-effect rather than only list steps.',
        'I can identify assumptions, limitations, and common misconceptions.',
    ];
    $html = '<div class="nexus-sbi4u-lesson">';
    $html .= '<p><strong>Curriculum connection:</strong> ' . s(implode(', ', $chapter['expectations'])) . '</p>';
    $html .= sbi4u_panel('Learning goals', $list($goals));
    $html .= sbi4u_panel('Success criteria', $list($criteria));
    $html .= '<h3>Key vocabulary</h3><p>' . s($chapter['vocabulary']) . '</p>';
    $html .= '<h3>Prior knowledge</h3><p>Recall relevant cell structures, chemical bonding, energy transfer, and evidence-based scientific reasoning. Record unfamiliar terms before continuing.</p>';
    $html .= '<h3>Detailed explanation</h3><p>' . s($chapter['explanation']) . '</p>';
    $html .= '<h3>Biological model and worked example</h3><p>' . s($chapter['example']) . '</p>';
    $html .= sbi4u_panel('STSE connection', '<p>' . s($chapter['stse']) . '</p>');
    foreach ($chapter['videos'] as $index => $video) {
        $filename = basename($video);
        $html .= '<h3>Learning video ' . ($index + 1) . '</h3>';
        $html .= '<video controls preload="metadata" playsinline style="display:block;width:100%;max-width:100%;height:auto;aspect-ratio:16/9;background:#000;border-radius:.5rem" aria-label="' . s($chapter['title'] . ' learning video ' . ($index + 1)) . '">';
        $html .= '<source src="@@PLUGINFILE@@/' . rawurlencode($filename) . '" type="video/mp4">';
        $html .= 'Your browser does not support the video. Use the surrounding lesson text and contact your teacher if an accessible alternative is needed.</video>';
        $html .= '<p><strong>Viewing purpose:</strong> Pause to annotate the main mechanism, one piece of evidence, and one remaining question.</p>';
    }
    $html .= '<h3>Check your understanding</h3>' . $list($chapter['checks']);
    $html .= sbi4u_panel('Common misconception', '<p>' . s($chapter['misconception']) . '</p>');
    $html .= '<h3>Key takeaways</h3><p>' . s($chapter['explanation']) . '</p>';
    $html .= '<h3>Next step</h3><p>Complete the connected practice in this unit. Use evidence from the lesson and show biological reasoning, not only a final answer.</p>';
    return $html . '</div>';
}

/** Create a page. */
function sbi4u_page(stdClass $course, int $section, string $idnumber, string $name, string $html, bool $completion = false): array {
    global $DB;
    $cm = sbi4u_cm($course, 'page', $idnumber);
    if ($cm) {
        return [$cm, false];
    }
    $info = (object)[
        'modulename' => 'page', 'module' => $DB->get_field('modules', 'id', ['name' => 'page'], MUST_EXIST),
        'name' => $name, 'intro' => '', 'introformat' => FORMAT_HTML, 'content' => $html, 'contentformat' => FORMAT_HTML,
        'display' => 0, 'printintro' => 0, 'printlastmodified' => 0, 'section' => $section, 'visible' => 1,
        'cmidnumber' => $idnumber, 'completion' => $completion ? COMPLETION_TRACKING_AUTOMATIC : COMPLETION_TRACKING_NONE,
        'completionview' => $completion ? 1 : 0,
    ];
    $created = add_moduleinfo($info, $course);
    return [$DB->get_record('course_modules', ['id' => $created->coursemodule], '*', MUST_EXIST), true];
}

/** Create an assignment with safe file and text submission settings. */
function sbi4u_assign(stdClass $course, int $section, string $idnumber, string $name, string $html, float $grade, bool $files = true): array {
    global $DB;
    $cm = sbi4u_cm($course, 'assign', $idnumber);
    if ($cm) {
        return [$cm, false];
    }
    $info = (object)[
        'modulename' => 'assign', 'module' => $DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST),
        'name' => $name, 'intro' => $html, 'introformat' => FORMAT_HTML, 'alwaysshowdescription' => 1,
        'submissiondrafts' => 0, 'requiresubmissionstatement' => 1, 'sendnotifications' => 0,
        'sendstudentnotifications' => 1, 'sendlatenotifications' => 0, 'duedate' => 0, 'allowsubmissionsfromdate' => 0,
        'cutoffdate' => 0, 'gradingduedate' => 0, 'grade' => $grade, 'teamsubmission' => 0,
        'requireallteammemberssubmit' => 0, 'teamsubmissiongroupingid' => 0, 'blindmarking' => 0,
        'attemptreopenmethod' => 'untilpass', 'maxattempts' => 3, 'markingworkflow' => 0, 'markingallocation' => 0,
        'assignsubmission_onlinetext_enabled' => 1, 'assignsubmission_file_enabled' => $files ? 1 : 0,
        'assignsubmission_file_maxfiles' => $files ? 5 : 0, 'assignsubmission_file_maxsizebytes' => 0,
        'section' => $section, 'visible' => 1, 'cmidnumber' => $idnumber,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionsubmit' => 1,
    ];
    $created = add_moduleinfo($info, $course);
    return [$DB->get_record('course_modules', ['id' => $created->coursemodule], '*', MUST_EXIST), true];
}

/** Create a Moodle Book. */
function sbi4u_book(stdClass $course, int $section, string $idnumber, string $name, string $intro): array {
    global $DB;
    $cm = sbi4u_cm($course, 'book', $idnumber);
    if ($cm) {
        return [$cm, $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST), false];
    }
    $info = (object)[
        'modulename' => 'book', 'module' => $DB->get_field('modules', 'id', ['name' => 'book'], MUST_EXIST),
        'name' => $name, 'intro' => $intro, 'introformat' => FORMAT_HTML, 'numbering' => 1,
        'navstyle' => 1, 'customtitles' => 0, 'section' => $section, 'visible' => 1, 'cmidnumber' => $idnumber,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
    ];
    $created = add_moduleinfo($info, $course);
    $cm = $DB->get_record('course_modules', ['id' => $created->coursemodule], '*', MUST_EXIST);
    return [$cm, $DB->get_record('book', ['id' => $created->instance], '*', MUST_EXIST), true];
}

/** Upsert a book chapter and its permanent File API media. */
function sbi4u_book_chapter(stdClass $book, stdClass $cm, array $chapter, string $mediaroot, array &$summary): void {
    global $DB;
    $record = $DB->get_record('book_chapters', ['bookid' => $book->id, 'importsrc' => $chapter['id']]);
    $content = sbi4u_chapter_html($chapter);
    if (!$record) {
        $record = (object)[
            'bookid' => $book->id, 'pagenum' => (int)$DB->get_field_sql('SELECT COALESCE(MAX(pagenum), 0) + 1 FROM {book_chapters} WHERE bookid = ?', [$book->id]),
            'subchapter' => 0, 'title' => $chapter['title'], 'content' => $content, 'contentformat' => FORMAT_HTML,
            'hidden' => 0, 'timecreated' => time(), 'timemodified' => time(), 'importsrc' => $chapter['id'],
        ];
        $record->id = $DB->insert_record('book_chapters', $record);
        $summary['lesson_chapters_created']++;
    } else {
        $record->title = $chapter['title'];
        $record->content = $content;
        $record->contentformat = FORMAT_HTML;
        $record->hidden = 0;
        $record->timemodified = time();
        $DB->update_record('book_chapters', $record);
        $summary['lesson_chapters_updated']++;
    }
    $fs = get_file_storage();
    $context = context_module::instance((int)$cm->id);
    foreach ($chapter['videos'] as $relative) {
        $source = $mediaroot . '/' . $relative;
        $filename = basename($relative);
        $existing = $fs->get_file($context->id, 'mod_book', 'chapter', (int)$record->id, '/', $filename);
        $sourcehash = sha1_file($source);
        if ($existing && $existing->get_contenthash() === $sourcehash && (int)$existing->get_filesize() === (int)filesize($source)) {
            $summary['videos_already_present']++;
            continue;
        }
        if (count($summary['video_replace_reasons']) < 5) {
            $summary['video_replace_reasons'][] = [
                'filename' => $filename,
                'found' => (bool)$existing,
                'stored_hash' => $existing ? $existing->get_contenthash() : null,
                'source_hash' => $sourcehash,
                'stored_bytes' => $existing ? $existing->get_filesize() : null,
                'source_bytes' => filesize($source),
            ];
        }
        if ($existing) {
            $existing->delete();
        }
        $fs->create_file_from_pathname([
            'contextid' => $context->id, 'component' => 'mod_book', 'filearea' => 'chapter',
            'itemid' => (int)$record->id, 'filepath' => '/', 'filename' => $filename,
        ], $source);
        $summary['videos_imported']++;
    }
}

/** Create a quiz. */
function sbi4u_quiz(stdClass $course, int $section, string $idnumber, string $name, string $intro, float $grade = 20): array {
    global $DB;
    $cm = sbi4u_cm($course, 'quiz', $idnumber);
    if ($cm) {
        return [$cm, $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST), false];
    }
    $info = (object)[
        'modulename' => 'quiz', 'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST),
        'name' => $name, 'intro' => $intro, 'introformat' => FORMAT_HTML, 'timeopen' => 0, 'timeclose' => 0,
        'preferredbehaviour' => 'deferredfeedback', 'attempts' => 0, 'attemptonlast' => 0,
        'grademethod' => QUIZ_GRADEHIGHEST, 'decimalpoints' => 2, 'questiondecimalpoints' => -1,
        'questionsperpage' => 1, 'shuffleanswers' => 1, 'sumgrades' => 0, 'grade' => $grade,
        'timelimit' => 0, 'overduehandling' => 'autosubmit', 'graceperiod' => 86400,
        'quizpassword' => '', 'subnet' => '', 'browsersecurity' => '', 'showuserpicture' => 0,
        'showblocks' => 0, 'navmethod' => QUIZ_NAVMETHOD_FREE, 'section' => $section, 'visible' => 1,
        'cmidnumber' => $idnumber, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
        'attemptduring' => 1, 'correctnessduring' => 1, 'maxmarksduring' => 1, 'marksduring' => 1,
        'specificfeedbackduring' => 1, 'generalfeedbackduring' => 1, 'rightanswerduring' => 1, 'overallfeedbackduring' => 0,
        'attemptimmediately' => 1, 'correctnessimmediately' => 1, 'maxmarksimmediately' => 1, 'marksimmediately' => 1,
        'specificfeedbackimmediately' => 1, 'generalfeedbackimmediately' => 1, 'rightanswerimmediately' => 1, 'overallfeedbackimmediately' => 1,
        'attemptopen' => 1, 'correctnessopen' => 1, 'maxmarksopen' => 1, 'marksopen' => 1,
        'specificfeedbackopen' => 1, 'generalfeedbackopen' => 1, 'rightansweropen' => 1, 'overallfeedbackopen' => 1,
        'attemptclosed' => 1, 'correctnessclosed' => 1, 'maxmarksclosed' => 1, 'marksclosed' => 1,
        'specificfeedbackclosed' => 1, 'generalfeedbackclosed' => 1, 'rightanswerclosed' => 1, 'overallfeedbackclosed' => 1,
    ];
    $created = add_moduleinfo($info, $course);
    $cm = $DB->get_record('course_modules', ['id' => $created->coursemodule], '*', MUST_EXIST);
    return [$cm, $DB->get_record('quiz', ['id' => $created->instance], '*', MUST_EXIST), true];
}

/** Create one named question category. */
function sbi4u_question_category(context_course $context, string $idnumber, string $name): stdClass {
    global $DB;
    $category = $DB->get_record('question_categories', ['contextid' => $context->id, 'idnumber' => $idnumber]);
    if ($category) {
        return $category;
    }
    $top = question_get_top_category($context->id, true);
    $manager = new \core_question\category_manager();
    $record = (object)[
        'name' => $name, 'info' => 'Original Nexus SBI4U curriculum-aligned questions.', 'infoformat' => FORMAT_HTML,
        'stamp' => make_unique_id_code(), 'idnumber' => $idnumber, 'parent' => $top->id,
        'contextid' => $context->id, 'sortorder' => $manager->get_max_sortorder((int)$top->id) + 1,
    ];
    $record->id = $DB->insert_record('question_categories', $record);
    return $record;
}

/** Find a stable question. */
function sbi4u_question(stdClass $category, string $idnumber): ?stdClass {
    global $DB;
    $sql = 'SELECT q.* FROM {question} q JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            WHERE qbe.questioncategoryid = :categoryid AND qbe.idnumber = :idnumber ORDER BY qv.version DESC';
    $rows = $DB->get_records_sql($sql, ['categoryid' => $category->id, 'idnumber' => $idnumber], 0, 1);
    return $rows ? reset($rows) : null;
}

/** Create an application-focused multiple-choice question. */
function sbi4u_mcq(stdClass $category, context_course $context, string $idnumber, array $q): array {
    $existing = sbi4u_question($category, $idnumber);
    if ($existing) {
        return [$existing, false];
    }
    $question = (object)['qtype' => 'multichoice', 'createdby' => 0, 'idnumber' => $idnumber,
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY];
    $answers = $feedback = $fractions = [];
    foreach ($q['answers'] as $i => $answer) {
        $answers[] = ['text' => $answer, 'format' => FORMAT_PLAIN];
        $feedback[] = ['text' => $i === $q['correct'] ? $q['feedback'] : 'Review the relevant mechanism and test every option against the evidence.', 'format' => FORMAT_HTML];
        $fractions[] = $i === $q['correct'] ? '1.0' : '0.0';
    }
    $form = (object)[
        'name' => $q['name'], 'questiontext' => ['text' => '<p>' . s($q['text']) . '</p>', 'format' => FORMAT_HTML],
        'generalfeedback' => ['text' => '<p>' . s($q['feedback']) . '</p>', 'format' => FORMAT_HTML],
        'defaultmark' => 1, 'penalty' => 0.3333333, 'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        'category' => $category->id . ',' . $context->id, 'idnumber' => $idnumber, 'shuffleanswers' => 1,
        'answernumbering' => 'abc', 'showstandardinstruction' => 1, 'single' => '1',
        'correctfeedback' => ['text' => 'Correct. The evidence supports this mechanism.', 'format' => FORMAT_HTML],
        'partiallycorrectfeedback' => ['text' => 'Partly correct. Review the evidence.', 'format' => FORMAT_HTML],
        'shownumcorrect' => 1, 'incorrectfeedback' => ['text' => 'Not yet. Revisit the lesson and explain the mechanism.', 'format' => FORMAT_HTML],
        'fraction' => $fractions, 'answer' => $answers, 'feedback' => $feedback,
        'hint' => [], 'hintclearwrong' => [], 'hintshownumcorrect' => [],
    ];
    return [question_bank::get_qtype('multichoice')->save_question($question, $form), true];
}

/** Create a manually graded essay/data-analysis question. */
function sbi4u_essay(stdClass $category, context_course $context, string $idnumber, string $name, string $text, string $guide): array {
    $existing = sbi4u_question($category, $idnumber);
    if ($existing) {
        return [$existing, false];
    }
    $question = (object)['qtype' => 'essay', 'createdby' => 0, 'idnumber' => $idnumber,
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY];
    $form = (object)[
        'name' => $name, 'questiontext' => ['text' => $text, 'format' => FORMAT_HTML],
        'generalfeedback' => ['text' => 'Use the marking guide to revise scientific accuracy, evidence, reasoning, and communication.', 'format' => FORMAT_HTML],
        'defaultmark' => 4, 'penalty' => 0, 'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        'category' => $category->id . ',' . $context->id, 'idnumber' => $idnumber,
        'responseformat' => 'editor', 'responserequired' => 1, 'responsefieldlines' => 15,
        'attachments' => 0, 'attachmentsrequired' => 0,
        'graderinfo' => ['text' => $guide, 'format' => FORMAT_HTML],
        'responsetemplate' => ['text' => '<p><strong>Claim:</strong></p><p><strong>Evidence:</strong></p><p><strong>Reasoning:</strong></p><p><strong>Limitations:</strong></p>', 'format' => FORMAT_HTML],
        'hint' => [], 'hintclearwrong' => [], 'hintshownumcorrect' => [],
    ];
    return [question_bank::get_qtype('essay')->save_question($question, $form), true];
}

/** Attach a question only once. */
function sbi4u_attach_question(stdClass $quiz, stdClass $question, float $maxmark): void {
    global $DB;
    $sql = 'SELECT 1 FROM {quiz_slots} qs JOIN {question_references} qr ON qr.itemid = qs.id
             JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
             JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            WHERE qs.quizid = :quizid AND qv.questionid = :questionid';
    if (!$DB->record_exists_sql($sql, ['quizid' => $quiz->id, 'questionid' => $question->id])) {
        quiz_add_quiz_question($question->id, $quiz, 0, $maxmark);
    }
}

/** Completion availability requiring a module to be complete. */
function sbi4u_require_complete(stdClass $cm, stdClass $required): void {
    global $DB;
    $availability = json_encode(['op' => '&', 'c' => [['type' => 'completion', 'cm' => (int)$required->id, 'e' => COMPLETION_COMPLETE]], 'showc' => [true]]);
    if ($cm->availability !== $availability) {
        $DB->set_field('course_modules', 'availability', $availability, ['id' => $cm->id]);
        $cm->availability = $availability;
    }
}

/** Unit-specific original MCQs. */
function sbi4u_mcqs(int $unit): array {
    $all = [
        1 => [
            ['name' => 'Membrane evidence', 'text' => 'A cell placed in a solution loses mass while solute cannot cross its membrane. Which conclusion is best supported?', 'answers' => ['The solution is hypertonic and net water movement is outward.', 'The solution is hypotonic and active transport removes water.', 'The membrane is impermeable to water.', 'Solute diffusion forces water into the cell.'], 'correct' => 0, 'feedback' => 'Net mass loss with water permeability supports outward osmosis into a hypertonic solution.'],
            ['name' => 'Enzyme plateau', 'text' => 'Reaction rate stops increasing as substrate concentration rises while temperature and pH remain optimal. What best explains the plateau?', 'answers' => ['All enzyme active sites are occupied most of the time.', 'The reaction has become endergonic.', 'The substrate has denatured.', 'Activation energy has increased above the uncatalysed value.'], 'correct' => 0, 'feedback' => 'At substrate saturation, enzyme concentration limits maximum rate.'],
            ['name' => 'Macromolecule structure', 'text' => 'A mutation replaces a charged amino acid with a non-polar amino acid inside a protein. What is the most direct risk?', 'answers' => ['Altered folding and therefore altered function.', 'Conversion of the protein into DNA.', 'Loss of every peptide bond.', 'Automatic increase in transcription.'], 'correct' => 0, 'feedback' => 'Changed side-chain interactions can alter tertiary structure and function.'],
            ['name' => 'Fluid mosaic', 'text' => 'Which observation most directly supports the fluid mosaic model?', 'answers' => ['Membrane proteins move laterally within a phospholipid bilayer.', 'All membrane components are fixed in place.', 'Only water crosses a membrane.', 'The membrane is made entirely of cellulose.'], 'correct' => 0, 'feedback' => 'Lateral movement and varied embedded components are defining evidence.'],
        ],
        2 => [
            ['name' => 'Oxygen role', 'text' => 'A toxin prevents oxygen from accepting electrons in mitochondria. Which change occurs first?', 'answers' => ['Electron transport backs up and oxidative phosphorylation declines.', 'Glycolysis immediately produces oxygen.', 'The Calvin cycle accelerates.', 'Pyruvate gains more carbon atoms.'], 'correct' => 0, 'feedback' => 'Oxygen is the final electron acceptor, so blocking it stops electron flow and the proton gradient.'],
            ['name' => 'Fermentation purpose', 'text' => 'Why can fermentation sustain glycolysis when oxygen is limited?', 'answers' => ['It regenerates NAD+ from NADH.', 'It produces more ATP than respiration.', 'It supplies oxygen to mitochondria.', 'It directly runs the citric acid cycle.'], 'correct' => 0, 'feedback' => 'Fermentation restores NAD+ needed for glycolytic oxidation.'],
            ['name' => 'Photosynthetic oxygen', 'text' => 'Isotope tracing shows released oxygen contains oxygen from labelled water. What does this support?', 'answers' => ['Photolysis of water supplies released oxygen.', 'Carbon dioxide is the direct source of released oxygen.', 'The Calvin cycle splits oxygen gas.', 'ATP synthase produces oxygen.'], 'correct' => 0, 'feedback' => 'Water splitting in light reactions produces molecular oxygen.'],
            ['name' => 'C4 trade-off', 'text' => 'Why can a C4 pathway benefit a plant in hot, bright conditions?', 'answers' => ['It concentrates carbon dioxide near RuBisCO and reduces photorespiration.', 'It eliminates the need for ATP.', 'It prevents all water loss.', 'It performs respiration instead of photosynthesis.'], 'correct' => 0, 'feedback' => 'Carbon concentration reduces RuBisCO oxygenation at an added energy cost.'],
        ],
        3 => [
            ['name' => 'Replication direction', 'text' => 'Why are Okazaki fragments produced on one new DNA strand?', 'answers' => ['DNA polymerase synthesizes only 5-prime to 3-prime while templates are antiparallel.', 'Ligase can copy only short genes.', 'Helicase moves in both directions at once.', 'RNA replaces DNA on that strand.'], 'correct' => 0, 'feedback' => 'Polymerase directionality and antiparallel templates require discontinuous synthesis.'],
            ['name' => 'Frameshift evidence', 'text' => 'A one-base insertion occurs near the start of a coding region. What outcome is most likely?', 'answers' => ['Many downstream codons change.', 'Only one amino acid changes with no other effect.', 'The chromosome becomes a plasmid.', 'Transcription cannot occur anywhere in the genome.'], 'correct' => 0, 'feedback' => 'A non-triplet insertion shifts the downstream reading frame.'],
            ['name' => 'PCR controls', 'text' => 'A PCR negative control produces a clear DNA band. What is the strongest interpretation?', 'answers' => ['Contamination may have produced unreliable results.', 'Every sample is confirmed positive.', 'The gel ran from small to large fragments.', 'Restriction enzymes failed to cut DNA.'], 'correct' => 0, 'feedback' => 'Amplification in a no-template control indicates contamination or setup error.'],
            ['name' => 'Gene regulation', 'text' => 'Two cell types contain the same genome but make different proteins. What best explains this?', 'answers' => ['Different genes are expressed through regulation.', 'Each cell type has a different genetic code.', 'One cell has no DNA.', 'Translation changes DNA sequence.'], 'correct' => 0, 'feedback' => 'Differential expression creates specialized cell functions.'],
        ],
        4 => [
            ['name' => 'Glucose feedback', 'text' => 'Blood glucose falls below its regulated range. Which response supports recovery?', 'answers' => ['Glucagon promotes glycogen breakdown and glucose release.', 'Insulin promotes additional glucose removal.', 'ADH blocks every kidney process.', 'Sweating increases heat loss.'], 'correct' => 0, 'feedback' => 'Glucagon raises blood glucose through liver glycogenolysis and related pathways.'],
            ['name' => 'ADH evidence', 'text' => 'After dehydration, which paired observation is expected in a healthy person?', 'answers' => ['Higher ADH and lower urine volume.', 'Lower ADH and dilute high-volume urine.', 'Higher insulin and no filtration.', 'Lower body temperature and increased glucose.'], 'correct' => 0, 'feedback' => 'ADH increases water reabsorption, concentrating urine and reducing volume.'],
            ['name' => 'Action potential', 'text' => 'A stronger stimulus above threshold usually changes neural signalling by:', 'answers' => ['Increasing action-potential frequency rather than amplitude.', 'Making each action potential continuously larger.', 'Reversing all ion gradients permanently.', 'Preventing neurotransmitter release.'], 'correct' => 0, 'feedback' => 'Action potentials are all-or-none; intensity is often encoded by frequency.'],
            ['name' => 'Steroid feedback', 'text' => 'External anabolic steroid use can reduce natural testosterone production because it:', 'answers' => ['Suppresses hypothalamic and pituitary signalling through negative feedback.', 'Destroys every steroid receptor immediately.', 'Converts neurons into endocrine glands.', 'Raises FSH and LH without limit.'], 'correct' => 0, 'feedback' => 'High external hormone levels inhibit upstream releasing and tropic hormones.'],
        ],
        5 => [
            ['name' => 'Growth model', 'text' => 'A population initially grows rapidly, then growth slows near a stable upper range. Which model best fits?', 'answers' => ['Logistic growth with resource limitation.', 'Unlimited exponential growth.', 'A model with no births or deaths.', 'A constant decline independent of density.'], 'correct' => 0, 'feedback' => 'Logistic growth slows as density-dependent limits approach carrying capacity.'],
            ['name' => 'Mark recapture', 'text' => 'Marked animals become easier to catch during the second sample. How will this likely affect the estimate?', 'answers' => ['It may underestimate population size because recaptures are unusually high.', 'It must exactly equal the true population.', 'It eliminates the need for random sampling.', 'It guarantees an overestimate.'], 'correct' => 0, 'feedback' => 'Excess recaptures reduce the calculated estimate under the model.'],
            ['name' => 'Predator prey', 'text' => 'Predator abundance peaks shortly after prey abundance. What is the best conclusion?', 'answers' => ['The pattern is consistent with a delayed predator response but needs more evidence for causation.', 'Predators always cause prey extinction.', 'The two populations cannot interact.', 'The delay proves density-independent control only.'], 'correct' => 0, 'feedback' => 'Lagged cycles support a hypothesis but alternative drivers and longer data are needed.'],
            ['name' => 'Ecological footprint', 'text' => 'Two populations are equal in size, but one has a much larger ecological footprint. Which factor could explain this?', 'answers' => ['Higher per-capita consumption and more resource-intensive technology.', 'Population size alone determines every footprint.', 'Lower waste production necessarily increases footprint.', 'Age structure has no possible relationship to consumption.'], 'correct' => 0, 'feedback' => 'Impact depends on consumption and technology as well as population.'],
        ],
    ];
    return $all[$unit];
}

/** Assignment and investigation content. */
function sbi4u_assignment_html(array $unit): string {
    return '<h3>Purpose</h3><p>' . s($unit['assignment']) . '</p>' .
        '<h3>Curriculum expectations</h3><p>' . s(implode(', ', $unit['overall'])) . ' and applicable A1 scientific investigation skills.</p>' .
        '<h3>Learning goals</h3><ul><li>Apply biological concepts accurately.</li><li>Analyse evidence and limitations.</li><li>Communicate a justified conclusion.</li></ul>' .
        '<h3>Task and required evidence</h3><p>Submit an original evidence-based response with a biological model or data display, a reasoned analysis, an STSE evaluation, and properly documented sources.</p>' .
        '<h3>Submission requirements</h3><p>Use PDF, DOCX, ODT, image, spreadsheet, presentation, or online text as appropriate. Do not upload executable files. Include citations and accessible labels.</p>' .
        '<h3>Assessment criteria</h3><table><thead><tr><th>Category</th><th>Evidence</th></tr></thead><tbody>' .
        '<tr><td>Knowledge and Understanding</td><td>Accurate concepts and terminology.</td></tr>' .
        '<tr><td>Thinking</td><td>Quality of evidence, analysis, and evaluation.</td></tr>' .
        '<tr><td>Communication</td><td>Clear organization, models, units, and citations.</td></tr>' .
        '<tr><td>Application</td><td>Transfer to an authentic biological or STSE context.</td></tr></tbody></table>';
}

function sbi4u_lab_html(array $unit): string {
    return '<h3>Purpose and question</h3><p>' . s($unit['lab']) . '. Determine how the selected independent variable affects a measurable biological outcome.</p>' .
        '<h3>Background and hypothesis</h3><p>Use the unit learning material to write a mechanism-based prediction. Identify independent, dependent, and controlled variables.</p>' .
        '<h3>Safe procedure and data</h3><p>Use only the teacher-provided simulation, video observation, or supplied data set. Do not perform unsupervised chemical or biological experimentation at home.</p>' .
        '<h3>Required evidence</h3><ol><li>Organized observations and a labelled table.</li><li>An appropriate graph with units.</li><li>Pattern analysis tied to biological mechanism.</li><li>Conclusion addressing the question.</li><li>Evaluation of uncertainty, error, limitations, and an improved design.</li><li>One authentic application.</li></ol>';
}

/** Create or return a weighted grade category. */
function sbi4u_grade_category(stdClass $course, string $fullname, float $weight): grade_category {
    $category = grade_category::fetch(['courseid' => $course->id, 'fullname' => $fullname]);
    if (!$category) {
        $category = new grade_category(['courseid' => $course->id, 'fullname' => $fullname]);
        $category->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN;
        $category->insert('sbi4u-provision');
    }
    $item = $category->get_grade_item();
    $item->aggregationcoef = $weight;
    $item->update('sbi4u-provision');
    return $category;
}

function sbi4u_move_grade_item(stdClass $course, stdClass $cm, grade_category $category): void {
    global $DB;
    $modname = $DB->get_field_sql('SELECT m.name FROM {modules} m JOIN {course_modules} cm ON cm.module = m.id WHERE cm.id = ?', [$cm->id]);
    $item = grade_item::fetch(['courseid' => $course->id, 'itemmodule' => $modname, 'iteminstance' => $cm->instance]);
    if ($item && (int)$item->categoryid !== (int)$category->id) {
        $item->set_parent($category->id);
    }
}

$summary = [
    'courseid' => (int)$course->id, 'books_created' => 0, 'lesson_chapters_created' => 0,
    'lesson_chapters_updated' => 0, 'videos_imported' => 0, 'videos_already_present' => 0,
    'video_replace_reasons' => [],
    'pages_created' => 0, 'assignments_created' => 0, 'quizzes_created' => 0,
    'questions_created' => 0, 'restrictions_configured' => 0,
];

$transaction = $DB->start_delegated_transaction();
course_create_sections_if_missing($course, range(0, 7));
$sectionnames = [
    0 => 'Course Information and Announcements', 1 => 'Scientific Investigation Skills',
    2 => 'Unit 1 - Biochemistry', 3 => 'Unit 2 - Metabolic Processes',
    4 => 'Unit 3 - Molecular Genetics', 5 => 'Unit 4 - Homeostasis',
    6 => 'Unit 5 - Population Dynamics', 7 => 'Final Evaluation',
];
foreach ($sectionnames as $number => $name) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $number], '*', MUST_EXIST);
    course_update_section($course, $section, ['name' => $name, 'summary' => '<p>Original Nexus SBI4U learning and assessment area.</p>', 'summaryformat' => FORMAT_HTML, 'visible' => 1]);
}

[$overviewcm, $created] = sbi4u_page($course, 0, 'nexus_sbi4u_overview', 'SBI4U Course Overview and Learning Path',
    '<h2>Biology, Grade 12, University Preparation</h2><p>This asynchronous course follows the Ontario curriculum and the learning path <strong>Learn - Watch - Read - Understand - Practice - Check - Investigate - Evaluate - Reflect - Progress</strong>.</p><p>Complete original work, document sources, follow laboratory safety, and contact your teacher when an accommodation or accessible alternative is needed.</p>', true);
$summary['pages_created'] += $created ? 1 : 0;

[$skillsbookcm, $skillsbook, $created] = sbi4u_book($course, 1, 'nexus_sbi4u_scientific_skills', 'Scientific Investigation Skills and Career Exploration', 'Ontario expectations A1.1-A1.13 and A2.1-A2.2 are applied throughout all five units.');
$summary['books_created'] += $created ? 1 : 0;
$skillschapters = [
    ['id' => 'skills_inquiry', 'title' => 'A1.1-A1.7 Planning and Conducting Investigations', 'expectations' => ['A1.1', 'A1.2', 'A1.3', 'A1.4', 'A1.5', 'A1.6', 'A1.7'], 'vocabulary' => 'question, hypothesis, variable, control, WHMIS, observation, data, documentation', 'explanation' => 'Scientific inquiry begins with a focused question and testable prediction, then selects appropriate tools, controls, safe procedures, and reliable sources. Accurate records must preserve units, conditions, observations, and changes to procedure.', 'example' => 'Convert a broad question about enzyme rate into a testable investigation with one independent variable, a measurable dependent variable, controls, repeats, and a safety plan.', 'stse' => 'Ethical and safe investigation protects people, organisms, environments, and the integrity of evidence.', 'checks' => ['Is the question testable?', 'Which variables must be controlled?', 'How will data and sources be recorded?'], 'misconception' => 'A control is not “doing nothing”; it provides a comparison that isolates the tested factor.', 'videos' => []],
    ['id' => 'skills_analysis', 'title' => 'A1.8-A1.13 Analysing and Communicating Evidence', 'expectations' => ['A1.8', 'A1.9', 'A1.10', 'A1.11', 'A1.12', 'A1.13'], 'vocabulary' => 'accuracy, precision, uncertainty, bias, reliability, validity, significant figures, conclusion', 'explanation' => 'Analysis organizes qualitative and quantitative evidence, tests a prediction, identifies error and bias, and explains limitations. Communication selects appropriate tables, graphs, diagrams, units, language, and documentation for the audience.', 'example' => 'Use repeated measurements to calculate a mean, recognize an outlier without deleting it automatically, and state a conclusion proportional to the evidence.', 'stse' => 'Transparent methods and uncertainty allow others to evaluate and reproduce scientific claims.', 'checks' => ['Does the evidence support the claim?', 'What limits confidence?', 'Which representation communicates the pattern best?'], 'misconception' => 'A hypothesis is not proven by one result; evidence supports, refutes, or leaves it unresolved.', 'videos' => []],
    ['id' => 'skills_careers', 'title' => 'A2.1-A2.2 Biology Careers and Contributions', 'expectations' => ['A2.1', 'A2.2'], 'vocabulary' => 'career pathway, training, research, contribution, collaboration, Canadian science', 'explanation' => 'Biology careers span health, genetics, wildlife, biotechnology, communication, policy, and education. Contributions are evaluated through evidence, collaboration, social context, and their effects on knowledge and communities.', 'example' => 'Compare education, responsibilities, and ethical obligations for a geneticist, wildlife officer, infectious-disease researcher, and scientific journalist.', 'stse' => 'Career exploration should represent diverse contributors and distinguish discovery, application, and public communication.', 'checks' => ['What training is required?', 'How does the career use evidence?', 'Whose contributions shaped the field?'], 'misconception' => 'Science careers are not limited to laboratory research or medicine.', 'videos' => []],
];
foreach ($skillschapters as $chapter) {
    sbi4u_book_chapter($skillsbook, $skillsbookcm, $chapter, $mediaroot, $summary);
}

$termcategory = sbi4u_grade_category($course, 'Term Evaluation - 70%', 0.70);
$finalcategory = sbi4u_grade_category($course, 'Final Evaluation - 30%', 0.30);
$previouslog = null;

foreach ($plan['units'] as $unitnumber => $unit) {
    $section = (int)$unit['section'];
    [$entrance, $created] = sbi4u_assign($course, $section, "nexus_sbi4u_u{$unitnumber}_entrance", "Unit {$unitnumber} Entrance Card", '<h3>Diagnostic purpose</h3><p>Explain prerequisite ideas in your own words, identify one area of confidence, and identify one question for this unit. This diagnostic is not a unit test.</p>', 0, false);
    $summary['assignments_created'] += $created ? 1 : 0;
    if ($previouslog) {
        sbi4u_require_complete($entrance, $previouslog);
        $summary['restrictions_configured']++;
    }

    [$bookcm, $book, $created] = sbi4u_book($course, $section, "nexus_sbi4u_u{$unitnumber}_book", $unit['book'], '<p>Complete the lesson chapters in order and use the connected practice to check understanding.</p>');
    $summary['books_created'] += $created ? 1 : 0;
    sbi4u_require_complete($bookcm, $entrance);
    $summary['restrictions_configured']++;
    foreach ($unit['chapters'] as $chapter) {
        sbi4u_book_chapter($book, $bookcm, $chapter, $mediaroot, $summary);
    }

    $practicecms = [];
    foreach ($unit['practice'] as $i => $name) {
        $questions = [];
        foreach ($unit['chapters'] as $chapter) {
            $questions[] = $chapter['checks'][$i % count($chapter['checks'])];
        }
        $html = '<h3>Purpose</h3><p>Progress from knowledge and understanding to interpretation, application, and analysis.</p><h3>Questions</h3><ol>' . implode('', array_map(static fn(string $q): string => '<li>' . s($q) . ' Explain with evidence.</li>', $questions)) . '</ol><p>Submit concise original reasoning. Diagrams and tables are encouraged where they clarify a mechanism.</p>';
        [$practice, $created] = sbi4u_assign($course, $section, "nexus_sbi4u_u{$unitnumber}_practice_" . ($i + 1), $name, $html, 10, true);
        $summary['assignments_created'] += $created ? 1 : 0;
        sbi4u_require_complete($practice, $i === 0 ? $bookcm : $practicecms[$i - 1]);
        $summary['restrictions_configured']++;
        $practicecms[] = $practice;
        $solution = '<h3>Reasoning guide</h3><p>Use these criteria after submitting practice: identify the relevant structure or pathway; state the direction of matter, energy, or information flow; connect evidence to a mechanism; include units and assumptions; and explain rather than merely name a term.</p><h3>Common checks</h3><ul>' . implode('', array_map(static fn(array $c): string => '<li><strong>' . s($c['title']) . ':</strong> ' . s($c['explanation']) . '</li>', $unit['chapters'])) . '</ul>';
        [$solutioncm, $solutioncreated] = sbi4u_page($course, $section, "nexus_sbi4u_u{$unitnumber}_solution_" . ($i + 1), 'Solution and Reasoning Guide - ' . $name, $solution, false);
        $summary['pages_created'] += $solutioncreated ? 1 : 0;
        sbi4u_require_complete($solutioncm, $practice);
        $summary['restrictions_configured']++;
    }

    [$lab, $created] = sbi4u_assign($course, $section, "nexus_sbi4u_u{$unitnumber}_lab", "Unit {$unitnumber} Investigation", sbi4u_lab_html($unit), 40, true);
    $summary['assignments_created'] += $created ? 1 : 0;
    sbi4u_require_complete($lab, end($practicecms));
    $summary['restrictions_configured']++;

    $category = sbi4u_question_category($context, "nexus_sbi4u_u{$unitnumber}", $unit['title']);
    [$quizcm, $quiz, $created] = sbi4u_quiz($course, $section, "nexus_sbi4u_u{$unitnumber}_quiz", "Unit {$unitnumber} Quiz", '<p>Application-focused checkpoint with feedback. Review the lesson and practice evidence before attempting.</p>', 20);
    $summary['quizzes_created'] += $created ? 1 : 0;
    sbi4u_require_complete($quizcm, $lab);
    $summary['restrictions_configured']++;
    foreach (sbi4u_mcqs($unitnumber) as $i => $qdata) {
        [$question, $qcreated] = sbi4u_mcq($category, $context, "nexus_sbi4u_u{$unitnumber}_q" . ($i + 1), $qdata);
        $summary['questions_created'] += $qcreated ? 1 : 0;
        sbi4u_attach_question($quiz, $question, 1.0);
    }
    \mod_quiz\quiz_settings::create((int)$quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
    sbi4u_move_grade_item($course, $quizcm, $termcategory);

    [$assignment, $created] = sbi4u_assign($course, $section, "nexus_sbi4u_u{$unitnumber}_assignment", "Unit {$unitnumber} Assignment", sbi4u_assignment_html($unit), 100, true);
    $summary['assignments_created'] += $created ? 1 : 0;
    sbi4u_require_complete($assignment, $quizcm);
    $summary['restrictions_configured']++;
    sbi4u_move_grade_item($course, $assignment, $termcategory);

    $obshtml = '<h3>Teacher prompts</h3><ul><li>Explain one mechanism using a labelled model.</li><li>Interpret an unfamiliar data pattern.</li><li>Defend a conclusion and identify a limitation.</li></ul><h3>Evidence checklist</h3><p>Accuracy; vocabulary; causal reasoning; evidence use; response to feedback. Teachers record authentic observations only. No synthetic student evidence is created.</p>';
    [$observation, $created] = sbi4u_assign($course, $section, "nexus_sbi4u_u{$unitnumber}_observation", "Unit {$unitnumber} Observation and Conversation", $obshtml, 20, false);
    $summary['assignments_created'] += $created ? 1 : 0;
    sbi4u_require_complete($observation, $assignment);
    $summary['restrictions_configured']++;
    sbi4u_move_grade_item($course, $observation, $termcategory);

    [$testcm, $test, $created] = sbi4u_quiz($course, $section, "nexus_sbi4u_u{$unitnumber}_test", "Unit {$unitnumber} Test", '<p>This test assesses Knowledge and Understanding, Thinking, Communication, and Application. Explain reasoning and interpret evidence.</p>', 50);
    $summary['quizzes_created'] += $created ? 1 : 0;
    sbi4u_require_complete($testcm, $observation);
    $summary['restrictions_configured']++;
    foreach (sbi4u_mcqs($unitnumber) as $i => $qdata) {
        [$question] = sbi4u_mcq($category, $context, "nexus_sbi4u_u{$unitnumber}_q" . ($i + 1), $qdata);
        sbi4u_attach_question($test, $question, 1.0);
    }
    [$essay, $qcreated] = sbi4u_essay($category, $context, "nexus_sbi4u_u{$unitnumber}_essay", "Unit {$unitnumber} Evidence Analysis", '<p>Analyse a relevant biological model or data pattern from this unit. State a claim, cite at least two specific observations, explain the mechanism, identify one limitation, and propose one justified next investigation.</p>', '<p>4 marks: scientific accuracy (1), evidence (1), mechanistic reasoning (1), limitation/next step (1).</p>');
    $summary['questions_created'] += $qcreated ? 1 : 0;
    sbi4u_attach_question($test, $essay, 4.0);
    \mod_quiz\quiz_settings::create((int)$test->id)->get_grade_calculator()->recompute_quiz_sumgrades();
    sbi4u_move_grade_item($course, $testcm, $termcategory);

    [$exit, $created] = sbi4u_assign($course, $section, "nexus_sbi4u_u{$unitnumber}_exit", "Unit {$unitnumber} Exit Card", '<p>Identify one concept you can now explain, one misconception you corrected, one piece of evidence that changed your thinking, and one next step.</p>', 0, false);
    $summary['assignments_created'] += $created ? 1 : 0;
    sbi4u_require_complete($exit, $testcm);
    $summary['restrictions_configured']++;
    [$log, $created] = sbi4u_assign($course, $section, "nexus_sbi4u_u{$unitnumber}_log", "Unit {$unitnumber} Learning Log", '<p>Write a concise reflection on progress toward the unit learning goals. Cite feedback, describe a revision, and set one specific study or inquiry goal.</p>', 0, false);
    $summary['assignments_created'] += $created ? 1 : 0;
    sbi4u_require_complete($log, $exit);
    $summary['restrictions_configured']++;
    $previouslog = $log;
}

[$reviewcm, $reviewbook, $created] = sbi4u_book($course, 7, 'nexus_sbi4u_final_review', 'Final Course Review', 'Review all five strands through concepts, models, pathways, data interpretation, and scientific reasoning.');
$summary['books_created'] += $created ? 1 : 0;
sbi4u_require_complete($reviewcm, $previouslog);
$summary['restrictions_configured']++;
foreach ($plan['units'] as $unitnumber => $unit) {
    $reviewchapter = [
        'id' => "final_review_u{$unitnumber}", 'title' => 'Review - ' . $unit['title'], 'expectations' => $unit['overall'],
        'vocabulary' => implode(', ', array_map(static fn(array $c): string => $c['vocabulary'], $unit['chapters'])),
        'explanation' => implode(' ', array_map(static fn(array $c): string => $c['explanation'], $unit['chapters'])),
        'example' => 'Create a concept map linking structures, processes, evidence, and an authentic application from this strand.',
        'stse' => 'Revisit one unit STSE issue and distinguish scientific evidence from value judgments and policy choices.',
        'checks' => array_values(array_merge(...array_map(static fn(array $c): array => $c['checks'], $unit['chapters']))),
        'misconception' => 'Review explanations should connect mechanisms across scales rather than list isolated vocabulary.', 'videos' => [],
    ];
    sbi4u_book_chapter($reviewbook, $reviewcm, $reviewchapter, $mediaroot, $summary);
}

$finalcategoryq = sbi4u_question_category($context, 'nexus_sbi4u_final', 'Final Review');
[$practicecm, $practicequiz, $created] = sbi4u_quiz($course, 7, 'nexus_sbi4u_final_practice', 'Final Practice Assessment', '<p>Comprehensive practice across all five strands with detailed feedback. It does not replace the final evaluation.</p>', 0);
$summary['quizzes_created'] += $created ? 1 : 0;
sbi4u_require_complete($practicecm, $reviewcm);
$summary['restrictions_configured']++;
foreach ($plan['units'] as $unitnumber => $unit) {
    foreach (sbi4u_mcqs($unitnumber) as $i => $qdata) {
        [$question, $qcreated] = sbi4u_mcq($finalcategoryq, $context, "nexus_sbi4u_final_practice_u{$unitnumber}_q" . ($i + 1), $qdata);
        $summary['questions_created'] += $qcreated ? 1 : 0;
        sbi4u_attach_question($practicequiz, $question, 1.0);
    }
}
\mod_quiz\quiz_settings::create((int)$practicequiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

[$culminating, $created] = sbi4u_assign($course, 7, 'nexus_sbi4u_culminating', 'Culminating Biological Systems Investigation', '<h3>Authentic integrative task</h3><p>Investigate a biological issue that connects at least three SBI4U strands. Develop a focused question, synthesize reliable evidence, analyse a model or data set, evaluate an STSE dimension, and communicate a justified conclusion with limitations.</p><h3>Required evidence</h3><p>Research record; biological model; quantitative or qualitative analysis; conclusion; source evaluation; reflection; APA-style references.</p><h3>Assessment</h3><p>Knowledge and Understanding 25%; Thinking 25%; Communication 25%; Application 25%.</p>', 100, true);
$summary['assignments_created'] += $created ? 1 : 0;
sbi4u_require_complete($culminating, $practicecm);
$summary['restrictions_configured']++;
sbi4u_move_grade_item($course, $culminating, $finalcategory);

[$examcm, $exam, $created] = sbi4u_quiz($course, 7, 'nexus_sbi4u_final_exam', 'SBI4U Final Examination', '<h3>Instructions</h3><p>Comprehensive final evaluation across all five strands. Suggested duration: 120 minutes. Permitted resources are determined by the teacher. Show reasoning, label diagrams, include units, and support conclusions with evidence.</p><p><strong>Section A:</strong> selected response and application. <strong>Section B:</strong> data/model analysis and scientific reasoning.</p>', 100);
$summary['quizzes_created'] += $created ? 1 : 0;
sbi4u_require_complete($examcm, $culminating);
$summary['restrictions_configured']++;
foreach ($plan['units'] as $unitnumber => $unit) {
    $qdata = sbi4u_mcqs($unitnumber)[0];
    [$question, $qcreated] = sbi4u_mcq($finalcategoryq, $context, "nexus_sbi4u_exam_u{$unitnumber}_mcq", $qdata);
    $summary['questions_created'] += $qcreated ? 1 : 0;
    sbi4u_attach_question($exam, $question, 1.0);
    [$essay, $qcreated] = sbi4u_essay($finalcategoryq, $context, "nexus_sbi4u_exam_u{$unitnumber}_essay", 'Final Examination - ' . $unit['title'], '<p>Apply this strand to an unfamiliar biological scenario. Interpret evidence, explain the mechanism, connect structure and function or matter/energy/information flow, and evaluate one limitation or STSE implication.</p>', '<p>8 marks: accuracy 2; evidence 2; reasoning 2; communication 1; limitation or STSE evaluation 1.</p>');
    $summary['questions_created'] += $qcreated ? 1 : 0;
    sbi4u_attach_question($exam, $essay, 8.0);
}
\mod_quiz\quiz_settings::create((int)$exam->id)->get_grade_calculator()->recompute_quiz_sumgrades();
sbi4u_move_grade_item($course, $examcm, $finalcategory);

$rootcategory = grade_category::fetch_course_category($course->id);
$rootcategory->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN;
$rootcategory->update('sbi4u-provision');
$DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
$transaction->allow_commit();

rebuild_course_cache((int)$course->id, true);
purge_all_caches();
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
