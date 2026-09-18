<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/theme/boost/lib.php');

$THEME->name = 'nexus';
$THEME->parents = ['boost'];

$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->editor_scss = ['editor'];

$THEME->usefallback = true;
$THEME->enable_dock = false;

$THEME->scss = function($theme) {
    return theme_nexus_get_main_scss_content($theme);
};

$THEME->extrascsscallback = 'theme_boost_get_extra_scss';
$THEME->prescsscallback = 'theme_boost_get_pre_scss';
$THEME->precompiledcsscallback = 'theme_boost_get_precompiled_css';

$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;

$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;

$THEME->activityheaderconfig = [
    'notitle' => true,
];

$boostconfig = $CFG->dirroot . '/theme/boost/config.php';

if (is_readable($boostconfig)) {
    $boosttheme = new stdClass();
    $originaltheme = $THEME;

    $THEME = $boosttheme;
    require($boostconfig);

    $originaltheme->layouts = $boosttheme->layouts;
    $THEME = $originaltheme;
}
