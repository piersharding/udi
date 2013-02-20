#!/bin/sh

DTE=`date +"%Y.%m.%d.%H%M%S"`

sudo /etc/init.d/slapd stop
sleep 2
killall -9 slapd
sudo apt-get -y remove slapd
sudo rm -rf /var/lib/ldap
sudo mkdir /var/lib/ldap
sudo chown openldap:openldap /var/lib/ldap
sudo chown openldap:openldap /etc/ldap/slapd.d
sudo apt-get --force-yes -y install slapd
#sudo dpkg-reconfigure --force -f noninteractive slapd
sudo /etc/init.d/slapd start

echo "installed slapd ..."

sudo /etc/init.d/slapd stop
sudo slapadd -l ldap/ldap-dump-reload.ldif
sudo chown -R openldap:openldap /var/lib/ldap

echo "loaded slapd ..."

sudo /etc/init.d/slapd start

ldapsearch -x -h localhost -D 'cn=admin,dc=example,dc=com' -w letmein  -b "dc=example,dc=com" "(objectclass=*)" "dn" | tail -30

