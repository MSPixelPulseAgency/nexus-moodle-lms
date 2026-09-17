<?php

declare(strict_types=1);

define('CLI_SCRIPT', true);
$options = getopt('', ['dry-run', 'apply', 'help']);
if (isset($options['help']) || (isset($options['dry-run']) === isset($options['apply']))) {
    echo "Usage: php fix-page-last-modified.php --dry-run|--apply\n";
    exit(isset($options['help']) ? 0 : 1);
}

$apply = isset($options['apply']);
$root = getenv('NEXUS_MOODLE_ROOT') ?: dirname(__DIR__);
chdir($root);
require $root . '/config.php';
require_once $CFG->libdir . '/moodlelib.php';

global $DB;

$pages = array_filter(
    $DB->get_records('page', null, 'id', 'id,course,name,displayoptions,timemodified,content'),
    static function(stdClass $page): bool {
        $options = @unserialize((string)$page->displayoptions);
        return !is_array($options)
            || !isset($options['printlastmodified'])
            || !empty($options['printlastmodified']);
    }
);
$before = [];
foreach ($DB->get_records('page', null, 'id', 'id,timemodified,content') as $page) {
    $before[] = [(int)$page->id, (int)$page->timemodified, hash('sha256', $page->content)];
}
$beforedigest = hash('sha256', json_encode($before));

if ($apply && $pages) {
    $transaction = $DB->start_delegated_transaction();
    foreach ($pages as $page) {
        $displayoptions = @unserialize((string)$page->displayoptions);
        if (!is_array($displayoptions)) {
            $displayoptions = [];
        }
        $displayoptions['printlastmodified'] = 0;
        $DB->set_field('page', 'displayoptions', serialize($displayoptions), ['id' => $page->id]);
    }
    $transaction->allow_commit();
    purge_all_caches();
}

$after = [];
foreach ($DB->get_records('page', null, 'id', 'id,timemodified,content') as $page) {
    $after[] = [(int)$page->id, (int)$page->timemodified, hash('sha256', $page->content)];
}
$afterdigest = hash('sha256', json_encode($after));
$remaining = count(array_filter(
    $DB->get_records('page', null, 'id', 'id,displayoptions'),
    static function(stdClass $page): bool {
        $options = @unserialize((string)$page->displayoptions);
        return !is_array($options)
            || !isset($options['printlastmodified'])
            || !empty($options['printlastmodified']);
    }
));

echo json_encode([
    'mode' => $apply ? 'apply' : 'dry-run',
    'existing_resources_fixed' => $apply ? count($pages) : 0,
    'would_fix' => count($pages),
    'remaining_visible_last_modified' => $remaining,
    'timestamps_and_content_preserved' => $beforedigest === $afterdigest,
    'before_digest' => $beforedigest,
    'after_digest' => $afterdigest,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(($apply && ($remaining !== 0 || $beforedigest !== $afterdigest)) ? 1 : 0);
