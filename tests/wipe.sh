#!/bin/sh
BASE=`pwd`
php ../tools/cron.php --server 'UDI LDAP Server' --process  --file=$BASE/data/udi_import_empty.csv --user='cn=admin,dc=example,dc=com' --passwd='letmein'
php ../tools/cron.php --server 'UDI LDAP Server' --delete --yes --user='cn=admin,dc=example,dc=com' --passwd='letmein'
