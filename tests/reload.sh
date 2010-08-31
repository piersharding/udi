#!/bin/sh

sudo /etc/init.d/slapd stop
sudo apt-get -y remove slapd
sudo rm -rf /var/lib/ldap
sudo mkdir /var/lib/ldap
sudo chown openldap:openldap /var/lib/ldap
sudo rm -rf /etc/ldap/slapd.d
#sudo mkdir /etc/ldap/slapd.d
#sudo chown openldap:openldap /etc/ldap/slapd.d
sudo apt-get -y install slapd

echo "installed slapd ..."

sudo ldapadd -c -Y EXTERNAL -H ldapi:/// -f ldap/db.ldif
echo "installed db ..."
sudo ldapadd -c -Y EXTERNAL -H ldapi:/// -f ldap/base.ldif
echo "installed base ..."
sudo ldapadd -c -Y EXTERNAL -H ldapi:/// -f ldap/config.ldif
echo "installed config ..."
sudo ldapadd -c -Y EXTERNAL -H ldapi:/// -f ldap/acl.ldif
echo "installed acl ..."
ldapadd -c -D cn=admin,cn=config -w letmein -f ldap/misc.ldif
ldapadd -c -D cn=admin,cn=config -w letmein -f ldap/cosine.ldif
ldapadd -c -D cn=admin,cn=config -w letmein -f ldap/nis.ldif
ldapadd -c -D cn=admin,cn=config -w letmein -f ldap/inetorgperson.ldif
ldapadd -c -D cn=admin,cn=config -w letmein -f ldap/openldap.ldif
ldapadd -c -D cn=admin,cn=config -w letmein -f ldap/eduperson.ldif
ldapadd -c -D cn=admin,cn=config -w letmein -f ldap/mlep.ldif
echo "installed schemas ..."
ldapadd -x -c -D cn=admin,dc=example,dc=com -w letmein -f ldap/example.com.ldif
ldapadd -x -c -D cn=admin,dc=example,dc=com -w letmein -f ldap/udiconfig.ldif
