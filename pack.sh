#!/usr/bin/bash
rm -r ./dist/*
cp -r ./packages/com_baforms ./packages/plg_content_formshortcode ./packages/plg_system_forms ./pkg_BaForms.xml ./script.php ./dist
cd ./dist
cd ./com_baforms
zip -r ../com_baforms.zip ./*
cd ../plg_content_formshortcode
zip -r ../plg_content_formshortcode.zip ./*
cd ../plg_system_forms
zip -r ../plg_system_forms.zip ./*
cd ..
rm -r ./com_baforms ./plg_content_formshortcode ./plg_system_forms
zip -r pkg_BaForms.zip ./*
rm com*.zip plg*.zip *.php *.xml
cd ..