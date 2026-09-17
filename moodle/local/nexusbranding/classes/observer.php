<?php

namespace local_nexusbranding;

defined('MOODLE_INTERNAL') || die();

/**
 * Enforces Nexus presentation defaults without changing Moodle core.
 */
final class observer {
    /**
     * Keep technical modification metadata hidden on every Page resource.
     */
    public static function hide_page_last_modified(\core\event\base $event): void {
        global $DB;

        $cm = $DB->get_record(
            'course_modules',
            ['id' => $event->objectid],
            'id, module, instance',
            IGNORE_MISSING
        );
        if (!$cm) {
            return;
        }

        $pagemoduleid = $DB->get_field('modules', 'id', ['name' => 'page']);
        if (!$pagemoduleid || (int)$cm->module !== (int)$pagemoduleid) {
            return;
        }

        $page = $DB->get_record('page', ['id' => $cm->instance], 'id, displayoptions', IGNORE_MISSING);
        if (!$page) {
            return;
        }

        $displayoptions = @unserialize((string)$page->displayoptions);
        if (!is_array($displayoptions)) {
            $displayoptions = [];
        }
        if (empty($displayoptions['printlastmodified'])) {
            return;
        }

        $displayoptions['printlastmodified'] = 0;
        $DB->set_field('page', 'displayoptions', serialize($displayoptions), ['id' => $page->id]);
    }
}
