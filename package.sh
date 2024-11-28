#!/bin/sh

SRC_DIR="plugin"
MAIN_FILE="webpay-rest.php"
README_FILE="readme.txt"
COMPOSER_FILE="composer.json"
COMPOSER_LOCK_FILE="composer.lock"

package_plugin() {
    echo "Packaging plugin."
    check_tag

    cd $SRC_DIR

    composer install --no-dev > /dev/null 2>&1

    npm install --no-audit --no-fund --no-optional > /dev/null 2>&1
    npm run build > /dev/null 2>&1

    if [ $? -ne 0 ]; then
        echo "Command npm run build finished with error" 1>&2
        exit 1
    fi

    rm -rf node_modules/

    set_plugin_tag

    create_zip

    cd ..

    restore_files

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

set_plugin_tag() {
    echo "Setting tag ${TAG#"v"} in readme and main file."

    sed -i.bkp "s/Version: VERSION_REPLACE_HERE/Version: ${TAG#"v"}/g" $MAIN_FILE
    sed -i.bkp "s/VERSION_REPLACE_HERE/${TAG#"v"}/g" $README_FILE
}

create_zip() {
    echo "Creating zip file."

    EXCLUSIONS="webpack.config.js *.lock *.json *.bkp"
    PLUGIN_FILE="transbank-webpay-plus-rest.zip"

    zip -FSr ../$PLUGIN_FILE . -x $EXCLUSIONS > /dev/null
}

restore_files() {
    echo "Restoring readme and main file."

    cp "$SRC_DIR/$MAIN_FILE.bkp" "$SRC_DIR/$MAIN_FILE"
    rm "$SRC_DIR/$MAIN_FILE.bkp"
    cp "$SRC_DIR/$README_FILE.bkp" "$SRC_DIR/$README_FILE"
    rm "$SRC_DIR/$README_FILE.bkp"
}

package_plugin

