#!/usr/bin/env bash
set -euo pipefail

if [[ "${1:-}" != "--confirm-production" ]]; then
    printf 'Usage: %s --confirm-production\n' "$0" >&2
    exit 2
fi

app_root="/home/1670697.cloudwaysapps.com/utrmhqdwuv"
moodle_root="${app_root}/public_html"
php_bin="${app_root}/.cw/bin/php"
source_dir="${NEXUS_DEMO_SOURCE:-/home/master/nexusdemodata-stage-20260916}"
plugin_dir="${moodle_root}/local/nexusdemodata"

if [[ ! -r "${source_dir}/version.php" || ! -r "${source_dir}/cli/provision.php" ]]; then
    printf 'Validated source package is missing from %s\n' "${source_dir}" >&2
    exit 1
fi

if [[ -e "${plugin_dir}/version.php" ]] && ! grep -q "local_nexusdemodata" "${plugin_dir}/version.php"; then
    printf 'Refusing to overwrite an unrelated local plugin at %s\n' "${plugin_dir}" >&2
    exit 1
fi

disable_maintenance() {
    "${php_bin}" "${moodle_root}/admin/cli/maintenance.php" --disable >/dev/null 2>&1 || true
}
trap disable_maintenance EXIT

"${php_bin}" "${moodle_root}/admin/cli/maintenance.php" --enable
mkdir -p "${plugin_dir}"
cp -a "${source_dir}/." "${plugin_dir}/"

"${php_bin}" -d max_input_vars=5000 "${moodle_root}/admin/cli/upgrade.php" --non-interactive
NEXUS_MOODLE_ROOT="${moodle_root}" "${php_bin}" "${plugin_dir}/cli/provision.php" --apply
NEXUS_MOODLE_ROOT="${moodle_root}" "${php_bin}" "${plugin_dir}/cli/verify.php"
"${php_bin}" "${moodle_root}/admin/cli/maintenance.php" --disable
trap - EXIT
"${php_bin}" "${moodle_root}/admin/cli/cron.php"

printf 'DEPLOYED=%s\n' "${plugin_dir}"
