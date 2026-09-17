<?php

defined('MOODLE_INTERNAL') || die();

function local_nexusbranding_before_standard_html_head() {
    global $CFG, $PAGE;

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

    $socialimagepath = __DIR__ . '/pix/nexus-lms-social-cover.png';
    $siteiconpath = __DIR__ . '/pix/site-icon-white-bg.png';
    $socialimage = new moodle_url(
        '/local/nexusbranding/pix/nexus-lms-social-cover.png',
        ['v' => filemtime($socialimagepath)]
    );
    $siteicon = new moodle_url(
        '/local/nexusbranding/pix/site-icon-white-bg.png',
        ['v' => filemtime($siteiconpath)]
    );

    $title = 'Nexus EPS Learning Portal';
    $description = 'Secure online learning portal for Nexus Education Private School students and teachers, with course materials, assignments, quizzes, grades, and academic support.';
    $imagealt = 'Nexus Education Private School Learning Portal';
    $tags = [
        html_writer::empty_tag('meta', ['name' => 'description', 'content' => $description]),
        html_writer::empty_tag('meta', ['name' => 'theme-color', 'content' => '#102f4c']),
        html_writer::empty_tag('meta', ['name' => 'apple-mobile-web-app-title', 'content' => 'Nexus EPS']),
        html_writer::empty_tag('link', ['rel' => 'icon', 'type' => 'image/png', 'sizes' => '512x512', 'href' => $siteicon->out(false)]),
        html_writer::empty_tag('link', ['rel' => 'apple-touch-icon', 'sizes' => '180x180', 'href' => $siteicon->out(false)]),
        html_writer::empty_tag('meta', ['property' => 'og:type', 'content' => 'website']),
        html_writer::empty_tag('meta', ['property' => 'og:locale', 'content' => 'en_CA']),
        html_writer::empty_tag('meta', ['property' => 'og:site_name', 'content' => 'Nexus Education Private School']),
        html_writer::empty_tag('meta', ['property' => 'og:title', 'content' => $title]),
        html_writer::empty_tag('meta', ['property' => 'og:description', 'content' => $description]),
        html_writer::empty_tag('meta', ['property' => 'og:url', 'content' => $CFG->wwwroot]),
        html_writer::empty_tag('meta', ['property' => 'og:image', 'content' => $socialimage->out(false)]),
        html_writer::empty_tag('meta', ['property' => 'og:image:secure_url', 'content' => $socialimage->out(false)]),
        html_writer::empty_tag('meta', ['property' => 'og:image:type', 'content' => 'image/png']),
        html_writer::empty_tag('meta', ['property' => 'og:image:width', 'content' => '1200']),
        html_writer::empty_tag('meta', ['property' => 'og:image:height', 'content' => '630']),
        html_writer::empty_tag('meta', ['property' => 'og:image:alt', 'content' => $imagealt]),
        html_writer::empty_tag('meta', ['name' => 'twitter:card', 'content' => 'summary_large_image']),
        html_writer::empty_tag('meta', ['name' => 'twitter:title', 'content' => $title]),
        html_writer::empty_tag('meta', ['name' => 'twitter:description', 'content' => $description]),
        html_writer::empty_tag('meta', ['name' => 'twitter:image', 'content' => $socialimage->out(false)]),
        html_writer::empty_tag('meta', ['name' => 'twitter:image:alt', 'content' => $imagealt]),
    ];

    return "\n" . implode("\n", $tags) . "\n";
}
