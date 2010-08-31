#!/bin/sh
BASE=`pwd`
php ../tools/cron.php --server 'Seagull LDAP Server' --process  --file=$BASE/data/udi_import_empty.csv
