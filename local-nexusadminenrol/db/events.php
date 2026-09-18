<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\course_created',
        'callback' => '\\local_nexusadminenrol\\observer::course_created',
    ],
    [
        'eventname' => '\\core\\event\\config_log_created',
        'callback' => '\\local_nexusadminenrol\\observer::config_log_created',
    ],
    [
        'eventname' => '\\core\\event\\course_deleted',
        'callback' => '\\local_nexusadminenrol\\observer::course_deleted',
    ],
    [
        'eventname' => '\\core\\event\\user_deleted',
        'callback' => '\\local_nexusadminenrol\\observer::user_deleted',
    ],
];
