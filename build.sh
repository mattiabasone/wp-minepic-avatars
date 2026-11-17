#!/usr/bin/env bash

PLUGIN_SLUG=minehub-avatars-for-minecraft

mkdir -p ./build/${PLUGIN_SLUG}
rm -rf ./build/${PLUGIN_SLUG}/*

cp -R assets/ ./build/${PLUGIN_SLUG}/assets
cp readme.txt ./build/${PLUGIN_SLUG}/readme.txt
cp minehub-avatars.php ./build/${PLUGIN_SLUG}/minehub-avatars.php
