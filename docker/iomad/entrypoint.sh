#!/usr/bin/env bash
set -euo pipefail

iomad_dir="${IOMAD_DIR:-/var/www/iomad}"
dataroot="${IOMAD_DATAROOT:-/var/www/iomaddata}"

if [ ! -f "${iomad_dir}/public/version.php" ]; then
    echo "IOMAD source is missing from ${iomad_dir}. Build with INCLUDE_IOMAD_SOURCE=true." >&2
    exit 1
fi

mkdir -p "${dataroot}"
if [ ! -w "${dataroot}" ]; then
    echo "IOMAD dataroot is not writable: ${dataroot}" >&2
    exit 1
fi

case "${1:-iomad-web}" in
    iomad-web)
        php-fpm -D
        exec nginx -g 'daemon off;'
        ;;
    iomad-php-fpm)
        exec php-fpm -F
        ;;
    iomad-nginx)
        exec nginx -g 'daemon off;'
        ;;
    iomad-cron-loop)
        interval="${IOMAD_CRON_INTERVAL:-60}"
        if ! [[ "${interval}" =~ ^[1-9][0-9]*$ ]]; then
            echo "IOMAD_CRON_INTERVAL must be a positive integer." >&2
            exit 1
        fi
        while true; do
            php "${iomad_dir}/admin/cli/cron.php" --keep-alive=0
            date +%s > /tmp/iomad-cron-heartbeat
            sleep "${interval}"
        done
        ;;
    *)
        exec "$@"
        ;;
esac
