#!/bin/sh

#Script for create the plugin artifact
echo "Travis tag: $TRAVIS_TAG"

if [ "$TRAVIS_TAG" = "" ]
then
   TRAVIS_TAG='1.0.0'
fi

SRC_DIR="plugin"
MAIN_FILE="webpay-rest.php"
README_FILE="readme.txt"


cd $SRC_DIR
composer install --no-dev
cd ..

sed -i.bkp "s/Version: VERSION_REPLACE_HERE/Version: ${TRAVIS_TAG#"v"}/g" "$SRC_DIR/$MAIN_FILE"
sed -i.bkp "s/VERSION_REPLACE_HERE/${TRAVIS_TAG#"v"}/g" "$SRC_DIR/$README_FILE"

PLUGIN_FILE="transbank-webpay-plus-rest.zip"

cd $SRC_DIR
zip -FSr ../$PLUGIN_FILE . -x composer.json composer.lock "$MAIN_FILE.bkp" "$README_FILE.bkp" vendor/tecnickcom/tcpdf/fonts/*
zip -ur ../$PLUGIN_FILE ./vendor/tecnickcom/tcpdf/fonts/helvetica.php
cd ..

cp "$SRC_DIR/$MAIN_FILE.bkp" "$SRC_DIR/$MAIN_FILE"
rm "$SRC_DIR/$MAIN_FILE.bkp"
cp "$SRC_DIR/$README_FILE.bkp" "$SRC_DIR/$README_FILE"
rm "$SRC_DIR/$README_FILE.bkp"

echo "Plugin version: $TRAVIS_TAG"
echo "Plugin file: $PLUGIN_FILE"
