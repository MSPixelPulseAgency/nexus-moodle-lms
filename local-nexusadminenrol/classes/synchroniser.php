<?php
// This file is part of Moodle - http://moodle.org/

namespace local_nexusadminenrol;

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps genuine course enrolments in sync with Moodle's site administrator list.
 */
class synchroniser {
    /** Component used for role assignments owned by this plugin. */
    private const COMPONENT = 'local_nexusadminenrol';

    /**
     * Synchronise every real course and deactivate records owned by the plugin for former administrators.
     *
     * @return array<string, int>
     */
    public static function sync_all(): array {
        global $DB;

        $result = self::new_result();
        $lockfactory = \core\lock\lock_config::get_lock_factory(self::COMPONENT);
        $lock = $lockfactory->get_lock('fullsync', 10, 600);
        if (!$lock) {
            $result['lockskipped']++;
            return $result;
        }

        try {
            $admins = self::get_admins();
            $courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'id ASC');
            $result['admins'] = count($admins);
            $result['courses'] = count($courses);

            foreach ($courses as $course) {
                foreach ($admins as $admin) {
                    self::sync_user_course($admin->id, $course, $result);
                }
            }

            self::deactivate_former_admins(array_keys($admins), $result);
        } finally {
            $lock->release();
        }

        return $result;
    }

    /**
     * Synchronise all current site administrators into one course.
     *
     * @param int $courseid
     * @return array<string, int>
     */
    public static function sync_course(int $courseid): array {
        global $DB;

        $result = self::new_result();
        if ($courseid === SITEID) {
            return $result;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory(self::COMPONENT);
        $lock = $lockfactory->get_lock('fullsync', 10, 600);
        if (!$lock) {
            $result['lockskipped']++;
            return $result;
        }

        try {
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $admins = self::get_admins();
            $result['admins'] = count($admins);
            $result['courses'] = 1;

            foreach ($admins as $admin) {
                self::sync_user_course($admin->id, $course, $result);
            }
        } finally {
            $lock->release();
        }

        return $result;
    }

    /**
     * Return a fresh result structure.
     *
     * @return array<string, int>
     */
    private static function new_result(): array {
        return [
            'admins' => 0,
            'courses' => 0,
            'enrolled' => 0,
            'reactivated' => 0,
            'roleassigned' => 0,
            'unchanged' => 0,
            'deactivated' => 0,
            'lockskipped' => 0,
        ];
    }

    /**
     * Return current site administrators, keyed by user id.
     *
     * @return array<int, \stdClass>
     */
    private static function get_admins(): array {
        $admins = [];
        foreach (get_admins() as $admin) {
            if (!$admin->deleted) {
                $admins[(int)$admin->id] = $admin;
            }
        }
        return $admins;
    }

    /**
     * Ensure one administrator has a genuine active enrolment and the Manager role in a course.
     *
     * @param int $userid
     * @param \stdClass $course
     * @param array<string, int> $result
     */
    private static function sync_user_course(int $userid, \stdClass $course, array &$result): void {
        global $DB;

        if ((int)$course->id === SITEID) {
            return;
        }

        $now = time();
        $tracking = $DB->get_record('local_nexusadminenrol', [
            'userid' => $userid,
            'courseid' => $course->id,
        ]);
        $activeenrolment = self::get_active_enrolment($userid, (int)$course->id);
        $changed = false;

        if (!$activeenrolment) {
            $manual = enrol_get_plugin('manual');
            if (!$manual) {
                throw new \moodle_exception('manualpluginmissing', self::COMPONENT);
            }

            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
            if (!$instance) {
                $instanceid = $manual->add_default_instance($course);
                $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
            }
            if ((int)$instance->status !== ENROL_INSTANCE_ENABLED) {
                throw new \moodle_exception('manualinstancedisabled', self::COMPONENT, '', $course->shortname);
            }

            $userenrolment = $DB->get_record('user_enrolments', [
                'enrolid' => $instance->id,
                'userid' => $userid,
            ]);

            if ($userenrolment) {
                if (!$tracking) {
                    $tracking = self::new_tracking($userid, (int)$course->id, $now);
                    $tracking->enrolid = $instance->id;
                    $tracking->userenrolid = $userenrolment->id;
                    $tracking->modifiedenrolment = 1;
                    $tracking->originalstatus = $userenrolment->status;
                    $tracking->originaltimestart = $userenrolment->timestart;
                    $tracking->originaltimeend = $userenrolment->timeend;
                }
                $manual->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE, 0, 0);
                $tracking->enrolid = $instance->id;
                $tracking->userenrolid = $userenrolment->id;
                $result['reactivated']++;
            } else {
                $manual->enrol_user($instance, $userid, null, 0, 0, ENROL_USER_ACTIVE);
                $userenrolment = $DB->get_record('user_enrolments', [
                    'enrolid' => $instance->id,
                    'userid' => $userid,
                ], '*', MUST_EXIST);
                if (!$tracking) {
                    $tracking = self::new_tracking($userid, (int)$course->id, $now);
                }
                $tracking->enrolid = $instance->id;
                $tracking->userenrolid = $userenrolment->id;
                $tracking->createdenrolment = 1;
                $result['enrolled']++;
            }
            $changed = true;
        }

        static $managerrole = null;
        if ($managerrole === null) {
            $managerrole = $DB->get_record('role', ['shortname' => 'manager'], 'id', MUST_EXIST);
        }
        $context = \context_course::instance($course->id, MUST_EXIST);
        $hasmanagerrole = $DB->record_exists('role_assignments', [
            'roleid' => $managerrole->id,
            'userid' => $userid,
            'contextid' => $context->id,
        ]);
        if (!$hasmanagerrole) {
            role_assign($managerrole->id, $userid, $context->id, self::COMPONENT, 0);
            if (!$tracking) {
                $tracking = self::new_tracking($userid, (int)$course->id, $now);
            }
            $tracking->roleid = $managerrole->id;
            $tracking->createdrole = 1;
            $result['roleassigned']++;
            $changed = true;
        }

        if (!$tracking) {
            $tracking = self::new_tracking($userid, (int)$course->id, $now);
        }
        if (empty($tracking->id)) {
            $tracking->active = 1;
            $tracking->timemodified = $now;
            $tracking->id = $DB->insert_record('local_nexusadminenrol', $tracking);
        } else if ($changed || !$tracking->active) {
            $tracking->active = 1;
            $tracking->timemodified = $now;
            $DB->update_record('local_nexusadminenrol', $tracking);
        }

        if (!$changed) {
            $result['unchanged']++;
        }
    }

    /**
     * Find any currently active enrolment for a user in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return \stdClass|false
     */
    private static function get_active_enrolment(int $userid, int $courseid) {
        global $DB;

        $now = time();
        return $DB->get_record_sql(
            'SELECT ue.*
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid
                AND e.courseid = :courseid
                AND ue.status = :active
                AND e.status = :enabled
                AND (ue.timestart = 0 OR ue.timestart <= :now1)
                AND (ue.timeend = 0 OR ue.timeend > :now2)
           ORDER BY ue.id ASC',
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'active' => ENROL_USER_ACTIVE,
                'enabled' => ENROL_INSTANCE_ENABLED,
                'now1' => $now,
                'now2' => $now,
            ],
            IGNORE_MULTIPLE
        );
    }

    /**
     * Restore only enrolments and roles previously managed by this plugin for former administrators.
     *
     * @param int[] $adminids
     * @param array<string, int> $result
     */
    private static function deactivate_former_admins(array $adminids, array &$result): void {
        global $DB;

        if ($adminids) {
            [$notinsql, $params] = $DB->get_in_or_equal($adminids, SQL_PARAMS_NAMED, 'admin', false);
            $records = $DB->get_records_select(
                'local_nexusadminenrol',
                'active = :active AND userid ' . $notinsql,
                ['active' => 1] + $params
            );
        } else {
            $records = $DB->get_records('local_nexusadminenrol', ['active' => 1]);
        }

        $manual = enrol_get_plugin('manual');
        foreach ($records as $record) {
            $context = \context_course::instance($record->courseid, IGNORE_MISSING);
            if ($record->createdrole && $context) {
                role_unassign($record->roleid, $record->userid, $context->id, self::COMPONENT, 0);
            }

            $instance = $DB->get_record('enrol', ['id' => $record->enrolid, 'courseid' => $record->courseid]);
            $userenrolment = $DB->get_record('user_enrolments', ['id' => $record->userenrolid]);
            if ($manual && $instance && $userenrolment) {
                if ($record->createdenrolment) {
                    $manual->update_user_enrol($instance, $record->userid, ENROL_USER_SUSPENDED);
                } else if ($record->modifiedenrolment) {
                    $manual->update_user_enrol(
                        $instance,
                        $record->userid,
                        $record->originalstatus,
                        $record->originaltimestart,
                        $record->originaltimeend
                    );
                }
            }

            $record->active = 0;
            $record->timemodified = time();
            $DB->update_record('local_nexusadminenrol', $record);
            $result['deactivated']++;
        }
    }

    /**
     * Create an unsaved tracking record with safe defaults.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $now
     * @return \stdClass
     */
    private static function new_tracking(int $userid, int $courseid, int $now): \stdClass {
        return (object)[
            'id' => 0,
            'userid' => $userid,
            'courseid' => $courseid,
            'enrolid' => 0,
            'userenrolid' => 0,
            'createdenrolment' => 0,
            'modifiedenrolment' => 0,
            'originalstatus' => 0,
            'originaltimestart' => 0,
            'originaltimeend' => 0,
            'roleid' => 0,
            'createdrole' => 0,
            'active' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
    }
}
