<?php

declare(strict_types=1);

define('CLI_SCRIPT', true);

$moodleroot = getenv('NEXUS_MOODLE_ROOT') ?: '/var/www/moodle';
$brandroot = getenv('NEXUS_BRAND_ROOT') ?: $moodleroot . '/local/nexusbranding/pix';

require $moodleroot . '/config.php';

/**
 * Replace one Moodle branding file and its matching setting.
 *
 * @return array<string, mixed>
 */
function nexus_update_brand_file(
    string $component,
    string $filearea,
    string $configplugin,
    string $configname,
    string $source,
    string $filename
): array {
    if (!is_readable($source)) {
        throw new RuntimeException('Brand asset is not readable: ' . $source);
    }

    $context = context_system::instance();
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, $component, $filearea, 0);

    $storedfile = $fs->create_file_from_pathname([
        'contextid' => $context->id,
        'component' => $component,
        'filearea' => $filearea,
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $filename,
    ], $source);

    set_config($configname, '/' . $filename, $configplugin);

    return [
        'component' => $component,
        'filearea' => $filearea,
        'filename' => $filename,
        'sha256' => hash_file('sha256', $source),
        'contenthash' => $storedfile->get_contenthash(),
    ];
}

$logo = $brandroot . '/logo.png';
$icon = $brandroot . '/site-icon.png';

$updated = [
    nexus_update_brand_file(
        'core_admin',
        'logo',
        'core_admin',
        'logo',
        $logo,
        'nexus-education-private-school-logo.png'
    ),
    nexus_update_brand_file(
        'core_admin',
        'logocompact',
        'core_admin',
        'logocompact',
        $icon,
        'nexus-brand-icon.png'
    ),
    nexus_update_brand_file(
        'core_admin',
        'favicon',
        'core_admin',
        'favicon',
        $icon,
        'nexus-brand-icon.png'
    ),
    nexus_update_brand_file(
        'theme_moove',
        'logo',
        'theme_moove',
        'logo',
        $logo,
        'nexus-education-private-school-logo.png'
    ),
    nexus_update_brand_file(
        'theme_moove',
        'favicon',
        'theme_moove',
        'favicon',
        $icon,
        'nexus-brand-icon.png'
    ),
];

purge_all_caches();

echo json_encode([
    'status' => 'updated',
    'files' => $updated,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
