#!/bin/bash

# This script rebuilds customized layer of Dojo for tt-rss
# Place unpacked Dojo source release in this directory and run this script.
# It will automatically replace previous build of Dojo in ../dojo

# Dojo requires Java runtime to build. Further information on rebuilding Dojo
# is available here: http://dojotoolkit.org/reference-guide/build/index.html

VERSION=1.14.2

# Download and extract dojo src code if it doesn't already exist
if [ ! -d "dojo" ]; then
    TARBALL=dojo-release-$VERSION-src.tar.gz
    if [ ! -f $TARBALL ]; then
        wget -q http://download.dojotoolkit.org/release-$VERSION/$TARBALL
    fi
    tar -zxf $TARBALL
    mv dojo-release-$VERSION-src/* .
    rm -rf dojo-release-$VERSION-src
fi

if [ -d util/buildscripts/ ]; then
	rm -rf release/dojo

	pushd util/buildscripts
	    ./build.sh profile=../../tt-rss action=release optimize=shrinksafe cssOptimize=comments
	popd

	if [ -d release/dojo ]; then
		rm -rf ../dojo ../dijit
		cp -r release/dojo/dojo ..
		cp -r release/dojo/dijit ..

		cd ..

		find dojo -name '*uncompressed*' -exec rm -- {} \;
		find dijit -name '*uncompressed*' -exec rm -- {} \;
	else
		echo $0: ERROR: Dojo build seems to have failed.
	fi
else
	echo $0: ERROR: Please unpack Dojo source release into current directory.
fi
