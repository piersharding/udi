<?php
/* A convenient name that will appear in the tree viewer and throughout
   phpLDAPadmin to identify this LDAP server to users. */
$servers->setValue('server','name','UDI LDAP Server');

/* Examples:
   'ldap.example.com',
   'ldaps://ldap.example.com/',
   'ldapi://%2fusr%local%2fvar%2frun%2fldapi'
           (Unix socket at /usr/local/var/run/ldap) */
 $servers->setValue('server','host','127.0.0.1');

/* The port your LDAP server listens on (no quotes). 389 is standard. */
 $servers->setValue('server','port',389);

/* Array of base DNs of your LDAP server. Leave this blank to have phpLDAPadmin
   auto-detect it for you. */
// $servers->setValue('server','base',array('ou=FakeRoot,dc=example,dc=com'));
 $servers->setValue('server','base',array('dc=example,dc=com'));
 //$servers->setValue('server','base',array('dc=sillyfoo,dc=org'));

/* Four options for auth_type:
   1. 'cookie': you will login via a web form, and a client-side cookie will
      store your login dn and password.
   2. 'session': same as cookie but your login dn and password are stored on the
      web server in a persistent session variable.
   3. 'http': same as session but your login dn and password are retrieved via
      HTTP authentication.
   4. 'config': specify your login dn and password here in this config file. No
      login will be required to use phpLDAPadmin for this server.

   Choose wisely to protect your authentication information appropriately for
   your situation. If you choose 'cookie', your cookie contents will be
   encrypted using blowfish and the secret your specify above as
   session['blowfish']. */
 $servers->setValue('login','auth_type','session');

/* The DN of the user for phpLDAPadmin to bind with. For anonymous binds or
   'cookie' or 'session' auth_types, LEAVE THE LOGIN_DN AND LOGIN_PASS BLANK. If
   you specify a login_attr in conjunction with a cookie or session auth_type,
   then you can also specify the bind_id/bind_pass here for searching the
   directory for users (ie, if your LDAP server does not allow anonymous binds. */
// $servers->setValue('login','bind_id','');
  $servers->setValue('login','bind_id','cn=admin,dc=example,dc=com');

/* Your LDAP password. If you specified an empty bind_id above, this MUST also
   be blank. */
 $servers->setValue('login','bind_pass','');

// Kiosk admin user for password change 
 $servers->setValue('login','kiosk_bind_id','cn=admin,dc=example,dc=com');
 $servers->setValue('login','kiosk_bind_pass','letmein');
//  $servers->setValue('server','tls',true);


