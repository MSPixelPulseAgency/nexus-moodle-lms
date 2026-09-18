<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Return the main SCSS for the Nexus theme.
 */
function theme_nexus_get_main_scss_content($theme): string {
    global $CFG;

    $scss = '';

    $boostfile = $CFG->dirroot . '/theme/boost/scss/preset/default.scss';

    if (is_readable($boostfile)) {
        $scss .= file_get_contents($boostfile);
    }

    $nexusfile = __DIR__ . '/scss/nexus.scss';

    if (is_readable($nexusfile)) {
        $scss .= "\n\n" . file_get_contents($nexusfile);
    }

    return $scss;
}
