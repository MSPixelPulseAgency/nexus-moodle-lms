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

    $PAGE->requires->js(
        '/local/nexusbranding/js/nexus.js',
        true
    );

    return '';
}
