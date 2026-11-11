#!/usr/bin/env bash

PLUGIN_SLUG=minepic-avatars-for-minecraft

mkdir -p ./build/${PLUGIN_SLUG}
rm -rf ./build/${PLUGIN_SLUG}/*

cp -R assets/ ./build/${PLUGIN_SLUG}/assets
cp readme.txt ./build/${PLUGIN_SLUG}/readme.txt
cp minepic-avatars.php ./build/${PLUGIN_SLUG}/minepic-avatars.php
