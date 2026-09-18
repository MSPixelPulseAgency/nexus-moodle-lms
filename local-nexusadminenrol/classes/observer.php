<?php
// This file is part of Moodle - http://moodle.org/

namespace local_nexusadminenrol;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for administrator enrolment synchronisation.
 */
class observer {
    /**
     * Enrol every current site administrator into a newly created real course.
     *
     * @param \core\event\course_created $event
     */
    public static function course_created(\core\event\course_created $event): void {
        $courseid = (int)$event->objectid;
        if ($courseid !== SITEID) {
            synchroniser::sync_course($courseid);
        }
    }

    /**
     * Reconcile all courses when the configured site administrator list changes.
     *
     * @param \core\event\config_log_created $event
     */
    public static function config_log_created(\core\event\config_log_created $event): void {
        $other = $event->other;
        if (($other['name'] ?? '') === 'siteadmins'
                && in_array(($other['plugin'] ?? ''), ['', 'core', null], true)) {
            synchroniser::sync_all();
        }
    }

    /**
     * Remove obsolete tracking rows after a course is deleted.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records('local_nexusadminenrol', ['courseid' => (int)$event->objectid]);
    }

    /**
     * Remove obsolete tracking rows after a user is deleted.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('local_nexusadminenrol', ['userid' => (int)$event->objectid]);
    }
}
