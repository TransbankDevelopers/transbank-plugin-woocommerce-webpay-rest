#!/usr/bin/env bash

set -Eeuo pipefail

on_error() {
	local exit_code=$?
	local line_no="${1:-unknown}"
	echo "ERROR: deployment failed at line ${line_no} with exit code ${exit_code}" 1>&2
	exit "${exit_code}"
	return "${exit_code}"
}

require_command() {
	local cmd="$1"
	if ! command -v "${cmd}" >/dev/null 2>&1; then
		echo "Missing required command: ${cmd}" 1>&2
		exit 1
	fi
	return 0
}

trap 'on_error $LINENO' ERR

if [[ -z "$GITHUB_ACTIONS" ]]; then
	echo "Script is only to be run by GitHub Actions" 1>&2
	exit 1
fi

if [[ -z "$WP_ORG_PASSWORD" ]]; then
	echo "WordPress.org password not set" 1>&2
	exit 1
fi

if [[ -z "$TAG" ]]; then
	echo "Build branch is required and must be a tag" 1>&2
	exit 1
fi

WP_ORG_USERNAME="transbankdevelopers"
PLUGIN="transbank-webpay-plus-rest"
PROJECT_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
PLUGIN_BUILDS_PATH="$PROJECT_ROOT/builds"
VERSION=$TAG
ZIP_FILE="$PROJECT_ROOT/$PLUGIN.zip"

require_command svn
require_command rsync
require_command unzip
require_command find
require_command xargs

# Ensure the zip file for the current version has been built
if [ ! -f "$ZIP_FILE" ]; then
    echo "Built zip file $ZIP_FILE does not exist" 1>&2
    exit 1
fi

mkdir -p $PLUGIN_BUILDS_PATH

# Check if the tag exists for the version we are building
if svn ls "https://plugins.svn.wordpress.org/$PLUGIN/tags/$VERSION" >/dev/null 2>&1; then
    # Tag exists, don't deploy
    echo "Tag already exists for version $VERSION, aborting deployment"
    exit 1
fi

cd "$PLUGIN_BUILDS_PATH"
# Remove any unzipped dir so we start from scratch
rm -fR "$PLUGIN"
# Unzip the built plugin
unzip -q -o "$ZIP_FILE" -d $PLUGIN

# Clean up any previous svn dir
rm -fR svn

# Checkout the SVN repo
svn co -q "http://svn.wp-plugins.org/$PLUGIN" svn

# Move out the trunk directory to a temp location
mv svn/trunk ./svn-trunk
# Create trunk directory
mkdir svn/trunk
# Copy our new version of the plugin into trunk
rsync -r -p $PLUGIN/* svn/trunk

# Copy all the .svn folders from the checked out copy of trunk to the new trunk.
# This is necessary as the Travis container runs Subversion 1.6 which has .svn dirs in every sub dir
cd svn/trunk/
TARGET=$(pwd)
cd ../../svn-trunk/

# Find all .svn dirs in sub dirs
SVN_DIRS=`find . -type d -iname .svn`

for SVN_DIR in $SVN_DIRS; do
    SOURCE_DIR=${SVN_DIR/.}
    TARGET_DIR=$TARGET${SOURCE_DIR/.svn}
    TARGET_SVN_DIR=$TARGET${SVN_DIR/.}
    if [ -d "$TARGET_DIR" ]; then
        # Copy the .svn directory to trunk dir
        cp -r $SVN_DIR $TARGET_SVN_DIR
    fi
done

# Back to builds dir
cd ../

# Remove checked out dir
rm -fR svn-trunk

# Add new version tag
mkdir svn/tags/$VERSION
rsync -r -p $PLUGIN/* svn/tags/$VERSION

# Add new files to SVN
while IFS= read -r path; do
    svn add "${path}@"
done < <(svn stat svn | awk '/^\?/ {print $2}')

# Remove deleted files from SVN
while IFS= read -r path; do
    svn rm --force "${path}@"
done < <(svn stat svn | awk '/^!/ {print $2}')
svn stat svn

# Commit to SVN
svn ci --no-auth-cache --username $WP_ORG_USERNAME --password $WP_ORG_PASSWORD svn -m "Deploy version $VERSION"

# Remove SVN temp dir
rm -fR svn
