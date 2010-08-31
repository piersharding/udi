#!/bin/sh
ldapadd -x -c -D cn=admin,dc=example,dc=com -w letmein -f ldap/udiconfig_posix.ldif
