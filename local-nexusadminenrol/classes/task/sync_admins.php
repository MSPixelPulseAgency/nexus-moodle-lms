<?php
// This file is part of Moodle - http://moodle.org/

namespace local_nexusadminenrol\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled reconciliation for administrator course enrolments.
 */
class sync_admins extends \core\task\scheduled_task {
    /**
     * Return the task's translated name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('synctask', 'local_nexusadminenrol');
    }

    /**
     * Run the reconciliation.
     */
    public function execute(): void {
        $result = \local_nexusadminenrol\synchroniser::sync_all();
        mtrace(get_string('syncresult', 'local_nexusadminenrol', (object)$result));
    }
}
