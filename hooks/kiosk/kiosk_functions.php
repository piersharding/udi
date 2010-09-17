<?php
/**
 * Classes and functions for importing data to LDAP
 *
 * These classes provide differnet import formats.
 *
 * @author Piers Harding
 * @package phpLDAPadmin
 */

/**
 * clean input values
 * 
 * @param String $name CGI parameter name
 * @return String request parameter value
 */
function kiosk_clean_value($value, $user=false) {
    if ($user) {
        return preg_replace('/[^-\.@_a-z0-9]/', '', $value);
    }
    else {
        $value = preg_replace('~[[:cntrl:]]|[&<>"`\|\':\\\\/]~u', '', $value);
        return trim($value);
    }
}

/**
 * Handle a change password request
 * 
 * @param String $username the user name
 * @param String $oldpassword old password
 * @param String $newpassword new password
 * @param String $confirm confirmation of the new password
 * 
 * @return bool true on success
 */
function kiosk_change_passwd($username, $oldpassword, $newpassword, $confirm) {
    global $request, $udiconfig, $app;
    $cfg = $udiconfig->getConfig();
    
    // must have a user
    $username = kiosk_clean_value($username, true);
    if (empty($username)) {
        return $request['page']->error(_('Please enter your user name'), 'Password Change');    
    }
    
    // user must exist
    $query = $app['server']->query(array('base' => $udiconfig->getBaseDN(), 'filter' => "(|(mlepUsername=".$username.")(uid=".$username.")(sAMAccountName=".$username."))"), 'anon');
    if (empty($query)) {
        return $request['page']->error(_('User could not be found'), 'Password Change');
    }
    $query = array_shift($query);
    $dn = $query['dn'];
    
    // must have an exisitng password to bind with
    $oldpassword = kiosk_clean_value($oldpassword);
    if (empty($oldpassword)) {
        return $request['page']->error(_('Please enter your password'), 'Password Change');    
    }

    // ensure that this is not an existing logged in account
    if ($app['server']->isLoggedIn('user')) {
        $app['server']->logout('user');
    }
//    $suser = $app['server']->getLogin('user');
//    $spass = $app['server']->getPassword('user');
    // must be able to bind
    $app['server']->setLogin($dn, $oldpassword, 'user');
    $result = $app['server']->connect('user');
//    $app['server']->setLogin($suser, $spass, 'user');
    if ($result == null) {
        return $request['page']->error(_('Invalid password'), 'Password Change');
    }
    // may need to logout again afterwards
//    $app['server']->logout('user');
    
    // must provide new and confirmed new password
    if (empty($newpassword) || empty($confirm)) {
        return $request['page']->error(_('Please enter and confirm your new password'), 'Password Change');    
    }
    
    // new and confirm must be the same
    if ($newpassword !== $confirm || kiosk_clean_value($newpassword) != $newpassword) {
        return $request['page']->error(_('Please ensure that you confirm your new password , and the password is valid'), 'Password Change');    
    }
    
    // does the password pass the password checker?
    $result = udi_run_hook('passwd_policy_algorithm',array($app['server'], $udiconfig, $newpassword, $cfg['passwd_policy_parameters']), $cfg['passwd_policy_algo']);
    if ($result === false || $result[0] !== true) {
        return $request['page']->error(_('New Password failed password policy checks'), 'Password Change'); 
    }
    
    // all good to go - now change it
    
    
}
?>