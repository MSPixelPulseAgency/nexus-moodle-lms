<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\course_module_created',
        'callback' => '\\local_nexusbranding\\observer::hide_page_last_modified',
    ],
    [
        'eventname' => '\\core\\event\\course_module_updated',
        'callback' => '\\local_nexusbranding\\observer::hide_page_last_modified',
    ],
];
