<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['help' => false],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised));
}

if ($options['help']) {
    echo "Synchronise all Moodle site administrators into every real course.\n\n"
        . "Options:\n-h, --help    Show this help.\n";
    exit(0);
}

$result = \local_nexusadminenrol\synchroniser::sync_all();
foreach ($result as $name => $value) {
    echo strtoupper($name) . '=' . $value . "\n";
}
