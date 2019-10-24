#!/bin/sh

#Script for create the plugin artifact
echo "Travis tag: $TRAVIS_TAG"

if [ "$TRAVIS_TAG" = "" ]
then
   TRAVIS_TAG='1.0.0'
fi

SRC_DIR="woocommerce-transbank"
FILE1="webpay.php"

cd $SRC_DIR
composer install --no-dev
composer update --no-dev
cd ..

sed -i.bkp "s/Version: 1.0.0/Version: ${TRAVIS_TAG#"v"}/g" "$SRC_DIR/$FILE1"

PLUGIN_FILE="woocommerce-transbank.zip"

cd $SRC_DIR
zip -FSr ../$PLUGIN_FILE . -x composer.json composer.lock "$FILE1.bkp"
cd ..

cp "$SRC_DIR/$FILE1.bkp" "$SRC_DIR/$FILE1"
rm "$SRC_DIR/$FILE1.bkp"

echo "Plugin version: $TRAVIS_TAG"
echo "Plugin file: $PLUGIN_FILE"
