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
 * @param String $adminuser Administration user DN
 * @param String $adminpass Adminstration user password
 * 
 * @return bool true on success
 */
function kiosk_change_passwd($username, $oldpassword, $newpassword, $confirm, $adminuser=false, $adminpass=false) {
    global $request, $udiconfig, $app;
    
    // must have a user
    $username = kiosk_clean_value($username, true);
    if (empty($username)) {
        return $request['page']->error(_('Please enter your user name'), 'Password Change');    
    }
    
    if (!empty($adminuser) && !empty($adminpass)) {
        $app['server']->setLogin($adminuser, $adminpass, 'user');
        $result = $app['server']->connect('user');
        if (!$result) {
            $_SESSION['sysmsg'] = array();
            return $request['page']->error(_('Invalid login for Kiosk Administrator account'), 'Kiosk');
        }
    }
    // must get the real values now that we should be a logged in user
    $cfg = $udiconfig->getConfig(true);
    
    // user must exist
    $query = $app['server']->query(array('base' => $udiconfig->getBaseDN(), 'filter' => "(|(mlepUsername=".$username.")(uid=".$username.")(sAMAccountName=".$username."))"), 'user');
    if (empty($query)) {
        return $request['page']->error(_('User could not be found'), 'Password Change');
    }
    // stash the DN for the actual update
    $query = array_shift($query);
    $dn = $query['dn'];
    
    // must have an exisitng password to bind with
    $oldpassword = kiosk_clean_value($oldpassword);
    
    // ensure that this is not an existing logged in account
    if ($app['server']->isLoggedIn('user')) {
        $app['server']->logout('user');
    }
    
    // do this if we aren't an administrator
    if ($adminuser) {
        // just set the admin user/pass
        $app['server']->setLogin($adminuser, $adminpass, 'user');
        $result = $app['server']->connect('user');
        if (!$result) {
            $_SESSION['sysmsg'] = array();
            return $request['page']->error(_('Invalid login for Administrator account'), 'Password Change');
        }
    }
    else {
        if (empty($oldpassword)) {
            return $request['page']->error(_('Please enter your password'), 'Password Change');    
        }
    
    //    $suser = $app['server']->getLogin('user');
    //    $spass = $app['server']->getPassword('user');
    
        // must be able to bind
        $app['server']->setLogin($dn, $oldpassword, 'user');
        $result = $app['server']->connect('user');
    
        // immediately logout because of subsequent error checking
        $app['server']->logout('user');
    //    $app['server']->setLogin($suser, $spass, 'user');
        if ($result == null) {
            $_SESSION['sysmsg'] = array();
            return $request['page']->error(_('Invalid password'), 'Password Change');
        }
        // may need to logout again afterwards
    //    $app['server']->logout('user');
    }
    
    // must provide new and confirmed new password
    if (empty($newpassword) || empty($confirm)) {
        return $request['page']->error(_('Please enter and confirm your new password'), 'Password Change');    
    }
    
    // new and confirm must be the same
    if ($newpassword !== $confirm || kiosk_clean_value($newpassword) != $newpassword) {
        return $request['page']->error(_('Please ensure that you confirm your new password , and the password is valid'), 'Password Change');    
    }
    
    // new must be different to old
    if ($newpassword == $oldpassword) {
        return $request['page']->error(_('Please ensure that your new password is different from the old'), 'Password Change');    
    }
    
    // does the password pass the password checker?
    $result = udi_run_hook('passwd_policy_algorithm',array($app['server'], $udiconfig, $newpassword, $cfg['passwd_policy_parameters']), $cfg['passwd_policy_algo']);
    if ($result === false || $result[0] !== true) {
        return $request['page']->error(_('New Password failed password policy checks'), 'Password Change'); 
    }
    
    // all good to go - now change it
    $app['server']->connect('user');
    $template = new Template($app['server']->getIndex(),null,null,'modify', null, true);
    $rdn = get_rdn($dn);
    $template->setDN($dn);
    $template->accept(false, 'user');
    
    if ($cfg['server_type'] == 'ad') {
        // Do not on any account - change the objectclass list - this only fills it 
        // and does not flag it as changed
        if (is_null($attribute = $template->getAttribute('objectclass'))) {
            $attribute = $template->addAttribute('objectclass', array('values'=> array('top', 'person', 'organizationalPerson', 'user')));
        }
        // now build the unicodePwd value        
        $attr = 'unicodePwd';
        $value = array(mb_convert_encoding('"' . $newpassword . '"', 'UCS-2LE', 'UTF-8'));
    }
    else {
        // update userPassword
        if (is_null($attribute = $template->getAttribute('objectclass'))) {
            $attribute = $template->addAttribute('objectclass', $udiconfig->getObjectClasses());
        }
        $this->modifyAttribute($template, 'objectclass', $user_total_classes);
        $attr = 'userPassword';
        $value = array(password_hash($newpassword, $cfg['encrypt_passwd']));
    }
    // set the attribute value for password
    if (is_null($attribute = $template->getAttribute(strtolower($attr)))) {
        $attribute = $template->addAttribute(strtolower($attr),array('values'=> $value));
        $attribute->justModified();
    }
    else {
        $attribute->clearValue();
        $attribute->setValue($value);
    }
    // do the actual update
    $result = $app['server']->modify($dn, $template->getLDAPmodify(), 'user');
    // final logout
    $app['server']->logout('user');
    
    // did it actually work ?
    if (!$result) {
        return $request['page']->error(_('Could not update user: ').$username, _('Password Change'));
    }
    else {
        $_SESSION['sysmsg'] = array();
        $request['page']->info(_('password changed for user: ').$username, _('Password Change'));
    }
    
    // now send out notifications
    $request['page']->email_passwd_change($username, $adminuser);
    return true;
}


