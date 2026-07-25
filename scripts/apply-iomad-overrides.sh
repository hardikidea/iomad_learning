#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'USAGE'
Usage:
  apply-iomad-overrides.sh [--exclude-upstream] [--skip-tracked-overrides] OVERRIDES_DIR IOMAD_DIR

Copies tracked project overrides into a clean IOMAD checkout. With
--exclude-upstream, also removes the reviewed paths listed in
iomad-overrides/.iomad-exclusions. --skip-tracked-overrides keeps a host
inspection checkout clean while still syncing additive overrides.
USAGE
}

apply_exclusions=false
skip_tracked_overrides=false
while [[ "${1:-}" = --* ]]; do
    case "$1" in
        --exclude-upstream)
            apply_exclusions=true
            ;;
        --skip-tracked-overrides)
            skip_tracked_overrides=true
            ;;
        *)
            usage >&2
            exit 1
            ;;
    esac
    shift
done

if [ "$#" -ne 2 ]; then
    usage >&2
    exit 1
fi

overrides_dir="$1"
iomad_dir="$2"
exclusions_file="${overrides_dir}/.iomad-exclusions"
tracked_overrides_file="${overrides_dir}/.iomad-tracked-overrides"
stale_overrides_file="${overrides_dir}/.iomad-stale-overrides"

if [ ! -d "${overrides_dir}" ] || [ ! -d "${iomad_dir}" ]; then
    echo "Override source and IOMAD target directories must exist." >&2
    exit 1
fi

file_sha256() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | awk '{print $1}'
    else
        shasum -a 256 "$1" | awk '{print $1}'
    fi
}

while IFS= read -r override_file; do
    relpath="${override_file#"${overrides_dir}"/}"
    if git -C "${iomad_dir}" ls-files --error-unmatch "${relpath}" >/dev/null 2>&1; then
        expected_sha="$(
            awk -v path="${relpath}" '$1 !~ /^#/ && $2 == path { print $1 }' \
                "${tracked_overrides_file}" 2>/dev/null
        )"
        if [ -z "${expected_sha}" ]; then
            echo "Refusing unreviewed tracked IOMAD override: ${relpath}" >&2
            exit 1
        fi

        actual_sha="$(file_sha256 "${iomad_dir}/${relpath}")"
        if [ "${actual_sha}" != "${expected_sha}" ]; then
            echo "Tracked IOMAD override requires re-review: ${relpath}" >&2
            echo "Expected upstream SHA-256 ${expected_sha}, got ${actual_sha}." >&2
            exit 1
        fi
    fi
done < <(
    find "${overrides_dir}" -type f \
        ! -path "${overrides_dir}/.iomad-exclusions" \
        ! -path "${overrides_dir}/.iomad-stale-overrides" \
        ! -path "${overrides_dir}/.iomad-tracked-overrides" \
        ! -path "${overrides_dir}/demo-output/*" |
        sort
)

tar_excludes=(
    "--exclude=./.iomad-exclusions"
    "--exclude=./.iomad-stale-overrides"
    "--exclude=./.iomad-tracked-overrides"
    "--exclude=./demo-output"
)
if [ "${skip_tracked_overrides}" = "true" ]; then
    while read -r _ relpath; do
        if [ -n "${relpath:-}" ]; then
            tar_excludes+=("--exclude=./${relpath}")
        fi
    done < <(awk '$1 !~ /^#/ && NF == 2' "${tracked_overrides_file}")
fi

# Additive plugin directories are project-owned. Recreate them before copying
# so a renamed or removed override file cannot survive into a release image.
while IFS= read -r version_file; do
    relpath="${version_file#"${overrides_dir}"/}"
    case "${relpath}" in
        public/admin/tool/*/version.php | \
        public/blocks/*/version.php | \
        public/course/format/*/version.php | \
        public/local/*/version.php | \
        public/mod/*/version.php | \
        public/theme/*/version.php)
            component_root="${relpath%/version.php}"
            ;;
        *)
            continue
            ;;
    esac

    if ! git -C "${iomad_dir}" ls-files "${component_root}" | grep -q .; then
        rm -rf -- "${iomad_dir:?}/${component_root}"
    fi
done < <(find "${overrides_dir}/public" -type f -name version.php | sort)

tar "${tar_excludes[@]}" -C "${overrides_dir}" -cf - . |
    tar -C "${iomad_dir}" -xf -

if [ -f "${stale_overrides_file}" ]; then
    while IFS= read -r relpath || [ -n "${relpath}" ]; do
        relpath="${relpath%%#*}"
        relpath="${relpath%"${relpath##*[![:space:]]}"}"
        relpath="${relpath#"${relpath%%[![:space:]]*}"}"
        if [ -z "${relpath}" ]; then
            continue
        fi
        if [[ "${relpath}" = /* || "${relpath}" = *//* || "${relpath}" =~ (^|/)\.\.?(/|$) ]]; then
            echo "Unsafe stale override path: ${relpath}" >&2
            exit 1
        fi
        if git -C "${iomad_dir}" ls-files --error-unmatch "${relpath}" >/dev/null 2>&1; then
            echo "Refusing to remove upstream-tracked stale override path: ${relpath}" >&2
            exit 1
        fi
        target="${iomad_dir}/${relpath}"
        if [ -e "${target}" ] || [ -L "${target}" ]; then
            rm -rf -- "${target}"
            echo "Removed retired project override: ${relpath}"
        fi
    done < "${stale_overrides_file}"
fi

if [ "${apply_exclusions}" != "true" ]; then
    exit 0
fi

if [ ! -f "${exclusions_file}" ]; then
    echo "IOMAD exclusion manifest is missing: ${exclusions_file}" >&2
    exit 1
fi

while IFS= read -r relpath || [ -n "${relpath}" ]; do
    relpath="${relpath%%#*}"
    relpath="${relpath%"${relpath##*[![:space:]]}"}"
    relpath="${relpath#"${relpath%%[![:space:]]*}"}"

    if [ -z "${relpath}" ]; then
        continue
    fi

    if [[ "${relpath}" = /* || "${relpath}" = *//* || "${relpath}" =~ (^|/)\.\.?(/|$) ]]; then
        echo "Unsafe IOMAD exclusion path: ${relpath}" >&2
        exit 1
    fi

    target="${iomad_dir}/${relpath}"
    if [ ! -e "${target}" ] && [ ! -L "${target}" ]; then
        echo "Reviewed IOMAD exclusion no longer exists upstream: ${relpath}" >&2
        echo "Review the new IOMAD release and update .iomad-exclusions explicitly." >&2
        exit 1
    fi

    rm -rf -- "${target}"
    echo "Excluded reviewed upstream component: ${relpath}"
done < "${exclusions_file}"
