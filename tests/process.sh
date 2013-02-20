#!/bin/sh
BASE=`pwd`
#php ../tools/cron.php --server 'UDI LDAP Server' --user='cn=admin,dc=example,dc=com' --passwd='letmein' --process --file=/home/piers/Desktop/sms-ide-1.7.csv
php ../tools/cron.php --server 'UDI LDAP Server' --user='cn=admin,dc=example,dc=com' --passwd='letmein' --process --file=/home/piers/Downloads/SMSIDE-kamar-1.7.csv
