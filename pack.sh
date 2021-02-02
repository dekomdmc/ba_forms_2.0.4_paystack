#!/usr/bin/bash
rm -r ./dist/*
cp -r ./packages/com_baforms ./packages/plg_content_formshortcode ./packages/plg_system_forms ./pkg_BaForms.xml ./script.php ./dist
cd ./dist
mkdir packages
cd ./com_baforms
zip -r ../packages/com_baforms.zip ./*
cd ../plg_content_formshortcode
zip -r ../packages/plg_content_formshortcode.zip ./*
cd ../plg_system_forms
zip -r ../packages/plg_system_forms.zip ./*
cd ..
rm -r ./com_baforms ./plg_content_formshortcode ./plg_system_forms
zip -r pkg_BaForms.zip ./*
rm -r ./packages *.php *.xml
cd ..

git add .
git commit -m "`date`"
git push origin master