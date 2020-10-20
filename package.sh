#!/bin/sh

#Script for create the plugin artifact
echo "Travis tag: $TRAVIS_TAG"

if [ "$TRAVIS_TAG" = "" ]
then
   TRAVIS_TAG='1.0.0'
fi

SRC_DIR="plugin"
FILE1="webpay.php"

cd $SRC_DIR
composer install --no-dev
cd ..

sed -i.bkp "s/Version: VERSION_REPLACE_HERE/Version: ${TRAVIS_TAG#"v"}/g" "$SRC_DIR/$FILE1"

PLUGIN_FILE="transbank-webpay-plus-rest.zip"

cd $SRC_DIR
zip -FSr ../$PLUGIN_FILE . -x composer.json composer.lock "$FILE1.bkp" vendor/tecnickcom/tcpdf/fonts/*
zip -ur ../$PLUGIN_FILE ./vendor/tecnickcom/tcpdf/fonts/helvetica.php
cd ..

cp "$SRC_DIR/$FILE1.bkp" "$SRC_DIR/$FILE1"
rm "$SRC_DIR/$FILE1.bkp"

echo "Plugin version: $TRAVIS_TAG"
echo "Plugin file: $PLUGIN_FILE"
