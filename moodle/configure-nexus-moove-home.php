<?php

define('CLI_SCRIPT', true);

require __DIR__ . '/config.php';

global $CFG;

require_once($CFG->libdir . '/filelib.php');

echo PHP_EOL;
echo "==========================================" . PHP_EOL;
echo " Nexus EPS — Configure Moove Front Page" . PHP_EOL;
echo "==========================================" . PHP_EOL;


/*
|--------------------------------------------------------------------------
| Hero source files
|--------------------------------------------------------------------------
|
| IMPORTANT:
| hero-04 is first because that is the image Nexus should show first.
|
*/

$heroes = [
    1 => __DIR__ . '/local/nexusbranding/pix/hero-04.png',
    2 => __DIR__ . '/local/nexusbranding/pix/hero-01.png',
    3 => __DIR__ . '/local/nexusbranding/pix/hero-02.png',
    4 => __DIR__ . '/local/nexusbranding/pix/hero-03.png',
];


/*
|--------------------------------------------------------------------------
| Validate source images
|--------------------------------------------------------------------------
*/

foreach ($heroes as $index => $path) {

    if (!file_exists($path)) {
        throw new Exception(
            "Missing hero image {$index}: {$path}"
        );
    }

    echo "FOUND HERO {$index}: "
        . basename($path)
        . PHP_EOL;
}


/*
|--------------------------------------------------------------------------
| Disable all unwanted Moove homepage sections
|--------------------------------------------------------------------------
*/

set_config(
    'displaymarketingbox',
    0,
    'theme_moove'
);

set_config(
    'numbersfrontpage',
    0,
    'theme_moove'
);

set_config(
    'faqcount',
    0,
    'theme_moove'
);


/*
|--------------------------------------------------------------------------
| Enable exactly four native Moove slides
|--------------------------------------------------------------------------
*/

set_config(
    'slidercount',
    4,
    'theme_moove'
);


/*
|--------------------------------------------------------------------------
| Store images using Moodle File API
|--------------------------------------------------------------------------
*/

$context = context_system::instance();

$fs = get_file_storage();


foreach ($heroes as $index => $sourcepath) {

    $filearea =
        'sliderimage' . $index;

    /*
     * Delete any old slider image.
     */
    $fs->delete_area_files(
        $context->id,
        'theme_moove',
        $filearea,
        0
    );

    /*
     * Use stable filename.
     */
    $filename =
        'nexus-slide-' . $index . '.png';

    $filerecord = [
        'contextid' => $context->id,
        'component' => 'theme_moove',
        'filearea' => $filearea,
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $filename,
    ];

    $file =
        $fs->create_file_from_pathname(
            $filerecord,
            $sourcepath
        );

    if (!$file) {
        throw new Exception(
            "Could not store slide {$index}"
        );
    }

    /*
     * admin_setting_configstoredfile expects the
     * filename in config.
     */
    set_config(
        'sliderimage' . $index,
        $filename,
        'theme_moove'
    );

    /*
     * Absolutely no text overlay.
     */
    set_config(
        'slidertitle' . $index,
        '',
        'theme_moove'
    );

    set_config(
        'slidercap' . $index,
        '',
        'theme_moove'
    );

    echo "INSTALLED SLIDE {$index}: "
        . $filename
        . PHP_EOL;
}


/*
|--------------------------------------------------------------------------
| Remove any stale demo configuration
|--------------------------------------------------------------------------
*/

set_config(
    'marketingheading',
    '',
    'theme_moove'
);

set_config(
    'marketingcontent',
    '',
    'theme_moove'
);

for ($i = 1; $i <= 4; $i++) {

    set_config(
        'marketing' . $i . 'heading',
        '',
        'theme_moove'
    );

    set_config(
        'marketing' . $i . 'content',
        '',
        'theme_moove'
    );
}


/*
|--------------------------------------------------------------------------
| Purge caches
|--------------------------------------------------------------------------
*/

purge_all_caches();


echo PHP_EOL;
echo "==========================================" . PHP_EOL;
echo " SUCCESS" . PHP_EOL;
echo "==========================================" . PHP_EOL;

echo "Slides: 4" . PHP_EOL;
echo "First slide: hero-04.png" . PHP_EOL;
echo "Rotation: Native Moove 5000ms" . PHP_EOL;
echo "Marketing boxes: OFF" . PHP_EOL;
echo "Numbers section: OFF" . PHP_EOL;
echo "FAQ section: OFF" . PHP_EOL;

echo PHP_EOL;
