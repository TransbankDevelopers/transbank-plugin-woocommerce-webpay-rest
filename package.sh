#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
SRC_DIR="plugin"
WORK_DIR="$PROJECT_ROOT/build/package-plugin"
PLUGIN_FILE="transbank-webpay-plus-rest.zip"
MAIN_FILE="webpay-rest.php"
README_FILE="readme.txt"
ENABLE_SCOPER="${ENABLE_SCOPER:-1}"
SCOPER_BIN="$PROJECT_ROOT/vendor/bin/php-scoper"
ROOT_COMPOSER_JSON="$PROJECT_ROOT/composer.json"
ROOT_COMPOSER_LOCK="$PROJECT_ROOT/composer.lock"
ROOT_VENDOR_DIR="$PROJECT_ROOT/vendor"
TOOLING_INSTALLED_BY_SCRIPT=0
ROOT_VENDOR_WAS_PRESENT=0
ROOT_LOCK_WAS_PRESENT=0

on_error() {
    local exit_code=$?
    local line_no="${1:-unknown}"
    echo "ERROR: packaging failed at line ${line_no} with exit code ${exit_code}" 1>&2
    exit "${exit_code}"
}

cleanup() {
    rm -rf "$PROJECT_ROOT/build"

    if [[ "$TOOLING_INSTALLED_BY_SCRIPT" == "1" ]]; then
        echo "Cleaning temporary tooling artifacts."
        if [[ "$ROOT_VENDOR_WAS_PRESENT" == "0" ]]; then
            rm -rf "$ROOT_VENDOR_DIR"
        fi
        if [[ "$ROOT_LOCK_WAS_PRESENT" == "0" ]]; then
            rm -f "$ROOT_COMPOSER_LOCK"
        fi
    fi
}

require_command() {
    local cmd="$1"
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "Missing required command: $cmd" 1>&2
        exit 1
    fi
}

run_step() {
    local name="$1"
    shift
    echo "Running: ${name}"
    "$@"
}

track_tooling_state() {
    if [[ -d "$ROOT_VENDOR_DIR" ]]; then
        ROOT_VENDOR_WAS_PRESENT=1
    fi
    if [[ -f "$ROOT_COMPOSER_LOCK" ]]; then
        ROOT_LOCK_WAS_PRESENT=1
    fi
}

check_tag() {
    if [[ "${GITHUB_EVENT_NAME:-}" == "release" ]]; then
        if [[ -z "${TAG:-}" ]]; then
            echo "TAG is required for release pipeline" 1>&2
            exit 1
        fi
        echo "Release pipeline detected. Tag: $TAG"
        return 0
    fi

    if [[ -n "${TAG:-}" ]]; then
        echo "Build pipeline with TAG override: $TAG"
    else
        echo "Build pipeline detected. Packaging without version replacement."
    fi
    return 0
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
    require_command php

    if [[ "$ENABLE_SCOPER" == "1" && ! -x "$SCOPER_BIN" ]]; then
        if [[ ! -f "$ROOT_COMPOSER_JSON" ]]; then
            echo "Missing required tool: $SCOPER_BIN" 1>&2
            echo "Missing tooling manifest: $ROOT_COMPOSER_JSON" 1>&2
            exit 1
        fi

        track_tooling_state
        run_step "Install build tooling (php-scoper)" composer --working-dir="$PROJECT_ROOT" install --no-interaction --no-progress
        TOOLING_INSTALLED_BY_SCRIPT=1

        if [[ ! -x "$SCOPER_BIN" ]]; then
            echo "Missing required tool after install: $SCOPER_BIN" 1>&2
            exit 1
        fi
    fi

    if [[ ! -f "$PROJECT_ROOT/scripts/apply_scope_replacements.php" ]]; then
        echo "Missing required script: scripts/apply_scope_replacements.php" 1>&2
        exit 1
    fi

    if [[ ! -f "$PROJECT_ROOT/scripts/fix_scoper_autoload.php" ]]; then
        echo "Missing required script: scripts/fix_scoper_autoload.php" 1>&2
        exit 1
    fi

    if [[ ! -f "$PROJECT_ROOT/$SRC_DIR/scoper-namespaces.php" ]]; then
        echo "Missing required scope config: $SRC_DIR/scoper-namespaces.php" 1>&2
        exit 1
    fi
}

