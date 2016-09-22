#!/usr/bin/env bash
wget https://github.com/rueduphp/skeleton/archive/master.zip
unzip master.zip -d working
cd working/skeleton-master
composer install
zip -ry ../../skeleton-craft.zip .
cd ../..
mv skeleton-craft.zip public/skeleton-craft.zip
rm -rf working
rm master.zip