/**
 * generate a new token for the recovery process
 * 
 * @param String $username
 * @param int $server_id
 * @return String token
 */
function kiosk_generate_token($username, $server_id) {
    // store the old session data
    $old_id = session_id();
    $old_name = session_name();
    session_write_close();
    
    // generate a new session to store the token
    session_name("uditoken");
    session_id(sha1(mt_rand()));
    $token = session_id();
    session_start();
    $_SESSION['username'] = $username;
    $_SESSION['server_id'] = $server_id;
    session_write_close();
    
    // resurect the old session
    session_id($old_id);
    session_name($old_name);
    session_start();

    return $token;
}

/**
 * Start the password recovery process
 * check username and emailaddress
 * send recovery token
 * 
 * @param String $username
 * @param String $email
 */
function kiosk_recover_passwd($username, $email) {
    global $request, $udiconfig, $app;
    $cfg = $udiconfig->getConfig();
    
    // must have a user
    $username = kiosk_clean_value($username, true);
    if (empty($username)) {
        return $request['page']->error(_('Please enter your user name'), 'Password Recovery');    
    }

    // must have an email address
    $email = kiosk_clean_value($email, true);
    if (empty($email)) {
        return $request['page']->error(_('Please enter your email address'), 'Password Recovery');    
    }
    
    // user must exist
    $query = $app['server']->query(array('base' => $udiconfig->getBaseDN(), 'filter' => "(|(mlepUsername=".$username.")(uid=".$username.")(sAMAccountName=".$username."))"), 'anon');
    if (empty($query)) {
        return $request['page']->error(_('User could not be found'), 'Password Recovery');
    }
    // stash the DN for the actual update
    $query = array_shift($query);
    $dn = $query['dn'];
    
    // email must match the user account
    $check_email = isset($query['mlepemail']) ? $query['mlepemail'][0] : (isset($query['mail']) ? $query['mail'][0] : '');
    if (strtolower(trim($email)) != strtolower(trim($check_email))){
        return $request['page']->error(_('Email address does not match user account'), 'Password Recovery');
    }
    
    // now, create and send a recovery token
    $token = kiosk_generate_token($username, $app['server']->getIndex());
    
    // now - email out the recovery token
    if ($request['page']->email_reset_token($email, $username, $token)) {
        return $request['page']->info(_('An email has been sent to: ').$email._('. Please follow the instructions sent within.'), 'Password Recovery');
    }
    else {
        return $request['page']->error(_('Recovery token issue failed - please contact administrator'), 'Password Recovery');
    }
}

/**
 * verify the password reset token 
 * 
 * @param String $token
 * @return false of array of reset values
 */
function kiosk_verify_token($token, $destroy=false, $regen=false) {
    global $request, $udiconfig, $app;
    
    // bail if there is no token
    if (empty($token)) {
        return false;
    }
    
    // must have a valid token
    $token = kiosk_clean_value($token, true);
    if (empty($token)) {
        return $request['page']->error(_('Authentication token invalid - please resubmit request'), 'Password Recovery');    
    }
    
    $cfg = $udiconfig->getConfig();
    
    // store the old session data
    $old_id = session_id();
    $old_name = session_name();
    session_write_close();
    
    // generate a new session to store the token
    session_name("uditoken");
    session_id($token);
    session_start();
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    $server_id = isset($_SESSION['server_id']) ? $_SESSION['server_id'] : null;
    // wipeout the session data, so that this token cannot be used again
    if ($destroy || $regen) {
        unset($_SESSION['username']);
        unset($_SESSION['server_id']);
    }
    session_write_close();
    
    // resurect the old session
    session_id($old_id);
    session_name($old_name);
    session_start();
    
    if (empty($username) || empty($server_id)) {
        return $request['page']->error(_('Authentication token expired - please resubmit request'), 'Password Recovery');    
    }
    
    if ($regen) {
        $token = kiosk_generate_token($username, $server_id);
    }
    else {
        $token = null;
    }
    
    return array('server_id' => $server_id, 'username' => $username, 'token' => $token);
}


