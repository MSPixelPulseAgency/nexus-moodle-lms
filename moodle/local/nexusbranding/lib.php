<?php

defined('MOODLE_INTERNAL') || die();

function local_nexusbranding_before_standard_html_head() {
    global $PAGE;

    $cssrevision = filemtime(__DIR__ . '/styles/nexus.css');

    $PAGE->requires->css(
        new moodle_url(
            '/local/nexusbranding/styles/nexus.css',
            ['v' => $cssrevision]
        )
    );

    $jsrevision = filemtime(__DIR__ . '/js/nexus.js');

    $PAGE->requires->js(
        new moodle_url(
            '/local/nexusbranding/js/nexus.js',
            ['v' => $jsrevision]
        ),
        true
    );

    return '';
}
