#!/bin/sh

#Script for create the plugin artifact
echo "Tag: $TAG"

if [ "$TAG" = "" ]
then
   TAG='1.0.0'
fi

SRC_DIR="plugin"
MAIN_FILE="webpay-rest.php"
README_FILE="readme.txt"
COMPOSER_FILE="composer.json"
COMPOSER_LOCK_FILE="composer.lock"


cd $SRC_DIR
composer update
composer install --no-dev
npm install --no-audit --no-fund --no-optional
npm run build
if [ $? -eq 0 ]; then
    rm -rf node_modules/
else
    echo "Command npm run build finished with error" 1>&2
    rm -rf node_modules/
    exit 1
fi
cd ..

sed -i.bkp "s/Version: VERSION_REPLACE_HERE/Version: ${TAG#"v"}/g" "$SRC_DIR/$MAIN_FILE"
sed -i.bkp "s/VERSION_REPLACE_HERE/${TAG#"v"}/g" "$SRC_DIR/$README_FILE"
cp "$SRC_DIR/$COMPOSER_LOCK_FILE" "$SRC_DIR/$COMPOSER_LOCK_FILE.bkp"

PLUGIN_FILE="transbank-webpay-plus-rest.zip"
PLUGIN_FILE_GUZZLE="transbank-webpay-plus-rest-guzzle7.zip"

cd $SRC_DIR
zip -FSr ../$PLUGIN_FILE . -x composer.json composer.lock webpack.config.js package.json package-lock.json "*.bkp"

# Create Guzzle 7 version
sed -i.bkp "s/\"php\": \"7.0\"/\"php\": \"7.2.5\"/g" "$COMPOSER_FILE"
composer require guzzlehttp/guzzle:^7.0
zip -FSr ../$PLUGIN_FILE_GUZZLE . -x composer.json composer.lock webpack.config.js package.json package-lock.json "*.bkp"

cp "$COMPOSER_LOCK_FILE.bkp" "$COMPOSER_LOCK_FILE"
rm "$COMPOSER_LOCK_FILE.bkp"
cp "$COMPOSER_FILE.bkp" "$COMPOSER_FILE"
rm "$COMPOSER_FILE.bkp"
composer update
composer install

cd ..

cp "$SRC_DIR/$MAIN_FILE.bkp" "$SRC_DIR/$MAIN_FILE"
rm "$SRC_DIR/$MAIN_FILE.bkp"
cp "$SRC_DIR/$README_FILE.bkp" "$SRC_DIR/$README_FILE"
rm "$SRC_DIR/$README_FILE.bkp"

echo "Plugin version: $TAG"
echo "Plugin file: $PLUGIN_FILE"
echo "Plugin file Guzzle 7: $PLUGIN_FILE_GUZZLE"