/**
 * Handle a change password request
 * 
 * @param String $dn the user DN
 * @param String $adminuser Administration user DN
 * @param String $adminpass Adminstration user password
 * @param String $action is this a deactivate or reaactivate
 * 
 * @return bool true on success
 */
function kiosk_toggle_user($dn, $adminuser, $adminpass, $action) {
    global $request, $udiconfig, $app;
    $cfg = $udiconfig->getConfig();
    
    // ensure that this is not an existing logged in account
    if ($app['server']->isLoggedIn('user')) {
        $app['server']->logout('user');
    }
    
    // just set the admin user/pass
    $app['server']->setLogin($adminuser, $adminpass, 'user');
    $result = $app['server']->connect('user');
    if (!$result) {
        $_SESSION['sysmsg'] = array();
        return $request['page']->error(_('Invalid login for Administrator account'), 'Un/Lock Account');
    }
    // initialise the cache
    $tree = Tree::getInstance($app['server']->getIndex());
    set_cached_item($app['server']->getIndex(),'tree','null',$tree);

    
    
    $processor = new Processor($app['server']);
    if ($action == 'reactivate') {
        if ($processor->reactivate($dn)) {
            $_SESSION['sysmsg'] = array();
            $request['page']->info(_('User account reactivated'));
        }
        else {
            $request['page']->error(_('Account not reactivated'));
        }
    }
    else if ($action == 'deactivate') {
        $processor->to_be_deactivated = $app['server']->query(array('base' => $dn), 'user');
        if (count($processor->to_be_deactivated) ==  1) {
            if ($processor->processDeactivations()) {
                $_SESSION['sysmsg'] = array();
                $request['page']->info(_('User account deactivated'));
            }
            else {
                $request['page']->error(_('Account not deactivated'));
            }
        }
        else {
            $request['page']->error(_('Account not found'));
        }
    }
    
    // final logout
    $app['server']->logout('user');
    
//    $request['page']->email_passwd_change($username, $adminuser);
    return true;
}

/**
 * Check that an administrator user is valid
 * 
 * @param string $adminuser administrator user name
 * @return String Admin user DN
 */
function kiosk_check_admin($adminuser) {
    global $app, $request, $udiconfig;

    $adminuser = kiosk_clean_value($adminuser, true);
    $query = $app['server']->query(array('base' => $udiconfig->getBaseDN(), 'filter' => "(|(mlepUsername=".$adminuser.")(uid=".$adminuser.")(sAMAccountName=".$adminuser."))"), 'anon');
    if (empty($query)) {
        return $request['page']->error(_('Administration User could not be found'), 'Un/Lock Account');
    }
    else {
        // stash the DN for the actual update
        $query = array_shift($query);
        $admindn = $query['dn'];
        return $admindn;       
    }
}


/**
 * Check that a user is valid
 * 
 * @param string $username administrator user name
 * @return array user
 */
function kiosk_check_user_active($username, $adminuser, $adminpass) {
    global $app, $request, $udiconfig;
    
    $username = kiosk_clean_value($username, true);
    
    // ensure that this is not an existing logged in account
    if ($app['server']->isLoggedIn('user')) {
        $app['server']->logout('user');
    }
    
    // use the adminuser
    $app['server']->setLogin($adminuser, $adminpass, 'user');
    $result = $app['server']->connect('user');
    if (!$result) {
        $_SESSION['sysmsg'] = array();
        return $request['page']->error(_('Invalid login for Administrator account'), 'Un/Lock Account');
    }
    
    // get the config now that we have a logged in user
    $cfg = $udiconfig->getConfig(true);
    
    // check deactivated first
    $query = $app['server']->query(array('base' => $cfg['move_to'], 'filter' => "(|(mlepUsername=".$username.")(uid=".$username.")(sAMAccountName=".$username."))"), 'user');
    
    if (!empty($query)) {
        $result = array_shift($query);
        $result['deactive'] = true;
        $app['server']->logout('user');
        return $result;
    }
    
    $bases = explode(';', $cfg['search_bases']);
    $bases []= 'ou=people,dc=example,dc=com';
    $result = false;
    // run through all the search bases
    foreach ($bases as $base) {
        // ensure that accounts inspected have the mlepPerson object class
        $query = $app['server']->query(array('base' => $base, 'filter' => "(|(mlepUsername=".$username.")(uid=".$username.")(sAMAccountName=".$username."))"), 'user');
        if (!empty($query)) {
            $result = array_shift($query);
            $result['deactive'] = false;
            break;
        }
    }
    $app['server']->logout('user');
    if (!$result) {
        return $request['page']->error(_('User could not be found'), 'Un/Lock Account');
    }
    return $result;
}

?>