#!/bin/sh

DESTINATION="include/sanity_config.php"

echo "<?php # This file has been generated at: " `date` > $DESTINATION

echo -n "define('GENERATED_CONFIG_CHECK', " >> $DESTINATION
grep CONFIG_VERSION config.php-dist | awk -F ' |)' '{ print $2 }' | xargs echo -n >> $DESTINATION
echo ");" >> $DESTINATION

echo -n "\$required_defines = array( " >> $DESTINATION

grep define\( config.php-dist | awk -F\' '{ print "*" $2 "*," }' | grep -v DB_PORT | xargs echo -n | sed -e s/,$// -e s/*/\'/g >> $DESTINATION

echo "); ?>" >> $DESTINATION


