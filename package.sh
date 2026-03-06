#!/usr/bin/env bash

set -Eeuo pipefail

SRC_DIR="plugin"
MAIN_FILE="webpay-rest.php"
README_FILE="readme.txt"
COMPOSER_FILE="composer.json"
COMPOSER_LOCK_FILE="composer.lock"
PLUGIN_FILE="transbank-webpay-plus-rest.zip"
BACKUPS_CREATED=0

on_error() {
    local exit_code=$?
    local line_no="${1:-unknown}"
    echo "ERROR: packaging failed at line ${line_no} with exit code ${exit_code}" 1>&2
    exit "${exit_code}"
}

restore_files_on_exit() {
    if [[ "${BACKUPS_CREATED}" -eq 1 ]]; then
        restore_files
    fi
}

require_command() {
    local cmd="$1"
    if ! command -v "${cmd}" >/dev/null 2>&1; then
        echo "Missing required command: ${cmd}" 1>&2
        exit 1
    fi
}

run_step() {
    local name="$1"
    shift
    echo "Running: ${name}"
    "$@"
}

trap 'on_error $LINENO' ERR
trap restore_files_on_exit EXIT

package_plugin() {
    echo "Packaging plugin."
    check_tag
    validate_tag
    check_requirements

    cd "$SRC_DIR"

    run_step "Composer install" composer install --no-dev

    run_step "NPM install" npm install --no-audit --no-fund --no-optional
    run_step "NPM build" npm run build

    rm -rf node_modules/

    set_plugin_tag

    create_zip

    cd ..

    restore_files
    BACKUPS_CREATED=0

    echo "\\nPlugin created, the detail is:"
    echo "- Version: $TAG"
    echo "- File name: $PLUGIN_FILE"
}

check_tag() {
    if [ "$TAG" = "" ]
    then
        echo "No Tag found. Using default Tag 1.0.0"
        TAG='1.0.0'
    fi

    echo "Tag: $TAG"
}

validate_tag() {
    if [[ ! "$TAG" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z]+)*$ ]]; then
        echo "Invalid TAG format: $TAG" 1>&2
        exit 1
    fi
}

check_requirements() {
    require_command composer
    require_command npm
    require_command zip
    require_command sed
}

set_plugin_tag() {
    echo "Setting tag ${TAG#"v"} in readme and main file."

    sed -i.bkp "s/Version: VERSION_REPLACE_HERE/Version: ${TAG#"v"}/g" $MAIN_FILE
    sed -i.bkp "s/VERSION_REPLACE_HERE/${TAG#"v"}/g" $README_FILE
    BACKUPS_CREATED=1
}

create_zip() {
    echo "Creating zip file."

    EXCLUSIONS="webpack.config.js *.lock *.json *.bkp"
    zip -FSr "../$PLUGIN_FILE" . -x $EXCLUSIONS
}

restore_files() {
    echo "Restoring readme and main file."

    cp "$SRC_DIR/$MAIN_FILE.bkp" "$SRC_DIR/$MAIN_FILE"
    rm "$SRC_DIR/$MAIN_FILE.bkp"
    cp "$SRC_DIR/$README_FILE.bkp" "$SRC_DIR/$README_FILE"
    rm "$SRC_DIR/$README_FILE.bkp"
}

package_plugin