prepare_work_dir() {
    rm -rf "$WORK_DIR"
    mkdir -p "$WORK_DIR"

    if command -v rsync >/dev/null 2>&1; then
        rsync -a --delete "$PROJECT_ROOT/$SRC_DIR/" "$WORK_DIR/"
    else
        cp -a "$PROJECT_ROOT/$SRC_DIR/." "$WORK_DIR/"
    fi
}

set_plugin_tag() {
    echo "Setting tag ${TAG#"v"} in readme and main file."
    sed -i.bkp "s/Version: VERSION_REPLACE_HERE/Version: ${TAG#"v"}/g" "$MAIN_FILE"
    sed -i.bkp "s/VERSION_REPLACE_HERE/${TAG#"v"}/g" "$README_FILE"
    rm -f "$MAIN_FILE.bkp" "$README_FILE.bkp"
}

scope_vendor_dependencies() {
    if [[ "$ENABLE_SCOPER" != "1" ]]; then
        echo "php-scoper disabled (ENABLE_SCOPER=$ENABLE_SCOPER)."
        return 0
    fi

    run_step "Scope composer dependencies" "$SCOPER_BIN" add-prefix --force --quiet --config=scoper.inc.php

    run_step "Fix scoped composer autoload maps" php "$PROJECT_ROOT/scripts/fix_scoper_autoload.php" "$WORK_DIR"
    run_step "Apply namespace replacement dictionary" php "$PROJECT_ROOT/scripts/apply_scope_replacements.php" "$WORK_DIR"

    run_step "Remove unscoped vendor directory" rm -rf vendor
}

validate_packaging_layout() {
    if [[ "$ENABLE_SCOPER" != "1" ]]; then
        return 0
    fi

    if [[ ! -f "vendor-prefixed/autoload.php" ]]; then
        echo "ERROR: missing vendor-prefixed/autoload.php after php-scoper run" 1>&2
        exit 1
    fi

    if [[ -d "vendor" ]]; then
        echo "ERROR: vendor directory must not exist when php-scoper is enabled" 1>&2
        exit 1
    fi

    if find src shared views -type f -name '*.php' -print0 2>/dev/null | xargs -0 grep -q "TransbankVendor\\\\Transbank\\\\WooCommerce\\\\WebpayRest\\\\\|TransbankVendor\\\\Transbank\\\\Plugin\\\\"; then
        echo "ERROR: plugin namespaces were prefixed unexpectedly" 1>&2
        exit 1
    fi
}

create_zip() {
    echo "Creating zip file."
    rm -f "$PROJECT_ROOT/$PLUGIN_FILE"
    (
        cd "$WORK_DIR"
        zip -r "$PROJECT_ROOT/$PLUGIN_FILE" . -x "webpack.config.js" "*.lock" "*.json" "*.bkp"
    )
}

validate_php_syntax() {
    run_step "Validate bootstrap syntax" php -l webpay-rest.php
}

package_plugin() {
    check_tag
    check_requirements

    run_step "Prepare build directory" prepare_work_dir
    cd "$WORK_DIR"

    run_step "Composer install" composer install --no-dev --prefer-dist
    run_step "NPM install" npm install --no-audit --no-fund --no-optional
    run_step "NPM build" npm run build

    rm -rf node_modules

    if [[ -n "${TAG:-}" ]]; then
        validate_tag
        set_plugin_tag
    fi

    scope_vendor_dependencies
    validate_packaging_layout
    validate_php_syntax
    create_zip

    cd "$PROJECT_ROOT"

    echo "\nPlugin created, the detail is:"
    if [[ -n "${TAG:-}" ]]; then
        echo "- Version: $TAG"
    else
        echo "- Version: unchanged (non-release build)"
    fi
    echo "- File name: $PLUGIN_FILE"
}

trap 'on_error $LINENO' ERR
trap cleanup EXIT
package_plugin
