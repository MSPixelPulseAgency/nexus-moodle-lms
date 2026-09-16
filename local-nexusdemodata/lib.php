<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Provide static course artwork when the learner requests reduced motion.
 */
function local_nexusdemodata_before_standard_html_head(): string {
    global $DB;

    $shortnames = ['MHF4U', 'SBI4U', 'SCH4U', 'ENG4U', 'ASM4M', 'SPH4U', 'TDJ4M', 'AVI1O'];
    $animatedrules = [];
    $reducedrules = [];
    $courses = $DB->get_records_list('course', 'shortname', $shortnames, '', 'id,shortname');
    foreach ($courses as $course) {
        $shortname = $course->shortname;
        $selector = '[data-course-id="' . (int)$course->id . '"] .dashboard-card-img,' .
            '[data-course-id="' . (int)$course->id . '"] .courseimage';
        $animatedurl = new moodle_url('/local/nexusdemodata/pix/banners/' . strtolower($shortname) . '.webp');
        $staticurl = new moodle_url('/local/nexusdemodata/pix/banners/' . strtolower($shortname) . '-static.webp');
        $animatedrules[] = $selector . '{background-image:url("' . $animatedurl->out(false) .
            '")!important;background-size:cover!important;background-position:center!important}';
        $reducedrules[] = $selector . '{background-image:url("' . $staticurl->out(false) .
            '")!important;background-size:cover!important;background-position:center!important;' .
            'animation:none!important;transition:none!important}';
    }
    if (!$animatedrules) {
        return '';
    }
    return html_writer::tag('style', implode('', $animatedrules) .
        '@media (prefers-reduced-motion:reduce){' . implode('', $reducedrules) . '}');
}
